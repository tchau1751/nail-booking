<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$tid    = tenantId();

/** Which services this technician does — only ever this salon's services. */
function saveTechServices(int $techId, array $serviceIds): void {
    query('DELETE FROM technician_services WHERE tenant_id=? AND technician_id=?', [tenantId(), $techId]);
    foreach ($serviceIds as $sid) {
        $sid = ownedId('services', $sid);
        if (!$sid) continue;
        query('INSERT IGNORE INTO technician_services (tenant_id,technician_id,service_id) VALUES (?,?,?)',
              [tenantId(), $techId, $sid]);
    }
}

if ($method === 'GET') {
    $techs = fetchAll('SELECT * FROM technicians WHERE tenant_id=? ORDER BY display_order,id', [$tid]);
    foreach ($techs as &$t) {
        $t['service_ids'] = array_column(fetchAll('SELECT service_id FROM technician_services WHERE tenant_id=? AND technician_id=?',
                                                  [$tid, $t['id']]), 'service_id');
    }
    echo json_encode(['success'=>true,'data'=>$techs]);
} elseif ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'),true);
    query('INSERT INTO technicians (tenant_id,name,phone,email,bio,photo_url,specialties,is_active,display_order) VALUES (?,?,?,?,?,?,?,?,?)',
        [$tid,$d['name'],$d['phone']??'',$d['email']??'',$d['bio']??'',$d['photo_url']??'',$d['specialties']??'',1,(int)($d['display_order']??0)]);
    $id = (int)db()->lastInsertId();
    saveTechServices($id, (array)($d['service_ids'] ?? []));
    echo json_encode(['success'=>true,'id'=>$id]);
} elseif ($method === 'PUT') {
    $d  = json_decode(file_get_contents('php://input'),true);
    $id = (int)($d['id']??0);
    if (!tenantOwns('technicians', $id)) { http_response_code(404); echo json_encode(['success'=>false,'error'=>'Technician not found.']); exit; }
    query('UPDATE technicians SET name=?,phone=?,email=?,bio=?,photo_url=?,specialties=?,is_active=?,display_order=? WHERE id=? AND tenant_id=?',
        [$d['name'],$d['phone']??'',$d['email']??'',$d['bio']??'',$d['photo_url']??'',$d['specialties']??'',(int)$d['is_active'],(int)($d['display_order']??0),$id,$tid]);
    saveTechServices($id, (array)($d['service_ids'] ?? []));
    echo json_encode(['success'=>true]);
} elseif ($method === 'DELETE') {
    $id = (int)($_GET['id']??0);
    query('UPDATE technicians SET is_active=0 WHERE id=? AND tenant_id=?',[$id,$tid]);
    echo json_encode(['success'=>true]);
}
