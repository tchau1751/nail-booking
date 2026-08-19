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

$message = trim((string) ($input['message'] ?? ''));
$clientIds = array_filter(array_map('intval', (array) ($input['client_ids'] ?? [])));

if ($message === '') {
    json_response(['ok' => false, 'error' => 'Please write a message.'], 422);
}
if (mb_strlen($message) > 480) {
    json_response(['ok' => false, 'error' => 'Message is too long (max 480 characters).'], 422);
}
if (empty($clientIds)) {
    json_response(['ok' => false, 'error' => 'Please select at least one recipient.'], 422);
}

if (stripos($message, 'stop') === false) {
    $message .= ' Reply STOP to opt out.';
}

$pdo = get_db();
$placeholders = implode(',', array_fill(0, count($clientIds), '?'));
$stmt = $pdo->prepare("SELECT id, phone FROM clients WHERE id IN ($placeholders) AND phone IS NOT NULL AND phone <> ''");
$stmt->execute(array_values($clientIds));
$clients = $stmt->fetchAll();

$sent = 0;
$failed = 0;
$skipped = 0;

foreach ($clients as $c) {
    $status = send_client_sms((int) $c['id'], $c['phone'], $message, 'promotion');
    if ($status === 'sent') {
        $sent++;
    } elseif ($status === 'failed') {
        $failed++;
    } else {
        $skipped++;
    }
}

json_response(['ok' => true, 'sent' => $sent, 'failed' => $failed, 'skipped' => $skipped]);
