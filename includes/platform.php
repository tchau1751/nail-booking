<?php
// ============================================================
//  The platform — the business that runs many salons.
//
//  Platform admins are not salon staff. They sign in on their own
//  page, with their own session cookie scoped to /platform/, and
//  their accounts live in their own table, so no salon login can
//  ever reach this and no platform login is a salon login. From here
//  salons are opened, moved between plans and suspended, and every
//  change is written to the platform audit log.
// ============================================================
require_once __DIR__ . '/schema.php';   // database, tenancy helpers, ensureTenantDefaults()

const PLATFORM_SESSION = 'NAILPLATFORM';
const PLATFORM_MAX_FAILS = 10;           // failed sign-ins per address per 15 minutes
const TENANT_STATUSES = [
    'trial'     => 'Trial',
    'active'    => 'Active',
    'past_due'  => 'Past due',
    'suspended' => 'Suspended',
    'cancelled' => 'Cancelled',
];

function platformSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(PLATFORM_SESSION);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/' . SUBFOLDER . '/platform/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        session_start();
    }
}

function platformAdmin(): ?array {
    platformSession();
    if (empty($_SESSION['platform_admin_id'])) return null;
    static $row = false;
    if ($row === false) {
        $row = fetchOne('SELECT * FROM platform_admins WHERE id=? AND is_active=1', [$_SESSION['platform_admin_id']]) ?: null;
    }
    return $row;
}

function platformRequire(): array {
    $a = platformAdmin();
    if (!$a) {
        header('Location: ' . BASE_PATH . '/platform/login.php');
        exit;
    }
    return $a;
}

