<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Only admin can update
requireLogin();

$raw = json_decode(file_get_contents('php://input'), true);

if (empty($raw['appointment_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing appointment_id']);
    exit;
}

$appointmentId = (int)$raw['appointment_id'];
$newTime = $raw['new_time'] ?? null;
$technicianId = isset($raw['technician_id']) && $raw['technician_id'] ? (int)$raw['technician_id'] : null;
$status = $raw['status'] ?? null;

try {
    // Get current appointment
    $appt = fetchOne('SELECT * FROM appointments WHERE id=?', [$appointmentId]);
    if (!$appt) {
        echo json_encode(['success' => false, 'error' => 'Appointment not found']);
        exit;
    }

    $updates = [];
    $params = [];

    if ($newTime) {
        // Validate time format
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
        $startDateTime = new DateTime($appt['appointment_date'] . ' ' . $newTime);
        $endDateTime = clone $startDateTime;
        $endDateTime->add(new DateInterval("PT{$service['duration_minutes']}M"));
        $endTime = $endDateTime->format('H:i:s');

        // Check if new slot is available
        $conflict = fetchOne(
            'SELECT id FROM appointments WHERE appointment_date = ? AND start_time = ? AND id != ? AND status IN ("pending", "confirmed")',
            [$appt['appointment_date'], $newTime, $appointmentId]
        );

        if ($conflict) {
            echo json_encode(['success' => false, 'error' => 'This time slot is no longer available']);
            exit;
        }

        $updates[] = 'start_time = ?';
        $params[] = $newTime;
        $updates[] = 'end_time = ?';
        $params[] = $endTime;
    }

    if ($technicianId !== null || isset($raw['technician_id'])) {
        $updates[] = 'technician_id = ?';
        $params[] = $technicianId;
    }

    if ($status) {
        if (!in_array($status, ['pending', 'confirmed', 'cancelled', 'completed'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid status']);
            exit;
        }
        $updates[] = 'status = ?';
        $params[] = $status;
    }

    if (empty($updates)) {
        echo json_encode(['success' => false, 'error' => 'No updates provided']);
        exit;
    }

    $params[] = $appointmentId;
    $updateStr = implode(', ', $updates);

    query("UPDATE appointments SET {$updateStr} WHERE id=?", $params);

    echo json_encode([
        'success' => true,
        'message' => 'Appointment updated'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Update failed: ' . $e->getMessage()]);
}
?>
