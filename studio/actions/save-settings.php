<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!current_admin()) {
    json_response(['ok' => false, 'error' => 'Not authenticated.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

if (!admin_csrf_verify($input['csrf_token'] ?? null)) {
    json_response(['ok' => false, 'error' => 'Session expired — please refresh.'], 419);
}

$fields = [
    'business_name' => trim((string) ($input['business_name'] ?? '')),
    'business_email' => trim((string) ($input['business_email'] ?? '')),
    'business_phone' => trim((string) ($input['business_phone'] ?? '')),
    'business_address' => trim((string) ($input['business_address'] ?? '')),
    'hours_note' => trim((string) ($input['hours_note'] ?? '')),
    'instagram_url' => trim((string) ($input['instagram_url'] ?? '')),
    'facebook_url' => trim((string) ($input['facebook_url'] ?? '')),
    'booking_notice' => trim((string) ($input['booking_notice'] ?? '')),
    'kiosk_mode' => ($input['kiosk_mode'] ?? 'checkin') === 'display' ? 'display' : 'checkin',
    'kiosk_promo_message' => trim((string) ($input['kiosk_promo_message'] ?? '')),
    'kiosk_promo_image_url' => trim((string) ($input['kiosk_promo_image_url'] ?? '')),
    'kiosk_points_lookup_enabled' => empty($input['kiosk_points_lookup_enabled']) ? 0 : 1,
];

if ($fields['business_name'] === '') {
    json_response(['ok' => false, 'error' => 'Studio name is required.'], 422);
}
if ($fields['business_email'] !== '' && !filter_var($fields['business_email'], FILTER_VALIDATE_EMAIL)) {
    json_response(['ok' => false, 'error' => 'Please enter a valid email address.'], 422);
}

$pdo = get_db();
$existing = $pdo->query('SELECT id FROM business_settings ORDER BY id ASC LIMIT 1')->fetch();

if ($existing) {
    $stmt = $pdo->prepare(
        'UPDATE business_settings SET business_name=:business_name, business_email=:business_email,
         business_phone=:business_phone, business_address=:business_address, hours_note=:hours_note,
         instagram_url=:instagram_url, facebook_url=:facebook_url, booking_notice=:booking_notice,
         kiosk_mode=:kiosk_mode, kiosk_promo_message=:kiosk_promo_message, kiosk_promo_image_url=:kiosk_promo_image_url,
         kiosk_points_lookup_enabled=:kiosk_points_lookup_enabled
         WHERE id=:id'
    );
    $fields['id'] = $existing['id'];
    $stmt->execute($fields);
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO business_settings (business_name, business_email, business_phone, business_address, hours_note, instagram_url, facebook_url, booking_notice, kiosk_mode, kiosk_promo_message, kiosk_promo_image_url, kiosk_points_lookup_enabled)
         VALUES (:business_name, :business_email, :business_phone, :business_address, :hours_note, :instagram_url, :facebook_url, :booking_notice, :kiosk_mode, :kiosk_promo_message, :kiosk_promo_image_url, :kiosk_points_lookup_enabled)'
    );
    $stmt->execute($fields);
}

json_response(['ok' => true]);
