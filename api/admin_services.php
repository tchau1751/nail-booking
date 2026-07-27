<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = fetchAll('SELECT * FROM services ORDER BY display_order,id');
    echo json_encode(['success'=>true,'data'=>$rows]);
} elseif ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'),true);
    query('INSERT INTO services (name,description,duration_minutes,price,category,image_url,is_active,display_order) VALUES (?,?,?,?,?,?,?,?)',
        [$d['name'],$d['description']??'',(int)$d['duration_minutes'],(float)$d['price'],$d['category']??'Manicure',$d['image_url']??'',isset($d['is_active'])?(int)$d['is_active']:1,(int)($d['display_order']??0)]);
    echo json_encode(['success'=>true,'id'=>(int)db()->lastInsertId()]);
} elseif ($method === 'PUT') {
    $d = json_decode(file_get_contents('php://input'),true);
    $id = (int)($d['id']??0);
    if (!$id) { echo json_encode(['success'=>false,'error'=>'Missing id']); exit; }
    query('UPDATE services SET name=?,description=?,duration_minutes=?,price=?,category=?,image_url=?,is_active=?,display_order=? WHERE id=?',
        [$d['name'],$d['description']??'',(int)$d['duration_minutes'],(float)$d['price'],$d['category']??'Manicure',$d['image_url']??'',(int)$d['is_active'],(int)($d['display_order']??0),$id]);
    echo json_encode(['success'=>true]);
} elseif ($method === 'DELETE') {
    $id = (int)($_GET['id']??0);
    query('UPDATE services SET is_active=0 WHERE id=?',[$id]);
    echo json_encode(['success'=>true]);
}
