<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/slots.php';

try { publicSalon(); }
catch (TenantMissing $e) { echo json_encode(['success'=>false,'error'=>$e->getMessage()]); exit; }

$date      = $_GET['date']       ?? '';
$serviceId = (int)($_GET['service_id'] ?? 0);
$techId    = ($_GET['technician_id'] ?? '') !== '' ? (int)$_GET['technician_id'] : null;

if (!$date || !$serviceId) {
    echo json_encode(['success'=>false,'error'=>'date and service_id required']);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success'=>false,'error'=>'invalid date']);
    exit;
}

$slots = getAvailableSlots($date, $serviceId, $techId);
echo json_encode(['success'=>true,'data'=>$slots]);
