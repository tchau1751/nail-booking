<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../includes/db.php';

$services = fetchAll(
    'SELECT id,name,description,duration_minutes,price,category,image_url FROM services WHERE is_active=1 ORDER BY display_order,id'
);
echo json_encode(['success'=>true,'data'=>$services]);
