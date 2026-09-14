<?php
// ============================================================
//  Tenancy — which salon a request belongs to.
//
//  Many salons share one database. Every business row carries a
//  tenant_id and every query filters on it. This is the one place
//  that decides what that id is, and it never takes it from the
//  browser: it comes from the signed-in session, or — on pages
//  nobody signs in to — from something the salon issued itself
//  (its booking-page address, a registered device, a review link).
// ============================================================

/** Every table whose rows belong to one salon. */
const TENANT_TABLES = [
    // booking site
    'admin_users', 'business_settings', 'business_hours', 'blocked_dates',
    'technicians', 'services', 'technician_services', 'appointments', 'sms_log',
    // the till
    'pos_settings', 'pos_products', 'pos_sales', 'pos_sale_items', 'pos_payments',
    'pos_cash_movements', 'pos_clients', 'pos_client_notes', 'pos_consents',
    'pos_consent_templates', 'pos_checkins', 'pos_tech_shifts', 'pos_gift_cards',
    'pos_gift_card_txns', 'pos_loyalty_txns', 'pos_stamp_txns', 'pos_expenses',
    'pos_campaigns', 'pos_feedback', 'pos_refunds', 'pos_refund_items',
    'pos_counters', 'pos_polish_brands', 'pos_nail_designs',
];

/** Raised whenever the tenancy schema learns something new; tenancyBoot() catches up. */
const TENANCY_VERSION = 1;

// Refuse any query on a salon's table that never mentions tenant_id. Off until
// every query in the app has been taught to scope itself.
defined('TENANT_GUARD') || define('TENANT_GUARD', false);

final class TenantMissing extends RuntimeException {}

/**
 * Pin this request to a salon. For the pages with no sign-in — the booking
 * site, a review link, a cron job walking every salon — once they have worked
 * out which salon they are for from something the salon issued.
 */
function tenantUse(int $tenantId): void {
    if ($tenantId < 1) throw new TenantMissing('That salon does not exist.');
    $GLOBALS['__tenant_id'] = $tenantId;
}

/** The salon this request is for. Throws rather than guess when there is more than one. */
function tenantId(): int {
    if (!empty($GLOBALS['__tenant_id'])) return (int)$GLOBALS['__tenant_id'];

    // A returning browser already has a session: open it rather than guess.
    if (session_status() === PHP_SESSION_NONE && function_exists('startSecureSession')
        && isset($_COOKIE[session_name()]) && !headers_sent()) {
        startSecureSession();
    }
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['tenant_id'])) {
        return (int)$_SESSION['tenant_id'];
    }
    // One salon on this install means there is nothing to choose between —
    // which is every shop that upgraded from before there were tenants.
    if ($only = soleTenantId()) return $only;

    throw new TenantMissing('This page does not know which salon it is for.');
}

function soleTenantId(): ?int {
    static $id = false;
    if ($id === false) {
        $rows = fetchAll("SELECT id FROM tenants WHERE status <> 'cancelled' ORDER BY id LIMIT 2");
        $id = count($rows) === 1 ? (int)$rows[0]['id'] : null;
    }
    return $id;
}

function tenantFind(int $id): ?array {
    return fetchOne('SELECT t.*, p.code AS plan_code, p.name AS plan_name, p.max_locations,
                            p.max_devices, p.max_employees, p.max_users
                       FROM tenants t LEFT JOIN plans p ON p.id = t.plan_id
                      WHERE t.id = ?', [$id]);
}

/** The salon this request belongs to, with its plan's limits. */
function currentTenant(): array {
    static $cache = [];
    $id = tenantId();
    if (!isset($cache[$id])) {
        $row = tenantFind($id);
        if (!$row) throw new TenantMissing('That salon no longer exists.');
        $cache[$id] = $row;
    }
    return $cache[$id];
}

function tenantBySlug(string $slug): ?array {
    $slug = strtolower(trim($slug));
    if ($slug === '') return null;
    return fetchOne("SELECT * FROM tenants WHERE slug = ? AND status <> 'cancelled'", [$slug]);
}

/** A booking-page address from a salon name: "Lovely Nail & Spa" → "lovely-nail-spa". */
function tenantSlugify(string $name): string {
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $name);
    $slug  = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $ascii !== false ? $ascii : $name), '-'));
    return substr($slug, 0, 60) ?: 'salon';
}

/** Null when the salon may sign in, otherwise the reason it may not. */
function tenantSignInBlock(array $tenant): ?string {
    switch ($tenant['status'] ?? '') {
        case 'suspended': return 'This salon\'s account is suspended. Please contact support.';
        case 'cancelled': return 'This salon\'s account has been closed.';
    }
    return null;
}

/**
 * Run $fn with the tenant guard lifted. For the few lookups that must find a
 * row before the salon is known — sign-in by email, a review link, a device
 * token — and for work that spans salons on purpose. Named so it stands out
 * in review.
 */
function unscoped(callable $fn) {
    $was = $GLOBALS['__tenant_unscoped'] ?? false;
    $GLOBALS['__tenant_unscoped'] = true;
    try {
        return $fn();
    } finally {
        $GLOBALS['__tenant_unscoped'] = $was;
    }
}

/**
 * The backstop behind every query: a statement that reads or writes a salon's
 * table without ever mentioning tenant_id is refused before it runs. It cannot
 * prove a query is scoped correctly — only catch the one that forgot entirely,
 * which is the mistake that leaks one salon's clients to another.
 */
function tenantGuard(string $sql): void {
    if (!TENANT_GUARD || !empty($GLOBALS['__tenant_unscoped'])) return;
    if (stripos($sql, 'tenant_id') !== false) return;
    if (!preg_match_all('/\b(?:FROM|JOIN|INTO|UPDATE)\s+`?([A-Za-z_][A-Za-z0-9_]*)`?/i', $sql, $m)) return;
    foreach ($m[1] as $table) {
        if (in_array(strtolower($table), TENANT_TABLES, true)) {
            throw new LogicException('Query on ' . $table . ' is not scoped to a salon: '
                . substr(preg_replace('/\s+/', ' ', $sql), 0, 160));
        }
    }
}

/**
 * First database use of the request: make sure the tenancy schema is in place
 * before anything reads a salon's rows. After an upgrade the first page anyone
 * opens does the migration, so nobody is locked out of a till whose sign-in
 * code already expects tenants. Once it is done this is one primary-key read.
 */
function tenancyBoot(): void {
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        $version = (int)db()->query("SELECT v FROM app_meta WHERE k = 'tenancy'")->fetchColumn();
    } catch (PDOException $e) {
        $version = 0;   // no app_meta yet: this database has never been migrated
    }
    if ($version >= TENANCY_VERSION) return;

    require_once __DIR__ . '/schema.php';
    try {
        $log = migrateTenancy();
        // A record of what an unattended upgrade changed, for whoever looks later.
        if ($log) error_log('Tenancy migration: ' . implode('; ', $log));
    } catch (Throwable $e) {
        error_log('Tenancy migration failed: ' . $e->getMessage());
        if (PHP_SAPI === 'cli') throw $e;
        http_response_code(503);
        echo '<!doctype html><meta charset="utf-8"><title>Upgrade did not finish</title>'
           . '<div style="font:16px/1.6 system-ui;max-width:34em;margin:12vh auto;padding:0 24px">'
           . '<h1 style="font-size:22px">The database upgrade did not finish</h1>'
           . '<p>Nothing was deleted. The reason is in the PHP error log — fix that and reload.</p></div>';
        exit;
    }
}
