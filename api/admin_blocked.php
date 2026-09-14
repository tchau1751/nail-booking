<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    echo json_encode(['success'=>true,'data'=>fetchAll('SELECT * FROM blocked_dates WHERE tenant_id=? ORDER BY blocked_date', [tenantId()])]);
} elseif ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'),true);
    try {
        query('INSERT INTO blocked_dates (tenant_id,blocked_date,reason) VALUES (?,?,?)',[tenantId(),$d['blocked_date'],$d['reason']??'']);
        echo json_encode(['success'=>true,'id'=>(int)db()->lastInsertId()]);
    } catch(Exception $e) { echo json_encode(['success'=>false,'error'=>'Date already blocked']); }
} elseif ($method === 'DELETE') {
    $id = (int)($_GET['id']??0);
    query('DELETE FROM blocked_dates WHERE id=? AND tenant_id=?',[$id, tenantId()]);
    echo json_encode(['success'=>true]);
}
