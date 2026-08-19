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

$booking_id = (int)($input['booking_id'] ?? 0);

if (!$booking_id) {
    json_response(['ok' => false, 'error' => 'Missing booking ID']);
}

$pdo = get_db();

// Delete the booking
$stmt = $pdo->prepare('DELETE FROM bookings WHERE id = ?');
$stmt->execute([$booking_id]);

if ($stmt->rowCount() > 0) {
    json_response(['ok' => true, 'message' => 'Booking deleted']);
} else {
    json_response(['ok' => false, 'error' => 'Booking not found']);
}
?>
