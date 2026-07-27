<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sms.php';

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    $from   = $_GET['from']   ?? '';
    $to     = $_GET['to']     ?? '';
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 20;
    $offset = ($page - 1) * $limit;

    $where  = ['1=1'];
    $params = [];

    if ($status) { $where[] = 'a.status=?'; $params[] = $status; }
    if ($search) { $where[] = '(a.full_name LIKE ? OR a.email LIKE ? OR a.phone LIKE ?)'; $s = "%{$search}%"; $params = array_merge($params,[$s,$s,$s]); }
    if ($from)   { $where[] = 'a.appointment_date>=?'; $params[] = $from; }
    if ($to)     { $where[] = 'a.appointment_date<=?'; $params[] = $to; }

    $whereStr = implode(' AND ', $where);
    $total    = fetchOne("SELECT COUNT(*) as c FROM appointments a WHERE {$whereStr}", $params)['c'];
    $rows     = fetchAll(
        "SELECT a.*,s.name AS service_name,s.price AS service_price,
                t.name AS technician_name
         FROM appointments a
         JOIN services s ON s.id=a.service_id
         LEFT JOIN technicians t ON t.id=a.technician_id
         WHERE {$whereStr}
         ORDER BY a.appointment_date DESC,a.start_time DESC
         LIMIT {$limit} OFFSET {$offset}",
        $params
    );
    echo json_encode(['success'=>true,'data'=>$rows,'total'=>(int)$total,'page'=>$page,'limit'=>$limit]);

} elseif ($method === 'PATCH') {
    $raw    = json_decode(file_get_contents('php://input'), true);
    $id     = (int)($raw['id'] ?? 0);
    $status = $raw['status'] ?? '';

    $allowed = ['pending','confirmed','cancelled','completed'];
    if (!$id || !in_array($status, $allowed)) {
        echo json_encode(['success'=>false,'error'=>'Invalid data']); exit;
    }

    query('UPDATE appointments SET status=? WHERE id=?', [$status, $id]);

    // Send SMS on status change
    $appt    = fetchOne('SELECT * FROM appointments WHERE id=?', [$id]);
    $service = fetchOne('SELECT * FROM services WHERE id=?', [$appt['service_id']]);
    $s       = settings();
    $biz     = $s['business_name'] ?? 'Diamond Nail & Spa';

    sendSMS($appt['phone'], smsStatusUpdate($appt, $service, $biz, $status), $id, $status === 'cancelled' ? 'cancellation' : 'custom');

    echo json_encode(['success'=>true,'message'=>'Status updated']);

} elseif ($method === 'POST' && ($_GET['action'] ?? '') === 'sms') {
    $raw  = json_decode(file_get_contents('php://input'), true);
    $id   = (int)($raw['id'] ?? 0);
    $type = $raw['type'] ?? 'reminder';
    if (!$id) { echo json_encode(['success'=>false,'error'=>'Missing id']); exit; }

    $appt    = fetchOne('SELECT * FROM appointments WHERE id=?', [$id]);
    $service = fetchOne('SELECT * FROM services WHERE id=?', [$appt['service_id']]);
    $s       = settings();
    $biz     = $s['business_name'] ?? 'Diamond Nail & Spa';

    $msg = match($type) {
        'reminder'     => smsReminder($appt, $service, $biz),
        'confirmation' => smsConfirmation($appt, $service, $biz),
        default        => $raw['message'] ?? 'Message from ' . $biz,
    };

    $result = sendSMS($appt['phone'], $msg, $id, $type);
    if ($result['success'] && $type === 'reminder') {
        query('UPDATE appointments SET sms_reminder_sent=1 WHERE id=?', [$id]);
    }
    echo json_encode($result);
}
