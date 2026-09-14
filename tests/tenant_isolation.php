<?php
// ============================================================
//  Salon isolation test.
//
//  Proves that one salon can neither see nor change another's data,
//  at the level of the functions every page is built on, with the
//  query guard switched on so any unscoped query fails loudly.
//
//  It WRITES. It creates (or reuses) a second salon, "Isolation Test
//  Salon", with its own staff, menu, client and sales, rings a sale
//  there, and runs "Reset everything" on it. It also adds a manager
//  called "Isolation Manager" to salon 1. Run it on a copy of the
//  database, never the live one:
//
//    D:\xampp\php\php.exe tests\tenant_isolation.php --test-database
//
//  Exit code 0 means every check passed.
// ============================================================
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }   // never from a browser
define('TENANT_GUARD', true);
chdir(dirname(__DIR__));
require 'pos/includes/salon.php';
require 'pos/includes/rewards.php';
require 'pos/includes/purge.php';
require 'includes/schema.php';
// The till keeps open tickets in the session, so the test needs one — and PHP
// will only open it before anything has been printed.
startSecureSession();

if (!in_array('--test-database', $argv, true)) {
    fwrite(STDERR, "This test writes to the database " . DB_NAME . ".\n"
                 . "If that is a copy, run it again with --test-database.\n");
    exit(2);
}

$failures = 0;
function check(string $what, bool $ok): void {
    global $failures;
    echo ($ok ? "  ok    " : "  FAIL  ") . $what . "\n";
    if (!$ok) $failures++;
}
function throws(callable $fn): bool {
    try { $fn(); return false; } catch (Throwable $e) { return true; }
}
/** Act as a signed-in user of a salon, the way a request would. */
function actAs(int $tenantId, int $userId): void {
    tenantUse($tenantId);
    startSecureSession();
    $_SESSION['admin_id']  = $userId;
    $_SESSION['tenant_id'] = $tenantId;
}

migrateTenancy();

// ── Salon A: salon 1, as it already is ─────────────────────────
$A = 1;
tenantUse($A);
$ownerA   = fetchOne("SELECT * FROM admin_users WHERE tenant_id=? AND role='owner' AND is_active=1 ORDER BY id LIMIT 1", [$A]);
$managerA = fetchOne("SELECT * FROM admin_users WHERE tenant_id=? AND email='manager@isolation-a.test'", [$A]);
if (!$managerA) {
    query("INSERT INTO admin_users (tenant_id, name, email, password_hash, role, pin_hash) VALUES (?,?,?,?,?,?)",
          [$A, 'Isolation Manager', 'manager@isolation-a.test', password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT),
           'manager', password_hash('9731', PASSWORD_BCRYPT)]);
    $managerA = fetchOne('SELECT * FROM admin_users WHERE id=? AND tenant_id=?', [db()->lastInsertId(), $A]);
}
$techA    = fetchOne('SELECT * FROM technicians WHERE tenant_id=? AND is_active=1 ORDER BY id LIMIT 1', [$A]);
$serviceA = fetchOne('SELECT * FROM services WHERE tenant_id=? AND is_active=1 ORDER BY id LIMIT 1', [$A]);
actAs($A, (int)$ownerA['id']);
$clientA  = clientUpsert('Isolation Guest A', '5550001111');
$cardA    = giftCardFind('') ?: giftCardIssue(40.00, (int)$clientA['id'], 'Isolation test');
$saleA    = fetchOne("SELECT * FROM pos_sales WHERE tenant_id=? AND status='completed' ORDER BY id DESC LIMIT 1", [$A]);
$countA   = (int)fetchOne('SELECT COUNT(*) n FROM pos_sales WHERE tenant_id=?', [$A])['n'];
$pointsA  = (int)clientFind((int)$clientA['id'])['points'];
foreach (['ownerA' => $ownerA, 'techA' => $techA, 'serviceA' => $serviceA, 'saleA' => $saleA] as $k => $v) {
    if (!$v) { fwrite(STDERR, "Salon 1 needs at least one $k for this test.\n"); exit(2); }
}

