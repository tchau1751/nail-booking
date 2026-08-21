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

$client_id = (int)($input['client_id'] ?? 0);
$action = trim($input['action'] ?? '');

if (!$client_id) {
    json_response(['ok' => false, 'error' => 'Invalid client ID: ' . $client_id]);
}

if (!in_array($action, ['add', 'remove', 'reset'], true)) {
    json_response(['ok' => false, 'error' => 'Invalid action: ' . $action]);
}

$pdo = get_db();

// Get current stamp count
$stmt = $pdo->prepare('SELECT COALESCE(stamp_count, 0) as stamps FROM clients WHERE id = ?');
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    json_response(['ok' => false, 'error' => 'Client not found']);
}

$currentStamps = (int)$client['stamps'];
$newStamps = $currentStamps;

if ($action === 'add') {
    $newStamps = min($currentStamps + 1, 10); // Cap at 10
    // If reached 10 stamps, reset to 0 after update
    if ($newStamps === 10) {
        $newStamps = 0; // Auto-reset on 10th stamp
    }
} elseif ($action === 'remove') {
    $newStamps = max($currentStamps - 1, 0); // Min at 0
} elseif ($action === 'reset') {
    $newStamps = 0;
}

// Update stamp count
$stmt = $pdo->prepare('UPDATE clients SET stamp_count = ? WHERE id = ?');
$stmt->execute([$newStamps, $client_id]);

json_response(['ok' => true, 'stamps' => $newStamps]);
?>
