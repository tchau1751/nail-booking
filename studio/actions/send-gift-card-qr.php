<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/notify.php';

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

$code = trim((string) ($input['code'] ?? ''));
$channel = (string) ($input['channel'] ?? '');
$destination = trim((string) ($input['destination'] ?? ''));

if ($code === '' || !in_array($channel, ['sms', 'email'], true) || $destination === '') {
    json_response(['ok' => false, 'error' => 'Invalid request.'], 422);
}

if ($channel === 'email' && !filter_var($destination, FILTER_VALIDATE_EMAIL)) {
    json_response(['ok' => false, 'error' => 'Please enter a valid email address.'], 422);
}

$pdo = get_db();
$stmt = $pdo->prepare("SELECT * FROM gift_cards WHERE code = :code");
$stmt->execute(['code' => $code]);
$card = $stmt->fetch();

if (!$card) {
    json_response(['ok' => false, 'error' => 'Gift card not found.'], 404);
}

$status = send_gift_card_qr((int) $card['id'], $channel, $destination, $card['code'], (float) $card['balance']);

if ($status === 'skipped') {
    $reason = $channel === 'sms'
        ? 'Twilio is not configured — SMS could not be sent.'
        : 'Email sending is not configured yet (RESEND_API_KEY missing) — email could not be sent.';
    json_response(['ok' => false, 'error' => $reason], 422);
}

if ($status === 'failed') {
    json_response(['ok' => false, 'error' => 'Could not send — please try again or share the code directly.'], 502);
}

json_response(['ok' => true, 'channel' => $channel, 'destination' => $destination]);
