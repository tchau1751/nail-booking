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

if (!admin_csrf_verify($input['csrf_token'] ?? null)) {
    json_response(['ok' => false, 'error' => 'Session expired — please refresh.'], 419);
}

$booking_id = (int)($input['booking_id'] ?? 0);

if (!$booking_id) {
    json_response(['ok' => false, 'error' => 'Missing booking ID']);
}

$pdo = get_db();

// Get booking and client info
$stmt = $pdo->prepare('
    SELECT b.*, c.id as client_id, c.full_name, c.phone, c.email,
           COALESCE(c.stamp_count, 0) as current_stamps
    FROM bookings b
    LEFT JOIN clients c ON b.client_id = c.id
    WHERE b.id = ?
');
$stmt->execute([$booking_id]);
$booking = $stmt->fetch();

if (!$booking) {
    json_response(['ok' => false, 'error' => 'Booking not found']);
}

// Update booking status to completed
$stmt = $pdo->prepare('UPDATE bookings SET status = ? WHERE id = ?');
$stmt->execute(['completed', $booking_id]);

$stamp_message = '';
$sms_sent = false;

// Add stamp if client exists
if ($booking['client_id']) {
    $new_stamp_count = $booking['current_stamps'] + 1;

    // Check if reached 10 stamps
    if ($new_stamp_count >= 10) {
        $new_stamp_count = 0; // Reset to 0
        $stamp_message = 'Client earned free service! Stamps reset to 0.';

        // Send SMS notification about free service
        if ($booking['phone']) {
            sendStampRewardSMS($booking['phone'], $booking['full_name']);
            $sms_sent = true;
        }
    } else {
        $stamp_message = "Stamp added! ($new_stamp_count/10)";
    }

    // Update stamp count
    $stmt = $pdo->prepare('UPDATE clients SET stamp_count = ? WHERE id = ?');
    $stmt->execute([$new_stamp_count, $booking['client_id']]);
}

json_response([
    'ok' => true,
    'message' => 'Check-in completed',
    'stamp_message' => $stamp_message,
    'sms_sent' => $sms_sent,
    'stamps' => $new_stamp_count ?? 0
]);

// Helper function to send SMS when client completes 10 stamps
function sendStampRewardSMS($phone, $clientName) {
    $accountSid = TWILIO_ACCOUNT_SID;
    $authToken = TWILIO_AUTH_TOKEN;
    $fromNumber = TWILIO_FROM_NUMBER;

    // Format phone
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (strpos($phone, '+') !== 0) {
        $phone = '+1' . substr($phone, -10);
    }

    $message = "Congratulations $clientName! You've earned a FREE SERVICE at Diamond Nails & Spa! Your stamp card has been reset. Come claim your reward!";

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

    curl_exec($ch);
    curl_close($ch);
}
?>
