<?php
// ============================================================
//  Stations: the tablets and PCs a salon has registered.
//
//  A salon runs several screens at once — POS #1 and POS #2 at the
//  counter, the front desk iPad, the kiosk by the door. Registering
//  one gives that browser a long random token in a cookie; only a
//  hash of it is stored. From then on the device knows its salon
//  before anyone signs in, every sale records which station rang it
//  up, and a manager can switch off a lost tablet so it is no use to
//  whoever has it.
// ============================================================
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/plans.php';

const DEVICE_KINDS = [
    'pos'        => 'POS station',
    'front_desk' => 'Front desk',
    'kiosk'      => 'Check-in kiosk',
    'display'    => 'Customer display',
    'manager'    => 'Manager PC',
];

/** This browser, if it is an active device of the signed-in salon. */
function deviceHere(): ?array {
    $d = deviceFromCookie();
    return ($d && (int)$d['is_active'] === 1 && (int)$d['tenant_id'] === tenantId()) ? $d : null;
}

/** This salon's devices, switched-off ones last. */
function devicesList(): array {
    return fetchAll('SELECT d.*, u.name AS last_user FROM pos_devices d
                     LEFT JOIN admin_users u ON u.id = d.last_user_id
                     WHERE d.tenant_id=?
                     ORDER BY d.is_active DESC, d.name', [tenantId()]);
}

function deviceFind(int $id): ?array {
    return fetchOne('SELECT * FROM pos_devices WHERE id=? AND tenant_id=?', [$id, tenantId()]);
}

/** Null while the salon's plan has room for another active device, otherwise why not. */
function deviceLimitReached(): ?string {
    return planRoomFor('devices');
}

function deviceCookieOptions(int $expires): array {
    return [
        'expires'  => $expires,
        'path'     => '/' . SUBFOLDER . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ];
}

/**
 * Make this browser one of the salon's devices. The token lives only in this
 * browser's cookie; the database keeps its hash, so a copy of the database
 * cannot be used to pose as a salon's till.
 */
function deviceRegister(string $name, string $kind): array {
    $name = mb_substr(trim($name), 0, 60);
    if ($name === '') throw new RuntimeException('Give this device a name everyone will recognise, like "POS #1".');
    if (!isset(DEVICE_KINDS[$kind])) $kind = 'pos';
    if ($here = deviceHere()) throw new RuntimeException('This browser is already registered as ' . $here['name'] . '.');
    if ($why = deviceLimitReached()) throw new RuntimeException($why);

    $token = bin2hex(random_bytes(32));
    query('INSERT INTO pos_devices (tenant_id, name, kind, token_hash, is_active, created_by, last_seen_at)
           VALUES (?,?,?,?,1,?,NOW())', [tenantId(), $name, $kind, hash('sha256', $token), currentAdmin()['id'] ?? null]);
    $id = (int)db()->lastInsertId();
    setcookie(DEVICE_COOKIE, $token, deviceCookieOptions(time() + 10 * 365 * 86400));
    $_COOKIE[DEVICE_COOKIE] = $token;
    startSecureSession();
    $_SESSION['device_id'] = $id;
    return deviceFind($id);
}

/** Take the registration off this browser only. The device stays on the list. */
function deviceForgetHere(): void {
    setcookie(DEVICE_COOKIE, '', deviceCookieOptions(time() - 3600));
    unset($_COOKIE[DEVICE_COOKIE]);
    startSecureSession();
    unset($_SESSION['device_id']);
}

function deviceRename(int $id, string $name): void {
    $name = mb_substr(trim($name), 0, 60);
    if ($name === '') throw new RuntimeException('A device needs a name.');
    if (!deviceFind($id)) throw new RuntimeException('Device not found.');
    query('UPDATE pos_devices SET name=? WHERE id=? AND tenant_id=?', [$name, $id, tenantId()]);
}

/**
 * Switch a device off or back on. Off takes effect on its next request: anyone
 * signed in on it is signed out, and its PIN pad stops working.
 */
function deviceSetActive(int $id, bool $on): void {
    $d = deviceFind($id);
    if (!$d) throw new RuntimeException('Device not found.');
    if ($on && !(int)$d['is_active'] && ($why = deviceLimitReached())) throw new RuntimeException($why);
    query('UPDATE pos_devices SET is_active=?, deactivated_at=' . ($on ? 'NULL' : 'NOW()') . ' WHERE id=? AND tenant_id=?',
          [$on ? 1 : 0, $id, tenantId()]);
}

/** Does this salon use registered devices at all? Once it does, a PIN only works on one. */
function tenantUsesDevices(): bool {
    return (bool)fetchOne('SELECT 1 x FROM pos_devices WHERE tenant_id=? AND is_active=1 LIMIT 1', [tenantId()]);
}
