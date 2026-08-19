<?php
require_once __DIR__ . '/../includes/functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$phone = trim((string) ($_GET['phone'] ?? ''));
$digits = preg_replace('/\D+/', '', $phone);

if (mb_strlen($digits) < 7) {
    json_response(['ok' => false, 'error' => 'Please enter a valid phone number.'], 422);
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT id, full_name, reward_points FROM clients WHERE phone <> "" AND phone = :phone LIMIT 1');
$stmt->execute(['phone' => $phone]);
$client = $stmt->fetch();

if (!$client) {
    // Phone numbers may be stored with slightly different punctuation — fall back to a digits-only match.
    $stmt = $pdo->query('SELECT id, full_name, phone, reward_points FROM clients');
    foreach ($stmt->fetchAll() as $row) {
        if (preg_replace('/\D+/', '', $row['phone']) === $digits) {
            $client = $row;
            break;
        }
    }
}

json_response([
    'ok' => true,
    'found' => (bool) $client,
    'full_name' => $client['full_name'] ?? null,
    'reward_points' => $client ? (int) $client['reward_points'] : 0,
]);
