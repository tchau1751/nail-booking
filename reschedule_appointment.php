<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['id'])) {
  echo json_encode(['error' => 'Missing appointment ID']);
  exit;
}

$pdo = get_db();

try {
  $stmt = $pdo->prepare("
    UPDATE bookings
    SET appointment_date = :date,
        appointment_time = :time,
        staff_id = :staff_id,
        updated_at = NOW()
    WHERE id = :id
  ");

  $stmt->execute([
    ':id' => $data['id'],
    ':date' => $data['appointment_date'],
    ':time' => $data['appointment_time'],
    ':staff_id' => $data['staff_id'] === 'unassigned' ? null : $data['staff_id']
  ]);

  if ($stmt->rowCount() > 0) {
    echo json_encode(['success' => true, 'message' => 'Appointment rescheduled']);
  } else {
    echo json_encode(['error' => 'Appointment not found']);
  }
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
?>
