<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!current_admin()) {
    json_response(['ok' => false, 'error' => 'Not authenticated.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$pdo = get_db();

$booking_id = (int)($input['booking_id'] ?? 0);
$phone = trim($input['phone'] ?? '');
$type = $input['type'] ?? 'remind';

if (!$booking_id || !$phone) {
    json_response(['ok' => false, 'error' => 'Missing booking_id or phone']);
}

$stmt = $pdo->prepare('
    SELECT b.*, s.name as service_name
    FROM bookings b
    JOIN services s ON s.id = b.service_id
    WHERE b.id = ?
');
$stmt->execute([$booking_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    json_response(['ok' => false, 'error' => 'Booking not found']);
}

$accountSid = 'AC3c74634420b61c96ed710cf81a98bc13';
$authToken = 'a55bb1e51c386caf4be796a2be2d210a';
$fromNumber = '+18559381372';

$phone = preg_replace('/[^0-9+]/', '', $phone);
if (strpos($phone, '+') !== 0) {
    $phone = '+1' . substr($phone, -10);
}

if ($type === 'confirm') {
    $message = "Hi {$booking['full_name']}, your appointment at Diamond Nails & Spa is confirmed for " .
               date('M d, Y H:i', strtotime($booking['appointment_date'] . ' ' . $booking['appointment_time'])) .
               ". See you soon!";
} else {
    $message = "Hi {$booking['full_name']}, reminder: you have an appointment at Diamond Nails & Spa tomorrow at " .
               date('H:i', strtotime($booking['appointment_time'])) .
               ". Call us if you need to reschedule!";
}

$url = "https://api.twilio.com/2010-04-01/Accounts/$accountSid/Messages.json";
$postData = http_build_query([
    'From' => $fromNumber,
    'To' => $phone,
    'Body' => $message
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_USERPWD, "$accountSid:$authToken");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 201) {
    json_response(['ok' => true, 'message' => 'SMS sent successfully']);
} else {
    $error_msg = 'HTTP ' . $httpCode;
    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['message'])) {
            $error_msg = $data['message'];
        } elseif (isset($data['error'])) {
            $error_msg = $data['error'];
        }
    }
    json_response(['ok' => false, 'error' => $error_msg]);
}
?>
