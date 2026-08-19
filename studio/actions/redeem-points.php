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

$clientId = (int) ($input['client_id'] ?? 0);
$amount = (float) ($input['amount'] ?? 0);
$note = trim((string) ($input['note'] ?? ''));

if (!$clientId) {
    json_response(['ok' => false, 'error' => 'Invalid request.'], 422);
}
if ($amount <= 0) {
    json_response(['ok' => false, 'error' => 'Please enter a valid amount.'], 422);
}

$pdo = get_db();
$result = redeem_reward_points($pdo, $clientId, $amount, null, $note);

if (!$result['ok']) {
    json_response(['ok' => false, 'error' => $result['error']], 422);
}

json_response([
    'ok' => true,
    'points_redeemed' => $result['points_redeemed'],
    'amount_value' => $result['amount_value'],
    'remaining_points' => $result['remaining_points'],
]);
