<?php
require_once __DIR__ . '/db.php';

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/' . SUBFOLDER . '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/**
 * Signed in, tied to a salon, and — for a session started on a registered
 * device — on a device that is still switched on.
 *
 * A session left open from before there were salons has a user but no salon:
 * it is given that user's own salon rather than thrown out, so an upgrade never
 * signs anyone out of a till mid-ticket.
 */
function isLoggedIn(): bool {
    startSecureSession();
    if (empty($_SESSION['admin_id'])) return false;
    if (empty($_SESSION['tenant_id'])) {
        $u = unscoped(function () {
            return fetchOne('SELECT tenant_id FROM admin_users WHERE id=? AND is_active=1', [$_SESSION['admin_id']]);
        });
        if (!$u) { $_SESSION = []; return false; }
        $_SESSION['tenant_id'] = (int)$u['tenant_id'];
    }
    // A manager switched this tablet off: whoever is on it is signed out on
    // their next tap, not whenever the session happens to run out.
    if (!empty($_SESSION['device_id']) && !deviceStillOn((int)$_SESSION['device_id'], (int)$_SESSION['tenant_id'])) {
        $_SESSION = [];
        return false;
    }
    return true;
}

function deviceStillOn(int $deviceId, int $tenantId): bool {
    static $seen = [];
    if (!isset($seen[$deviceId])) {
        $seen[$deviceId] = (bool)fetchOne('SELECT 1 x FROM pos_devices WHERE id=? AND tenant_id=? AND is_active=1',
                                          [$deviceId, $tenantId]);
    }
    return $seen[$deviceId];
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_PATH . '/admin/login.php');
        exit;
    }
}

function currentAdmin(): ?array {
    if (!isLoggedIn()) return null;
    return fetchOne('SELECT * FROM admin_users WHERE id=? AND tenant_id=?',
                    [$_SESSION['admin_id'], $_SESSION['tenant_id']]);
}

/**
 * The five roles, least trusted first. Each can do everything the ones before
 * it can: a front desk can ring up a sale, a manager can seat a guest.
 */
const ROLES = [
    'technician' => 'Technician',
    'cashier'    => 'Cashier',
    'front_desk' => 'Front desk',
    'manager'    => 'Manager',
    'owner'      => 'Owner',
];

function roleRank(?string $role): int {
    // Accounts from before there were five roles could run the register, the
    // queue and the kiosk — which is what the front desk does.
    if ($role === 'staff') $role = 'front_desk';
    $i = array_search($role, array_keys(ROLES), true);
    return $i === false ? 0 : $i + 1;
}

function roleLabel(?string $role): string {
    return ROLES[$role === 'staff' ? 'front_desk' : (string)$role] ?? 'No role';
}

function currentRole(): string {
    $a = currentAdmin();
    return $a['role'] ?? '';
}

function hasRole(string $atLeast): bool {
    return roleRank(currentRole()) >= roleRank($atLeast);
}

/** The first till screen a person's role can open — where signing in takes them. */
function posHome(): string {
    return BASE_PATH . '/pos/' . (hasRole('cashier') ? '' : 'queue.php');
}

/**
 * Gate a page behind a minimum role. Money and staff pages use this so a
 * technician signed in at the till can't read payroll or change settings.
 */
function requireRole(string $atLeast): void {
    requireLogin();
    if (!hasRole($atLeast)) {
        http_response_code(403);
        echo '<!doctype html><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<title>Not allowed</title>'
           . '<div style="font:16px/1.5 -apple-system,Segoe UI,sans-serif;max-width:460px;'
           . 'margin:14vh auto;padding:28px;text-align:center;color:#3a2a24">'
           . '<div style="font-size:52px">🔒</div>'
           . '<h1 style="font-size:22px;margin:10px 0">Not your pay grade</h1>'
           . '<p style="color:#7a6a63">This screen needs ' . e(roleLabel($atLeast)) . ' access or above. '
           . 'Ask a manager to sign in if you need it.</p>'
           . '<a href="' . e(posHome()) . '" style="display:inline-block;margin-top:18px;padding:14px 24px;'
           . 'background:#3a2a24;color:#fff;border-radius:12px;text-decoration:none;font-weight:700">'
           . 'Back to your screen</a></div>';
        exit;
    }
}