// ── Salon B: made for the test ─────────────────────────────────
$B = (int)(fetchOne("SELECT id FROM tenants WHERE slug='isolation-test'")['id'] ?? 0);
if (!$B) {
    query("INSERT INTO tenants (slug, name, plan_id, status)
           VALUES ('isolation-test', 'Isolation Test Salon', (SELECT id FROM plans WHERE code='basic'), 'active')");
    $B = (int)db()->lastInsertId();
}
ensureTenantDefaults($B, 'Isolation Test Salon');
tenantUse($B);
$ownerB = fetchOne("SELECT * FROM admin_users WHERE tenant_id=? AND email='owner@isolation.test'", [$B]);
if (!$ownerB) {
    query("INSERT INTO admin_users (tenant_id, name, email, password_hash, role, pin_hash) VALUES (?,?,?,?,?,?)",
          [$B, 'Bea Owner', 'owner@isolation.test', password_hash('Isolation@123', PASSWORD_BCRYPT), 'owner',
           password_hash('1357', PASSWORD_BCRYPT)]);
    $ownerB = fetchOne('SELECT * FROM admin_users WHERE id=? AND tenant_id=?', [db()->lastInsertId(), $B]);
}
$techB = fetchOne('SELECT * FROM technicians WHERE tenant_id=? ORDER BY id LIMIT 1', [$B]);
if (!$techB) {
    query("INSERT INTO technicians (tenant_id, name) VALUES (?, 'Bao Tech')", [$B]);
    $techB = fetchOne('SELECT * FROM technicians WHERE id=? AND tenant_id=?', [db()->lastInsertId(), $B]);
}
$serviceB = fetchOne('SELECT * FROM services WHERE tenant_id=? ORDER BY id LIMIT 1', [$B]);
if (!$serviceB) {
    query("INSERT INTO services (tenant_id, name, price, duration_minutes, category) VALUES (?, 'Test Manicure', 20.00, 30, 'Manicure')", [$B]);
    $serviceB = fetchOne('SELECT * FROM services WHERE id=? AND tenant_id=?', [db()->lastInsertId(), $B]);
}

echo "Salon A = $A, salon B = $B\n\n";

// ── As salon B, reaching for salon A ───────────────────────────
echo "Salon B looking at salon A:\n";
actAs($B, (int)$ownerB['id']);
check('settings are B\'s own',                 (settings()['business_name'] ?? '') === 'Isolation Test Salon');
check('till settings are B\'s own',            (posSettings()['receipt_header'] ?? '') === 'Isolation Test Salon');
check('A\'s client id finds nothing',          clientFind((int)$clientA['id']) === null);
$sharedPhone = clientUpsert('Isolation Guest B', '5550001111');
check('same phone becomes B\'s own client',    (int)$sharedPhone['id'] !== (int)$clientA['id']
                                               && (int)$sharedPhone['tenant_id'] === $B);
check('A\'s gift card code finds nothing',     giftCardFind($cardA['code']) === null);
check('A\'s service is not B\'s',              !tenantOwns('services', (int)$serviceA['id']));
check('A\'s technician is not B\'s',           !tenantOwns('technicians', (int)$techA['id']));
check('turns board lists only B\'s staff',     !in_array((int)$techA['id'], array_map('intval', array_column(turnsBoard(), 'id')), true));
check('PIN list shows only B\'s people',       !array_diff(array_map('intval', array_column(pinUsers(), 'id')),
                                                   array_map('intval', array_column(fetchAll('SELECT id FROM admin_users WHERE tenant_id=?', [$B]), 'id'))));
check('A\'s manager PIN approves nothing',     managerByPin('9731') === null);
check('cannot void A\'s sale',                 throws(function () use ($saleA) { voidSale((int)$saleA['id']); }));
check('cannot refund A\'s sale',               throws(function () use ($saleA) {
    $item = unscoped(function () use ($saleA) { return fetchOne('SELECT id FROM pos_sale_items WHERE sale_id=? LIMIT 1', [$saleA['id']]); });
    refundSale((int)$saleA['id'], [(int)($item['id'] ?? 0) => 1], 'cash');
}));
check('cannot spend A\'s gift card',           throws(function () use ($cardA) { giftCardRedeem((int)$cardA['id'], 1.00); }));
check('cannot stamp A\'s client',              throws(function () use ($clientA) { stampAward((int)$clientA['id']); }));
check('cannot give points to A\'s client',     throws(function () use ($clientA) { pointsLog((int)$clientA['id'], 'adjust', 500); }));

$checkinId = checkInGuest(['guest_name' => 'Walk-in B', 'service_id' => $serviceA['id'],
                           'requested_tech_id' => $techA['id']]);
$checkin   = fetchOne('SELECT * FROM pos_checkins WHERE id=? AND tenant_id=?', [$checkinId, $B]);
check('check-in drops A\'s service and tech',  $checkin && $checkin['service_id'] === null && $checkin['requested_tech_id'] === null);

// A sale in B numbers from B's own counter.
cartReset();
cartAdd('service', (int)$serviceB['id'], $serviceB['name'], (float)$serviceB['price'], 1, (int)$techB['id']);
$saleIdB = checkout([['method' => 'cash', 'amount' => cartDue(), 'reference' => '']]);
$saleB   = fetchOne('SELECT * FROM pos_sales WHERE id=? AND tenant_id=?', [$saleIdB, $B]);
check('B\'s sale is stamped with B',           $saleB && (int)$saleB['tenant_id'] === $B);
check('B\'s items are stamped with B',         !unscoped(function () use ($saleIdB, $B) {
    return fetchOne('SELECT 1 x FROM pos_sale_items WHERE sale_id=? AND tenant_id<>?', [$saleIdB, $B]);
}));

check('an unscoped query is refused',          throws(function () { fetchAll('SELECT id FROM pos_sales'); }));
check('unscoped() lets a deliberate one run',  !throws(function () { unscoped(function () { return fetchAll('SELECT id FROM pos_sales LIMIT 1'); }); }));

$preview = purgeAllPreview(['clients' => true, 'giftcards' => true]);
purgeAll(['clients' => true, 'giftcards' => true, 'bookings' => true]);
check('B\'s reset cleared B\'s sales',         (int)fetchOne('SELECT COUNT(*) n FROM pos_sales WHERE tenant_id=?', [$B])['n'] === 0);

// ── Salon A afterwards: untouched ──────────────────────────────
echo "\nSalon A after all that:\n";
actAs($A, (int)$managerA['id']);
check('A still has every sale',                (int)fetchOne('SELECT COUNT(*) n FROM pos_sales WHERE tenant_id=?', [$A])['n'] === $countA);
check('A\'s sale is still completed',          (fetchOne('SELECT status FROM pos_sales WHERE id=? AND tenant_id=?', [$saleA['id'], $A])['status'] ?? '') === 'completed');
check('A\'s gift card balance is unchanged',   (float)giftCardFind($cardA['code'])['balance'] === (float)$cardA['balance']);
check('A\'s client points are unchanged',      (int)clientFind((int)$clientA['id'])['points'] === $pointsA);
check('A cannot see B\'s client',              clientFind((int)$sharedPhone['id']) === null);
check('A\'s queue has no B walk-in',           !in_array($checkinId, array_map('intval', array_column(waitingList(), 'id')), true));
check('A\'s manager PIN works in A',           (managerByPin('9731')['id'] ?? 0) == $managerA['id']);

echo "\n" . ($failures ? "$failures check(s) FAILED\n" : "All checks passed\n");
exit($failures ? 1 : 0);
