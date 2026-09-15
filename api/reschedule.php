<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Only admin can reschedule
requireLogin();

$raw = json_decode(file_get_contents('php://input'), true);

if (empty($raw['appointment_id']) || empty($raw['new_date']) || empty($raw['new_time'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$appointmentId = (int)$raw['appointment_id'];
$newDate = $raw['new_date'];
$newTime = $raw['new_time'];
$technicianId = isset($raw['technician_id']) && $raw['technician_id'] ? (int)$raw['technician_id'] : null;

try {
    // Get current appointment
    $appt = fetchOne('SELECT * FROM appointments WHERE id=?', [$appointmentId]);
    if (!$appt) {
        echo json_encode(['success' => false, 'error' => 'Appointment not found']);
        exit;
    }

    // Validate date and time format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
        echo json_encode(['success' => false, 'error' => 'Invalid date format']);
        exit;
    }

    if (!preg_match('/^\d{2}:\d{2}$/', $newTime)) {
        echo json_encode(['success' => false, 'error' => 'Invalid time format']);
        exit;
    }

    // Get service info for duration
    $service = fetchOne('SELECT duration_minutes FROM services WHERE id=?', [$appt['service_id']]);
    if (!$service) {
        echo json_encode(['success' => false, 'error' => 'Service not found']);
        exit;
    }

    // Calculate end time
    $startDateTime = new DateTime($newDate . ' ' . $newTime);
    $endDateTime = clone $startDateTime;
    $endDateTime->add(new DateInterval("PT{$service['duration_minutes']}M"));
    $endTime = $endDateTime->format('H:i:s');

    // Check if new slot is available (excluding current appointment)
    $conflict = fetchOne(
        'SELECT id FROM appointments WHERE appointment_date = ? AND start_time = ? AND id != ? AND status IN ("pending", "confirmed")',
        [$newDate, $newTime, $appointmentId]
    );

    if ($conflict) {
        echo json_encode(['success' => false, 'error' => 'This time slot is no longer available']);
        exit;
    }

    // Update appointment
    $updateFields = ['appointment_date = ?', 'start_time = ?', 'end_time = ?'];
    $params = [$newDate, $newTime, $endTime];

    if ($technicianId !== null) {
        $updateFields[] = 'technician_id = ?';
        $params[] = $technicianId;
    }

    $params[] = $appointmentId;
    $updateStr = implode(', ', $updateFields);

    query("UPDATE appointments SET {$updateStr} WHERE id=?", $params);

    echo json_encode([
        'success' => true,
        'message' => 'Appointment rescheduled'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Reschedule failed: ' . $e->getMessage()]);
}
?>