/** The same gate for a JSON endpoint: an answer the tablet can read, not an HTML page. */
function requireRoleJson(string $atLeast): void {
    if (!isLoggedIn()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Not signed in.']);
        exit;
    }
    if (!hasRole($atLeast)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'This needs ' . roleLabel($atLeast) . ' access or above.']);
        exit;
    }
}

/** What every way of signing in writes into the session. The salon comes from the user's own row. */
function signIn(array $user): void {
    startSecureSession();
    session_regenerate_id(true);
    // Nothing from another salon rides along into this one: open tickets, a
    // manager's approval, a half-filled form.
    if (isset($_SESSION['tenant_id']) && (int)$_SESSION['tenant_id'] !== (int)$user['tenant_id']) {
        $_SESSION = [];
    }
    $_SESSION['admin_id']   = $user['id'];
    $_SESSION['admin_name'] = $user['name'];
    $_SESSION['admin_role'] = $user['role'];
    $_SESSION['tenant_id']  = (int)$user['tenant_id'];

    // Signing in on one of the salon's registered devices ties the session to
    // it: switching the device off signs this person out, and each sale knows
    // which station rang it up.
    unset($_SESSION['device_id']);
    $device = deviceFromCookie();
    if ($device && (int)$device['is_active'] === 1 && (int)$device['tenant_id'] === (int)$user['tenant_id']) {
        $_SESSION['device_id'] = (int)$device['id'];
        query('UPDATE pos_devices SET last_user_id=?, last_seen_at=NOW() WHERE id=? AND tenant_id=?',
              [$user['id'], $device['id'], $user['tenant_id']]);
    }
}

/**
 * Signs in by email and password. Returns null on success, or the message to
 * show. The email is looked up across every salon — it is the one thing that
 * says which salon this person works for.
 */
function loginAdmin(string $email, string $password): ?string {
    $a = unscoped(function () use ($email) {
        return fetchOne('SELECT * FROM admin_users WHERE email=? AND is_active=1', [$email]);
    });
    if (!$a || !password_verify($password, $a['password_hash'])) {
        return 'Invalid email or password. Please try again.';
    }
    $tenant = tenantFind((int)$a['tenant_id']);
    if (!$tenant) return 'Invalid email or password. Please try again.';
    if ($why = tenantSignInBlock($tenant)) return $why;

    signIn($a);
    return null;
}

function logoutAdmin(): void {
    startSecureSession();
    $_SESSION = [];
    session_destroy();
}

// ============================================================
//  Till PIN sign-in
//
//  Four digits is a small haystack, so this is deliberately not the
//  same door as the email login: a PIN only works on a shared salon
//  tablet, it locks out after a few wrong guesses, and it is stored
//  hashed exactly like a password. The email login stays the way in
//  for anything sensitive.
//
//  The tablet already knows its salon before anyone signs in, so the
//  name list, the PIN check and the manager approval all stay inside
//  that one salon — another salon's manager PIN approves nothing here.
//  And once a salon has registered its devices, a PIN only works on
//  one of them.
// ============================================================

const PIN_MIN_DIGITS  = 4;
const PIN_MAX_FAILS   = 5;
const PIN_LOCK_MINUTES = 5;

/**
 * Null when this browser may use a PIN, otherwise why not. A salon that has
 * never registered a device keeps working exactly as before; one that has
 * registered any takes PINs only on those.
 */
function pinBlockedHere(): ?string {
    $tid    = tenantId();
    $device = deviceFromCookie();
    if ($device && (int)$device['is_active'] === 1 && (int)$device['tenant_id'] === $tid) return null;
    $usesDevices = (bool)fetchOne('SELECT 1 x FROM pos_devices WHERE tenant_id=? AND is_active=1 LIMIT 1', [$tid]);
    return $usesDevices
        ? 'This tablet is not registered to the salon, so PINs do not work on it. Sign in with your email and '
          . 'password, or ask a manager to register it under Devices.'
        : null;
}

