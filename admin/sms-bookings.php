<?php
session_start();

// Check if logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$success = false;

// Handle manual SMS sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $booking_id = intval($_POST['booking_id'] ?? 0);
    $phone = trim($_POST['phone'] ?? '');
    $type = $_POST['type'] ?? ''; // 'confirm' or 'remind'

    if ($booking_id && $phone && $type) {
        // Twilio credentials
        $accountSid = 'AC3c74634420b61c96ed710cf81a98bc13';
        $authToken = 'a55bb1e51c386caf4be796a2be2d210a';
        $fromNumber = '+18559381372';

        // Get booking details from database
        try {
            $pdo = new PDO('mysql:host=localhost;dbname=nail_booking', 'root', '');
            $stmt = $pdo->prepare('SELECT b.*, s.name as service_name, c.name as client_name FROM bookings b LEFT JOIN services s ON b.service_id = s.id LEFT JOIN clients c ON b.client_id = c.id WHERE b.id = ?');
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($booking) {
                // Build message
                if ($type === 'confirm') {
                    $message_text = "Hi {$booking['client_name']}, your appointment at Diamond Nails & Spa is confirmed for " . date('M d, Y H:i', strtotime($booking['appointment_date'])) . ". See you soon!";
                } else {
                    $message_text = "Hi {$booking['client_name']}, reminder: you have an appointment at Diamond Nails & Spa tomorrow at " . date('H:i', strtotime($booking['appointment_date'])) . ". Call us if you need to reschedule!";
                }

                // Ensure E.164 format
                $phone = preg_replace('/[^0-9+]/', '', $phone);
                if (strpos($phone, '+') !== 0) {
                    $phone = '+1' . substr($phone, -10);
                }

                // Send via Twilio API
                $url = "https://api.twilio.com/2010-04-01/Accounts/$accountSid/Messages.json";
                $postData = http_build_query([
                    'From' => $fromNumber,
                    'To' => $phone,
                    'Body' => $message_text
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
                    $success = true;
                    $message = ucfirst($type) . " SMS sent to {$booking['client_name']}!";
                } else {
                    $data = json_decode($response, true);
                    $message = 'Error: ' . ($data['message'] ?? 'Unknown error');
                }
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
        }
    }
}

// Get bookings list
$bookings = [];
try {
    $pdo = new PDO('mysql:host=localhost;dbname=nail_booking', 'root', '');
    $stmt = $pdo->prepare('SELECT b.*, s.name as service_name, c.name as client_name, c.phone as client_phone FROM bookings b LEFT JOIN services s ON b.service_id = s.id LEFT JOIN clients c ON b.client_id = c.id ORDER BY b.appointment_date DESC LIMIT 50');
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $message = 'Database error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Booking SMS Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:Manrope,sans-serif;background:#fbf7f1;color:#3a2a24;min-height:100vh}
        .container{max-width:1000px;margin:0 auto;padding:28px}
        h1{font-family:'Playfair Display',serif;font-size:28px;margin-bottom:24px}
        .alert{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px}
        .alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
        .alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
        .table-wrap{overflow-x:auto;border-radius:12px;border:1px solid #d9c7b8;background:white}
        table{width:100%;border-collapse:collapse;font-size:13px}
        thead th{background:#f9f9f9;padding:12px 14px;text-align:left;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:rgba(58,42,36,.6);border-bottom:1px solid #d9c7b8}
        tbody td{padding:12px 14px;border-bottom:1px solid rgba(217,199,184,.35);vertical-align:middle}
        tbody tr:last-child td{border-bottom:none}
        .actions{display:flex;gap:6px}
        .btn-sms{background:#1ba0c8;color:white;border:none;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:11px;font-weight:600;transition:.2s}
        .btn-sms:hover{background:#148fa8}
        .btn-sms.remind{background:#d63d7c}
        .btn-sms.remind:hover{background:#b82f68}
        .phone{font-family:monospace;background:#f5f5f5;padding:2px 6px;border-radius:4px}
        .status{display:inline-block;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600}
        .status.confirmed{background:#d1fae5;color:#065f46}
        .status.pending{background:#fef3c7;color:#92400e}
    </style>
</head>
<body>
    <div class="container">
        <h1>Booking SMS Management</h1>

        <?php if ($message): ?>
            <div class="alert <?= $success ? 'alert-success' : 'alert-error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Client Name</th>
                        <th>Phone</th>
                        <th>Service</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                    <tr>
                        <td><?= htmlspecialchars($b['client_name'] ?? 'Unknown') ?></td>
                        <td><span class="phone"><?= htmlspecialchars($b['client_phone'] ?? 'N/A') ?></span></td>
                        <td><?= htmlspecialchars($b['service_name'] ?? 'N/A') ?></td>
                        <td><?= date('M d, Y H:i', strtotime($b['appointment_date'])) ?></td>
                        <td><span class="status <?= $b['status'] === 'confirmed' ? 'confirmed' : 'pending' ?>"><?= ucfirst($b['status']) ?></span></td>
                        <td>
                            <div class="actions">
                                <form method="POST" style="margin:0">
                                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                    <input type="hidden" name="phone" value="<?= htmlspecialchars($b['client_phone']) ?>">
                                    <input type="hidden" name="action" value="send_sms">
                                    <input type="hidden" name="type" value="confirm">
                                    <button type="submit" class="btn-sms">Confirm</button>
                                </form>
                                <form method="POST" style="margin:0">
                                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                    <input type="hidden" name="phone" value="<?= htmlspecialchars($b['client_phone']) ?>">
                                    <input type="hidden" name="action" value="send_sms">
                                    <input type="hidden" name="type" value="remind">
                                    <button type="submit" class="btn-sms remind">Remind</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (empty($bookings)): ?>
            <div style="text-align:center;padding:40px;color:#999">
                No bookings found
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
