<?php
// ============================================================
//  The day calendar's edit box: a booking's time (same day),
//  technician or status. Quiet — no text to the guest; the booking
//  admin's status buttons are the ones that send one.
//  No hyphen in the name, matching the file on the server.
// ============================================================
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bookings.php';

requireRoleJson('front_desk');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Use POST']);
    exit;
}

$raw = json_decode(file_get_contents('php://input'), true);
$raw = is_array($raw) ? $raw : [];

$id = filter_var($raw['appointment_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Missing appointment_id']);
    exit;
}

$changes = [];
if (!empty($raw['new_time']))     $changes['time'] = $raw['new_time'];
if (isset($raw['technician_id'])) $changes['technician_id'] = $raw['technician_id'] ?: null;
if (!empty($raw['status']))       $changes['status'] = $raw['status'];

try {
    bookingUpdate($id, $changes);
    echo json_encode(['success' => true, 'message' => 'Appointment updated']);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('api/updateappointment.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Update failed. Please try again.']);
}
