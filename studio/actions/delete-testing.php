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

$pdo = get_db();

// Delete bookings that contain "test", "demo", "sample" in client name or notes
$testPatterns = ['test', 'demo', 'sample', 'testing'];
$placeholders = implode(',', array_fill(0, count($testPatterns), '?'));

$stmt = $pdo->prepare("
    DELETE FROM bookings
    WHERE LOWER(full_name) LIKE ?
       OR LOWER(notes) LIKE ?
       OR LOWER(email) LIKE ?
");

$deleted = 0;
foreach ($testPatterns as $pattern) {
    $searchTerm = '%' . $pattern . '%';
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $deleted += $stmt->rowCount();
}

json_response(['ok' => true, 'deleted' => $deleted]);
?>
