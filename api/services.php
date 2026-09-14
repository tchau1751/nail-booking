<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../includes/db.php';

try { publicSalon(); }
catch (TenantMissing $e) { echo json_encode(['success'=>false,'error'=>$e->getMessage()]); exit; }

$services = fetchAll(
    'SELECT id,name,description,duration_minutes,price,category,image_url FROM services
     WHERE tenant_id=? AND is_active=1 ORDER BY display_order,id', [tenantId()]
);
echo json_encode(['success'=>true,'data'=>$services]);