function platformCsrf(): string {
    platformSession();
    if (empty($_SESSION['platform_csrf'])) $_SESSION['platform_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['platform_csrf'];
}

function platformCsrfValid(?string $sent): bool {
    platformSession();
    return !empty($_SESSION['platform_csrf']) && is_string($sent) && hash_equals($_SESSION['platform_csrf'], $sent);
}

function platformAudit(string $action, string $detail = '', ?int $tenantId = null, ?int $adminId = null): void {
    query('INSERT INTO platform_audit (admin_id, tenant_id, action, detail, ip) VALUES (?,?,?,?,?)',
          [$adminId ?? ($_SESSION['platform_admin_id'] ?? null), $tenantId, $action,
           mb_substr($detail, 0, 255), $_SERVER['REMOTE_ADDR'] ?? 'cli']);
}

/**
 * Signs a platform admin in. Returns null on success or the message to show.
 * An address that keeps guessing is shut out for a quarter of an hour: this
 * page opens every salon's books.
 */
function platformLogin(string $email, string $password): ?string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $fails = (int)fetchOne("SELECT COUNT(*) n FROM platform_audit
                            WHERE action='sign_in_failed' AND ip=? AND created_at >= NOW() - INTERVAL 15 MINUTE", [$ip])['n'];
    if ($fails >= PLATFORM_MAX_FAILS) return 'Too many failed attempts. Wait fifteen minutes and try again.';

    $email = strtolower(trim($email));
    $a = fetchOne('SELECT * FROM platform_admins WHERE email=? AND is_active=1', [$email]);
    if (!$a || !password_verify($password, $a['password_hash'])) {
        platformAudit('sign_in_failed', $email, null, null);
        return 'Invalid email or password.';
    }
    platformSession();
    session_regenerate_id(true);
    $_SESSION['platform_admin_id'] = (int)$a['id'];
    query('UPDATE platform_admins SET last_login_at=NOW() WHERE id=?', [$a['id']]);
    platformAudit('sign_in', $email, null, (int)$a['id']);
    return null;
}

function platformLogout(): void {
    platformSession();
    $_SESSION = [];
    session_destroy();
}

/**
 * Open a new salon: the salon, its starter settings, hours, policies and polish
 * list, and an owner who can sign in straight away. All of it or none of it.
 */
function tenantCreate(array $in): int {
    $name          = mb_substr(trim((string)($in['name'] ?? '')), 0, 160);
    $slug          = tenantSlugify(trim((string)($in['slug'] ?? '')) !== '' ? (string)$in['slug'] : $name);
    $planCode      = (string)($in['plan'] ?? 'basic');
    $status        = array_key_exists((string)($in['status'] ?? ''), TENANT_STATUSES) ? (string)$in['status'] : 'trial';
    $ownerName     = mb_substr(trim((string)($in['owner_name'] ?? '')), 0, 120);
    $ownerEmail    = strtolower(trim((string)($in['owner_email'] ?? '')));
    $ownerPassword = (string)($in['owner_password'] ?? '');

    if ($name === '')                                     throw new RuntimeException('Give the salon a name.');
    if (fetchOne('SELECT 1 x FROM tenants WHERE slug=?', [$slug]))
                                                          throw new RuntimeException('The booking address "' . $slug . '" is taken — choose another.');
    $plan = fetchOne('SELECT * FROM plans WHERE code=?', [$planCode]);
    if (!$plan)                                           throw new RuntimeException('Choose a plan.');
    if (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL))  throw new RuntimeException('The owner\'s email address does not look right.');
    if (strlen($ownerPassword) < 10)                      throw new RuntimeException('The owner\'s first password needs at least 10 characters.');
    // Sign-in finds a salon by email, so an email can belong to one salon only.
    $taken = unscoped(function () use ($ownerEmail) {
        return fetchOne('SELECT 1 x FROM admin_users WHERE email=?', [$ownerEmail]);
    });
    if ($taken)                                           throw new RuntimeException('That email already signs in to a salon.');

    $pdo = db();
    $pdo->beginTransaction();
    try {
        query('INSERT INTO tenants (slug, name, plan_id, status, trial_ends_on) VALUES (?,?,?,?,?)',
              [$slug, $name, $plan['id'], $status, $status === 'trial' ? date('Y-m-d', strtotime('+14 days')) : null]);
        $tid = (int)$pdo->lastInsertId();
        ensureTenantDefaults($tid, $name);
        query('INSERT INTO admin_users (tenant_id, name, email, password_hash, role, is_active) VALUES (?,?,?,?,?,1)',
              [$tid, $ownerName ?: 'Owner', $ownerEmail, password_hash($ownerPassword, PASSWORD_BCRYPT, ['cost' => 12]), 'owner']);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    platformAudit('salon_opened', $name . ' (' . $slug . '), ' . $plan['name'] . ', owner ' . $ownerEmail, $tid);
    return $tid;
}

function tenantSetPlan(int $tenantId, string $planCode): void {
    $t    = tenantFind($tenantId);
    $plan = fetchOne('SELECT * FROM plans WHERE code=?', [$planCode]);
    if (!$t || !$plan) throw new RuntimeException('Salon or plan not found.');
    if ((int)$t['plan_id'] === (int)$plan['id']) return;
    query('UPDATE tenants SET plan_id=? WHERE id=?', [$plan['id'], $tenantId]);
    platformAudit('plan_changed', ($t['plan_name'] ?? 'none') . ' → ' . $plan['name'], $tenantId);
}

/**
 * Suspending a salon stops new sign-ins; people already on a till finish what
 * they are doing. Nothing a salon owns is deleted by any status.
 */
function tenantSetStatus(int $tenantId, string $status): void {
    $t = tenantFind($tenantId);
    if (!$t) throw new RuntimeException('Salon not found.');
    if (!array_key_exists($status, TENANT_STATUSES)) throw new RuntimeException('Unknown status.');
    if ($t['status'] === $status) return;
    query('UPDATE tenants SET status=? WHERE id=?', [$status, $tenantId]);
    platformAudit('status_changed', TENANT_STATUSES[$t['status']] . ' → ' . TENANT_STATUSES[$status], $tenantId);
}

/** What a salon has switched on against its plan, and how busy it is. */
function tenantUsage(int $tenantId): array {
    $count = function (string $sql) use ($tenantId): int {
        return (int)(fetchOne($sql, [$tenantId])['n'] ?? 0);
    };
    return [
        'devices'   => $count('SELECT COUNT(*) n FROM pos_devices WHERE tenant_id=? AND is_active=1'),
        'employees' => $count('SELECT COUNT(*) n FROM technicians WHERE tenant_id=? AND is_active=1'),
        'users'     => $count('SELECT COUNT(*) n FROM admin_users WHERE tenant_id=? AND is_active=1'),
        'sales_30d' => $count("SELECT COUNT(*) n FROM pos_sales WHERE tenant_id=? AND status <> 'voided'
                                AND created_at >= NOW() - INTERVAL 30 DAY"),
    ];
}
