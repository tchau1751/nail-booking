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

if (!$client_id) {
    json_response(['ok' => false, 'error' => 'Invalid client ID']);
}

$pdo = get_db();

try {
    // Delete client
    $stmt = $pdo->prepare('DELETE FROM clients WHERE id = ?');
    $stmt->execute([$client_id]);

    json_response(['ok' => true, 'message' => 'Client deleted successfully']);
} catch (Exception $e) {
    json_response(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
