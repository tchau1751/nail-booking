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

$amount = (float) ($input['amount'] ?? 0);
$recipientName = trim((string) ($input['recipient_name'] ?? ''));
$purchaserName = trim((string) ($input['purchaser_name'] ?? ''));
$purchaserPhone = trim((string) ($input['purchaser_phone'] ?? ''));
$purchaserEmail = trim((string) ($input['purchaser_email'] ?? ''));
$notes = trim((string) ($input['notes'] ?? ''));

if ($amount <= 0 || $amount > 10000) {
    json_response(['ok' => false, 'error' => 'Please enter a valid amount.'], 422);
}
if ($purchaserEmail !== '' && !filter_var($purchaserEmail, FILTER_VALIDATE_EMAIL)) {
    json_response(['ok' => false, 'error' => 'Please enter a valid purchaser email.'], 422);
}

$pdo = get_db();
$code = generate_gift_card_code($pdo);

$pdo->prepare(
    'INSERT INTO gift_cards (code, initial_amount, balance, recipient_name, purchaser_name, purchaser_phone, purchaser_email, notes)
     VALUES (:code, :amount, :balance, :recipient, :purchaser, :phone, :email, :notes)'
)->execute([
    'code' => $code,
    'amount' => $amount,
    'balance' => $amount,
    'recipient' => $recipientName !== '' ? $recipientName : null,
    'purchaser' => $purchaserName !== '' ? $purchaserName : null,
    'phone' => $purchaserPhone !== '' ? $purchaserPhone : null,
    'email' => $purchaserEmail !== '' ? $purchaserEmail : null,
    'notes' => $notes !== '' ? $notes : null,
]);

json_response(['ok' => true, 'code' => $code]);
