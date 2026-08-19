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
$date = trim((string) ($input['appointment_date'] ?? ''));
$time = trim((string) ($input['appointment_time'] ?? ''));
$staffId = (int) ($input['staff_id'] ?? 0);

if (!$bookingId) {
    json_response(['ok' => false, 'error' => 'Invalid request.'], 422);
}

$dateObj = DateTime::createFromFormat('Y-m-d', $date);
if (!$dateObj) {
    json_response(['ok' => false, 'error' => 'Please choose a valid date.'], 422);
}
$timeObj = DateTime::createFromFormat('H:i', $time);
if (!$timeObj) {
    json_response(['ok' => false, 'error' => 'Please choose a valid time.'], 422);
}

$pdo = get_db();

if ($staffId > 0) {
    $staffStmt = $pdo->prepare('SELECT id FROM staff WHERE id = :id');
    $staffStmt->execute(['id' => $staffId]);
    if (!$staffStmt->fetch()) {
        json_response(['ok' => false, 'error' => 'Please choose a valid technician.'], 422);
    }
} else {
    $staffId = null;
}

$stmt = $pdo->prepare('UPDATE bookings SET appointment_date = :date, appointment_time = :time, staff_id = :staff_id WHERE id = :id');
$stmt->execute([
    'date' => $dateObj->format('Y-m-d'),
    'time' => $timeObj->format('H:i:s'),
    'staff_id' => $staffId,
    'id' => $bookingId,
]);

json_response(['ok' => true]);
