<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    // The auth token never leaves the server. It was being sent to the browser
    // and rendered into a filled password box, where anyone at the tablet could
    // read it straight back out.
    $data = settings();
    $data['twilio_auth_token_set'] = $data['twilio_auth_token'] !== '';
    $data['twilio_auth_token'] = '';
    echo json_encode(['success'=>true,'data'=>$data]);
} elseif ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'),true);
    $fields = ['business_name','business_phone','business_email','business_address','slot_interval_minutes','booking_notice_hours','sms_sender','timezone','reminder_hours_before','twilio_account_sid','twilio_auth_token','twilio_from_number'];
    $sets=[]; $params=[];
    foreach($fields as $f) {
        if (!isset($d[$f])) continue;
        // An empty token box means "leave it alone", not "wipe it" — the form
        // cannot show what is stored, so it cannot be asked to resend it.
        if ($f === 'twilio_auth_token' && trim((string)$d[$f]) === '') continue;
        $sets[]="$f=?"; $params[]=$d[$f];
    }
    if($sets) { $params[] = tenantId(); query('UPDATE business_settings SET '.implode(',',$sets).' WHERE tenant_id=?',$params); }
    echo json_encode(['success'=>true]);
}
