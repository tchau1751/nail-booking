<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    echo json_encode(['success'=>true,'data'=>fetchAll('SELECT * FROM business_hours WHERE tenant_id=? ORDER BY weekday', [tenantId()])]);
} elseif ($method === 'POST') {
    $rows = json_decode(file_get_contents('php://input'),true);
    foreach ($rows as $r) {
        query('UPDATE business_hours SET is_open=?,start_time=?,end_time=? WHERE tenant_id=? AND weekday=?',
            [(int)$r['is_open'],$r['start_time'],$r['end_time'],tenantId(),(int)$r['weekday']]);
    }
    echo json_encode(['success'=>true]);
}
