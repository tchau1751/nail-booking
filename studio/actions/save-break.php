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

$staffId = (int) ($input['staff_id'] ?? 0);
$date = trim((string) ($input['break_date'] ?? ''));
$start = trim((string) ($input['start_time'] ?? ''));
$end = trim((string) ($input['end_time'] ?? ''));
$label = trim((string) ($input['label'] ?? 'Break'));

if ($staffId <= 0) {
    json_response(['ok' => false, 'error' => 'Please choose a staff member.'], 422);
}
if (!DateTime::createFromFormat('Y-m-d', $date)) {
    json_response(['ok' => false, 'error' => 'Please choose a valid date.'], 422);
}
$startObj = DateTime::createFromFormat('H:i', $start);
$endObj = DateTime::createFromFormat('H:i', $end);
if (!$startObj || !$endObj || $endObj <= $startObj) {
    json_response(['ok' => false, 'error' => 'End time must be after start time.'], 422);
}
if ($label === '') {
    $label = 'Break';
}

$pdo = get_db();
$stmt = $pdo->prepare(
    'INSERT INTO staff_breaks (staff_id, break_date, start_time, end_time, label) VALUES (:staff_id, :date, :start, :end, :label)'
);
$stmt->execute([
    'staff_id' => $staffId,
    'date' => $date,
    'start' => $startObj->format('H:i:s'),
    'end' => $endObj->format('H:i:s'),
    'label' => $label,
]);

json_response(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
