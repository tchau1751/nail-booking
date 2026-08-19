<?php
require_once __DIR__ . '/../config/config.php';
session_start();

$message = '';
$success = false;

// Check if logged in by checking session
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $text = trim($_POST['message'] ?? '');

    if (!$phone || !$text) {
        $message = 'Please enter phone number and message.';
    } else {
        // Twilio credentials from config
        $accountSid = TWILIO_ACCOUNT_SID;
        $authToken = TWILIO_AUTH_TOKEN;
        $fromNumber = TWILIO_FROM_NUMBER;

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
            'Body' => $text
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
            $message = "SMS sent to $phone successfully!";
        } else {
            $data = json_decode($response, true);
            $message = 'Error: ' . ($data['message'] ?? 'Unknown error');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>SMS Test — Diamond Nail & Spa</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:Manrope,sans-serif;background:#f5f5f5;color:#3a2a24;min-height:100vh;display:flex;align-items:center;justify-content:center}
        .test-container{max-width:500px;width:100%;padding:20px}
        .test-form{background:white;padding:32px;border-radius:14px;box-shadow:0 8px 24px -8px rgba(58,42,36,.18)}
        .test-form h1{font-family:'Playfair Display',serif;font-size:24px;margin-bottom:24px;color:#3a2a24}
        .form-group{margin-bottom:16px}
        .form-group label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;color:rgba(58,42,36,.65);margin-bottom:6px;letter-spacing:.12em}
        .form-group input, .form-group textarea{width:100%;padding:11px 14px;border:1.5px solid #d9c7b8;border-radius:8px;font-family:Manrope,sans-serif;font-size:14px;transition:.2s}
        .form-group input:focus, .form-group textarea:focus{outline:none;border-color:#c9947f;box-shadow:0 0 0 3px rgba(201,148,127,.2)}
        .form-group textarea{resize:vertical;min-height:100px}
        .alert{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px}
        .alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
        .alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
        .btn-test{width:100%;background:#3a2a24;color:white;padding:11px;border:none;border-radius:22px;font-weight:600;cursor:pointer;font-size:14px;transition:.2s}
        .btn-test:hover{background:#6e4856;transform:translateY(-1px);box-shadow:0 4px 12px rgba(58,42,36,.2)}
        .note{font-size:12px;color:#666;margin-top:8px;line-height:1.4}
        .info-box{margin-top:24px;padding:16px;background:#f9f9f9;border-radius:8px;font-size:12px;border-left:4px solid #c9947f}
        .info-box strong{color:#3a2a24}
    </style>
</head>
<body>
    <div class="test-container">
        <div class="test-form">
            <h1>Send Test SMS</h1>

            <?php if ($message): ?>
                <div class="alert <?= $success ? 'alert-success' : 'alert-error' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Phone Number *</label>
                    <input type="tel" name="phone" placeholder="+14155552671 or 415-555-2671" required>
                    <div class="note">Include country code (+1 for USA)</div>
                </div>

                <div class="form-group">
                    <label>Test Message *</label>
                    <textarea name="message" placeholder="Enter your test message..." required></textarea>
                    <div class="note">Max 160 characters per SMS</div>
                </div>

                <button type="submit" class="btn-test">Send Test SMS</button>
            </form>

            <div class="info-box">
                <strong>From:</strong> <?= htmlspecialchars(TWILIO_FROM_NUMBER) ?><br>
                <strong>Service:</strong> Twilio<br>
                <strong>Account:</strong> <?= htmlspecialchars(TWILIO_ACCOUNT_SID) ?>
            </div>
        </div>
    </div>
</body>
</html>
