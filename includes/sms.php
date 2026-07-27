<?php
// ============================================================
//  SMS Helper — Twilio integration
//  Docs: https://www.twilio.com/docs/sms/quickstart/php
// ============================================================
require_once __DIR__ . '/db.php';

/**
 * Send an SMS via Twilio REST API (no SDK needed).
 * Returns ['success'=>bool, 'sid'=>string, 'error'=>string]
 */
function sendSMS(string $toNumber, string $message, ?int $appointmentId = null, string $type = 'custom'): array {
    $s = settings();

    // Pull from DB first, fall back to constants
    $sid   = $s['twilio_account_sid']  ?: TWILIO_ACCOUNT_SID;
    $token = $s['twilio_auth_token']   ?: TWILIO_AUTH_TOKEN;
    $from  = $s['twilio_from_number']  ?: TWILIO_FROM_NUMBER;

    if (!$sid || !$token || !$from) {
        logSMS($appointmentId, $toNumber, $message, $type, 'not_configured', '');
        return ['success'=>false, 'sid'=>'', 'error'=>'Twilio not configured'];
    }

    $to = normalizePhone($toNumber);

    $url  = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
    $data = http_build_query(['To'=>$to, 'From'=>$from, 'Body'=>$message]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_USERPWD        => "{$sid}:{$token}",
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $body = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && !empty($body['sid'])) {
        logSMS($appointmentId, $to, $message, $type, 'sent', $body['sid']);
        return ['success'=>true, 'sid'=>$body['sid'], 'error'=>''];
    }

    $err = $body['message'] ?? 'Unknown Twilio error';
    logSMS($appointmentId, $to, $message, $type, 'failed', '');
    return ['success'=>false, 'sid'=>'', 'error'=>$err];
}

function logSMS(?int $apptId, string $to, string $msg, string $type, string $status, string $sid): void {
    query(
        'INSERT INTO sms_log (appointment_id,to_number,message,type,status,provider_id) VALUES (?,?,?,?,?,?)',
        [$apptId, $to, $msg, $type, $status, $sid]
    );
}

function normalizePhone(string $phone): string {
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) === 10) $digits = '1' . $digits;
    return '+' . $digits;
}

// ── SMS message templates ────────────────────────────────────

function smsConfirmation(array $appt, array $service, string $businessName): string {
    $date = date('l, F j', strtotime($appt['appointment_date']));
    $time = date('g:i A', strtotime($appt['start_time']));
    return "Hi {$appt['full_name']}! ✨ Your {$service['name']} appointment at {$businessName} is confirmed for {$date} at {$time}. We can't wait to see you! Reply CANCEL to cancel.";
}

function smsReminder(array $appt, array $service, string $businessName): string {
    $date = date('l, F j', strtotime($appt['appointment_date']));
    $time = date('g:i A', strtotime($appt['start_time']));
    return "Hi {$appt['full_name']}! 💅 Reminder: your {$service['name']} at {$businessName} is tomorrow — {$date} at {$time}. See you soon! Reply CANCEL to cancel.";
}

function smsCancellation(array $appt, string $businessName): string {
    return "Hi {$appt['full_name']}, your appointment at {$businessName} has been cancelled. Book again anytime at our website. Thank you!";
}

function smsStatusUpdate(array $appt, array $service, string $businessName, string $newStatus): string {
    $date = date('D M j', strtotime($appt['appointment_date']));
    $time = date('g:i A', strtotime($appt['start_time']));
    $statusMsg = match($newStatus) {
        'confirmed'  => "✅ Your {$service['name']} on {$date} at {$time} has been confirmed!",
        'cancelled'  => "❌ Your appointment on {$date} has been cancelled.",
        'completed'  => "🙏 Thank you for visiting {$businessName}! We hope to see you again soon.",
        default      => "Your appointment status has been updated to: {$newStatus}.",
    };
    return "Hi {$appt['full_name']}! {$statusMsg} — {$businessName}";
}
