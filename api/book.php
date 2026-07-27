<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/sms.php';
require_once __DIR__ . '/../includes/slots.php';

$raw = json_decode(file_get_contents('php://input'), true);

// ── Validate required fields ──────────────────────────────────
$required = ['full_name','email','phone','service_id','appointment_date','start_time'];
foreach ($required as $f) {
    if (empty($raw[$f])) {
        echo json_encode(['success'=>false,'error'=>"Missing: {$f}"]);
        exit;
    }
}

$name      = trim($raw['full_name']);
$email     = trim($raw['email']);
$phone     = trim($raw['phone']);
$serviceId = (int)$raw['service_id'];
$date      = $raw['appointment_date'];
$start     = $raw['start_time'];
$techId    = isset($raw['technician_id']) && $raw['technician_id'] !== '' ? (int)$raw['technician_id'] : null;
$notes     = trim($raw['notes'] ?? '');

// ── Re-validate slot is still available ──────────────────────
$slots = getAvailableSlots($date, $serviceId, $techId);
$valid = false;
$endTime = '';
foreach ($slots as $slot) {
    if ($slot['start'] === $start) {
        $valid   = true;
        $endTime = $slot['end'];
        break;
    }
}

if (!$valid) {
    echo json_encode(['success'=>false,'error'=>'This time slot is no longer available. Please choose another.']);
    exit;
}

// ── Insert appointment ────────────────────────────────────────
try {
    query(
        'INSERT INTO appointments (full_name,email,phone,service_id,technician_id,appointment_date,start_time,end_time,status,notes)
         VALUES (?,?,?,?,?,?,?,?,?,?)',
        [$name,$email,$phone,$serviceId,$techId,$date,$start,$endTime,'pending',$notes]
    );
    $apptId = (int)db()->lastInsertId();

    // ── Send confirmation SMS ─────────────────────────────────
    $appt    = fetchOne('SELECT * FROM appointments WHERE id=?', [$apptId]);
    $service = fetchOne('SELECT * FROM services WHERE id=?',     [$serviceId]);
    $s       = settings();
    $bizName = $s['business_name'] ?? 'Diamond Nail & Spa';

    $smsResult = sendSMS($phone, smsConfirmation($appt, $service, $bizName), $apptId, 'confirmation');
    if ($smsResult['success']) {
        query('UPDATE appointments SET sms_confirmation_sent=1 WHERE id=?', [$apptId]);
    }

    echo json_encode([
        'success'       => true,
        'appointment_id'=> $apptId,
        'message'       => 'Appointment booked!',
        'sms_sent'      => $smsResult['success'],
        'details'       => [
            'service'   => $service['name'],
            'date'      => date('l, F j Y', strtotime($date)),
            'time'      => date('g:i A', strtotime("{$date} {$start}")),
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Booking failed. Please try again.']);
}
