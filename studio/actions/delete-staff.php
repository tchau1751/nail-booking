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

$id = (int) ($input['id'] ?? 0);
if (!$id) {
    json_response(['ok' => false, 'error' => 'Invalid staff member.'], 422);
}

$pdo = get_db();

$stmt = $pdo->prepare('SELECT COUNT(*) c FROM bookings WHERE staff_id = :id');
$stmt->execute(['id' => $id]);
$bookingCount = (int) $stmt->fetch()['c'];

if ($bookingCount > 0) {
    // Preserve booking/calendar history — deactivate instead of deleting a staff
    // member who already has appointments on the books.
    $pdo->prepare('UPDATE staff SET is_active = 0 WHERE id = :id')->execute(['id' => $id]);
    json_response(['ok' => true, 'deactivated' => true]);
}

$pdo->prepare('DELETE FROM staff WHERE id = :id')->execute(['id' => $id]);
json_response(['ok' => true, 'deactivated' => false]);
