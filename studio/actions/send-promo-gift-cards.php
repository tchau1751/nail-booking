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

$amount = (float) ($input['amount'] ?? 0);
$clientIds = array_filter(array_map('intval', (array) ($input['client_ids'] ?? [])));

if ($amount <= 0 || $amount > 500) {
    json_response(['ok' => false, 'error' => 'Please enter a valid amount (up to $500 per card).'], 422);
}
if (empty($clientIds)) {
    json_response(['ok' => false, 'error' => 'Please select at least one recipient.'], 422);
}

$pdo = get_db();
$placeholders = implode(',', array_fill(0, count($clientIds), '?'));
$stmt = $pdo->prepare("SELECT id, full_name, phone FROM clients WHERE id IN ($placeholders) AND phone IS NOT NULL AND phone <> ''");
$stmt->execute(array_values($clientIds));
$clients = $stmt->fetchAll();

$sent = 0;
$failed = 0;
$skipped = 0;

foreach ($clients as $c) {
    $code = generate_gift_card_code($pdo);
    $pdo->prepare(
        'INSERT INTO gift_cards (code, initial_amount, balance, recipient_name, purchaser_phone, notes)
         VALUES (:code, :amount, :balance, :recipient, :phone, :notes)'
    )->execute([
        'code' => $code,
        'amount' => $amount,
        'balance' => $amount,
        'recipient' => $c['full_name'],
        'phone' => $c['phone'],
        'notes' => 'Issued via Promotions bulk gift card campaign',
    ]);
    $cardId = (int) $pdo->lastInsertId();

    $status = send_gift_card_qr($cardId, 'sms', $c['phone'], $code, $amount);
    if ($status === 'sent') {
        $sent++;
    } elseif ($status === 'failed') {
        $failed++;
    } else {
        $skipped++;
    }
}

json_response(['ok' => true, 'sent' => $sent, 'failed' => $failed, 'skipped' => $skipped]);
