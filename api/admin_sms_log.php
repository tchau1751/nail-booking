<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }
$rows = fetchAll('SELECT * FROM sms_log ORDER BY sent_at DESC LIMIT 200');
echo json_encode(['success'=>true,'data'=>$rows]);
