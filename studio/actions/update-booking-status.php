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

$bookingId = (int) ($input['booking_id'] ?? 0);
$status = (string) ($input['status'] ?? '');
$allowed = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];

if (!$bookingId || !in_array($status, $allowed, true)) {
    json_response(['ok' => false, 'error' => 'Invalid request.'], 422);
}

$pdo = get_db();
$stmt = $pdo->prepare('UPDATE bookings SET status = :status WHERE id = :id');
$stmt->execute(['status' => $status, 'id' => $bookingId]);

json_response(['ok' => true]);
