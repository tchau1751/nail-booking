<?php
header('Content-Type: application/json');

$pdo = get_db();

// Get request data
$raw = json_decode(file_get_contents('php://input'), true);
$booking_id = (int)($raw['booking_id'] ?? 0);
$phone = trim($raw['phone'] ?? '');
$type = $raw['type'] ?? 'remind'; // 'confirm' or 'remind'

if (!$booking_id || !$phone) {
    echo json_encode(['success' => false, 'error' => 'Missing booking_id or phone']);
    exit;
}

// Get booking details
$stmt = $pdo->prepare('
    SELECT b.*, s.name as service_name
    FROM bookings b
    JOIN services s ON s.id = b.service_id
    WHERE b.id = ?
');
$stmt->execute([$booking_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    echo json_encode(['success' => false, 'error' => 'Booking not found']);
    exit;
}

// Twilio credentials
$accountSid = 'AC3c74634420b61c96ed710cf81a98bc13';
$authToken = 'a55bb1e51c386caf4be796a2be2d210a';
$fromNumber = '+18559381372';

// Format phone number
$phone = preg_replace('/[^0-9+]/', '', $phone);
if (strpos($phone, '+') !== 0) {
    $phone = '+1' . substr($phone, -10);
}

// Build message
if ($type === 'confirm') {
    $message = "Hi {$booking['full_name']}, your appointment at Diamond Nails & Spa is confirmed for " .
               date('M d, Y H:i', strtotime($booking['appointment_date'] . ' ' . $booking['appointment_time'])) .
               ". See you soon!";
} else {
    $message = "Hi {$booking['full_name']}, reminder: you have an appointment at Diamond Nails & Spa tomorrow at " .
               date('H:i', strtotime($booking['appointment_time'])) .
               ". Call us if you need to reschedule!";
}

// Send via Twilio API
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
    echo json_encode(['success' => true, 'message' => 'SMS sent successfully']);
} else {
    $data = json_decode($response, true);
    echo json_encode(['success' => false, 'error' => $data['message'] ?? 'Failed to send SMS']);
}
?>
