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

} elseif ($method === 'PATCH' && (($rawIn = json_decode(file_get_contents('php://input'), true)) ?: []) && ($rawIn['action'] ?? '') === 'move') {
    // ── Drag-and-drop reschedule from the calendar ──────────────
    // Deliberately silent: dragging is a quick correction on the owner's own
    // screen, and firing a text at the guest on every drop would be both
    // expensive and alarming. Confirm from All Bookings once it has settled.
    $id    = (int)($rawIn['id'] ?? 0);
    $date  = trim((string)($rawIn['date'] ?? ''));
    $start = trim((string)($rawIn['start'] ?? ''));

    $d = DateTime::createFromFormat('Y-m-d', $date);
    $t = DateTime::createFromFormat('H:i:s', $start) ?: DateTime::createFromFormat('H:i', $start);
    if (!$id || !$d || $d->format('Y-m-d') !== $date || !$t) {
        http_response_code(422);
        echo json_encode(['success'=>false,'error'=>'That date or time is not valid.']); exit;
    }

    $appt = fetchOne('SELECT * FROM appointments WHERE id=?', [$id]);
    if (!$appt) { http_response_code(404); echo json_encode(['success'=>false,'error'=>'Booking not found.']); exit; }

    // Keep the appointment exactly as long as it was — the guest booked a
    // 45-minute service, and moving it must not quietly change that.
    $wasStart = new DateTime($appt['appointment_date'] . ' ' . $appt['start_time']);
    $wasEnd   = new DateTime($appt['appointment_date'] . ' ' . $appt['end_time']);
    $minutes  = max(5, (int)(($wasEnd->getTimestamp() - $wasStart->getTimestamp()) / 60));

    $newStart = new DateTime($date . ' ' . $t->format('H:i:s'));
    $newEnd   = (clone $newStart)->modify("+{$minutes} minutes");

    query('UPDATE appointments SET appointment_date=?, start_time=?, end_time=? WHERE id=?',
          [$newStart->format('Y-m-d'), $newStart->format('H:i:s'), $newEnd->format('H:i:s'), $id]);

    echo json_encode([
        'success' => true,
        'message' => 'Moved to ' . $newStart->format('D j M') . ' at ' . $newStart->format('g:i A'),
    ]);

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
        'promo'        => smsPromo($appt, $biz, $raw['offer'] ?? ''),
        default        => $raw['message'] ?? 'Message from ' . $biz,
    };

    $result = sendSMS($appt['phone'], $msg, $id, $type);
    if ($result['success'] && $type === 'reminder') {
        query('UPDATE appointments SET sms_reminder_sent=1 WHERE id=?', [$id]);
    }
    echo json_encode($result);
}
