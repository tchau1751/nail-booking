<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../includes/db.php';

try { publicSalon(); }
catch (TenantMissing $e) { echo json_encode(['success'=>false,'error'=>$e->getMessage()]); exit; }

$serviceId = (int)($_GET['service_id'] ?? 0);

if ($serviceId) {
    $techs = fetchAll(
        'SELECT t.id,t.name,t.bio,t.photo_url,t.specialties
         FROM technicians t
         JOIN technician_services ts ON ts.technician_id=t.id
         WHERE t.tenant_id=? AND ts.service_id=? AND t.is_active=1
         ORDER BY t.display_order',
        [tenantId(), $serviceId]
    );
} else {
    $techs = fetchAll('SELECT id,name,bio,photo_url,specialties FROM technicians
                       WHERE tenant_id=? AND is_active=1 ORDER BY display_order', [tenantId()]);
}

echo json_encode(['success'=>true,'data'=>$techs]);
