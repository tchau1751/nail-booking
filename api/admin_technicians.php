<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $techs = fetchAll('SELECT * FROM technicians ORDER BY display_order,id');
    foreach ($techs as &$t) {
        $t['service_ids'] = array_column(fetchAll('SELECT service_id FROM technician_services WHERE technician_id=?',[$t['id']]),'service_id');
    }
    echo json_encode(['success'=>true,'data'=>$techs]);
} elseif ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'),true);
    query('INSERT INTO technicians (name,phone,email,bio,photo_url,specialties,is_active,display_order) VALUES (?,?,?,?,?,?,?,?)',
        [$d['name'],$d['phone']??'',$d['email']??'',$d['bio']??'',$d['photo_url']??'',$d['specialties']??'',1,(int)($d['display_order']??0)]);
    $id = (int)db()->lastInsertId();
    if (!empty($d['service_ids'])) {
        foreach ($d['service_ids'] as $sid) {
            query('INSERT IGNORE INTO technician_services (technician_id,service_id) VALUES (?,?)',[$id,(int)$sid]);
        }
    }
    echo json_encode(['success'=>true,'id'=>$id]);
} elseif ($method === 'PUT') {
    $d  = json_decode(file_get_contents('php://input'),true);
    $id = (int)($d['id']??0);
    query('UPDATE technicians SET name=?,phone=?,email=?,bio=?,photo_url=?,specialties=?,is_active=?,display_order=? WHERE id=?',
        [$d['name'],$d['phone']??'',$d['email']??'',$d['bio']??'',$d['photo_url']??'',$d['specialties']??'',(int)$d['is_active'],(int)($d['display_order']??0),$id]);
    query('DELETE FROM technician_services WHERE technician_id=?',[$id]);
    if (!empty($d['service_ids'])) {
        foreach ($d['service_ids'] as $sid) {
            query('INSERT IGNORE INTO technician_services (technician_id,service_id) VALUES (?,?)',[$id,(int)$sid]);
        }
    }
    echo json_encode(['success'=>true]);
} elseif ($method === 'DELETE') {
    $id = (int)($_GET['id']??0);
    query('UPDATE technicians SET is_active=0 WHERE id=?',[$id]);
    echo json_encode(['success'=>true]);
}