/** People who can sign in at this salon's till, for the name list on the PIN screen. */
function pinUsers(): array {
    return fetchAll("SELECT id, name, role FROM admin_users
                     WHERE tenant_id=? AND is_active=1 AND pin_hash IS NOT NULL AND pin_hash <> ''
                     ORDER BY name", [tenantId()]);
}

function pinLockedFor(array $user): int {
    if (empty($user['pin_locked_until'])) return 0;
    return max(0, strtotime($user['pin_locked_until']) - time());
}

/**
 * Signs in by PIN. Returns null on success, or a message to show the user.
 * The message never says whether the PIN was close — only that it was wrong.
 */
function loginByPin(int $userId, string $pin): ?string {
    $tid = tenantId();
    if ($why = pinBlockedHere()) return $why;
    $u = fetchOne('SELECT * FROM admin_users WHERE id=? AND tenant_id=? AND is_active=1', [$userId, $tid]);
    if (!$u || empty($u['pin_hash'])) return 'That person cannot sign in with a PIN.';
    if ($why = tenantSignInBlock(currentTenant())) return $why;

    if ($wait = pinLockedFor($u)) {
        return 'Too many wrong PINs. Try again in ' . ceil($wait / 60) . ' min, or sign in with an email and password.';
    }
    if (!password_verify($pin, $u['pin_hash'])) {
        $fails = (int)$u['pin_fails'] + 1;
        if ($fails >= PIN_MAX_FAILS) {
            query('UPDATE admin_users SET pin_fails=0, pin_locked_until=DATE_ADD(NOW(), INTERVAL ? MINUTE)
                   WHERE id=? AND tenant_id=?', [PIN_LOCK_MINUTES, $u['id'], $tid]);
            return 'Too many wrong PINs. Locked for ' . PIN_LOCK_MINUTES . ' minutes.';
        }
        query('UPDATE admin_users SET pin_fails=? WHERE id=? AND tenant_id=?', [$fails, $u['id'], $tid]);
        return 'Wrong PIN.';
    }

    query('UPDATE admin_users SET pin_fails=0, pin_locked_until=NULL WHERE id=? AND tenant_id=?', [$u['id'], $tid]);
    signIn($u);
    return null;
}

/**
 * Checks a PIN against this salon's active managers and owners, without
 * signing anyone in — used when a manager stands over a technician's shoulder
 * to approve a discount. Returns the approving user, or null.
 */
function managerByPin(string $pin): ?array {
    if (strlen($pin) < PIN_MIN_DIGITS) return null;
    $rows = fetchAll("SELECT * FROM admin_users
                      WHERE tenant_id=? AND is_active=1 AND role IN ('manager','owner')
                        AND pin_hash IS NOT NULL AND pin_hash <> ''", [tenantId()]);
    foreach ($rows as $m) {
        if (pinLockedFor($m)) continue;
        if (password_verify($pin, $m['pin_hash'])) return $m;
    }
    return null;
}

/** How long one manager approval stays good for, in seconds. */
const MANAGER_APPROVAL_SECONDS = 120;

function grantManagerApproval(array $manager): void {
    startSecureSession();
    $_SESSION['mgr_ok_until'] = time() + MANAGER_APPROVAL_SECONDS;
    $_SESSION['mgr_ok_by']    = $manager['name'];
}

/** True for a manager/owner signed in, or a fresh over-the-shoulder approval. */
function managerApproved(): bool {
    if (hasRole('manager')) return true;
    startSecureSession();
    return !empty($_SESSION['mgr_ok_until']) && $_SESSION['mgr_ok_until'] > time();
}

function clearManagerApproval(): void {
    startSecureSession();
    unset($_SESSION['mgr_ok_until'], $_SESSION['mgr_ok_by']);
}
