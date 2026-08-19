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

$booking_ids = $input['booking_ids'] ?? [];

if (empty($booking_ids) || !is_array($booking_ids)) {
    json_response(['ok' => false, 'error' => 'No bookings selected']);
}

// Validate all IDs are integers
$booking_ids = array_map('intval', $booking_ids);

$pdo = get_db();

// Delete bookings
$placeholders = implode(',', array_fill(0, count($booking_ids), '?'));
$stmt = $pdo->prepare("DELETE FROM bookings WHERE id IN ($placeholders)");
$stmt->execute($booking_ids);

$deleted = $stmt->rowCount();

json_response(['ok' => true, 'deleted' => $deleted]);
?>
