<?php
// ============================================================
//  Send one test text, to check the Twilio settings work — after
//  a new auth token goes in, say. It goes through sendSMS() like
//  the booking texts do, so it proves the settings they will use,
//  and it lands in the SMS log alongside them.
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sms.php';
require_once __DIR__ . '/../pos/includes/pos.php';   // posCsrfToken(), posCsrfValid()

// Texts go to real phones on the salon's account: managers and the owner only.
requireRole('manager');

$message = '';
$success = false;
$phone   = trim($_POST['phone'] ?? '');
$text    = trim($_POST['message'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!posCsrfValid($_POST['_csrf'] ?? null)) {
        http_response_code(419);
        $message = 'That form went stale, so nothing was sent. Try again.';
    } elseif ($phone === '' || $text === '') {
        $message = 'Please enter phone number and message.';
    } else {
        $r = sendSMS($phone, $text, null, 'custom');
        $success = $r['success'];
        $message = $success ? 'SMS sent to ' . normalizePhone($phone) . '.' : 'Error: ' . $r['error'];
    }
}

// Which place each Twilio value will be taken from, checked in the same order
// sendSMS() uses. The values themselves never go on the page.
$s = settings();
$twilio = [];
foreach ([
    'Account SID' => [$s['twilio_account_sid'] ?? '', TWILIO_ACCOUNT_SID],
    'Auth token'  => [$s['twilio_auth_token']  ?? '', TWILIO_AUTH_TOKEN],
    'From number' => [$s['twilio_from_number'] ?? '', TWILIO_FROM_NUMBER],
] as $label => [$saved, $fallback]) {
    $twilio[$label] = $saved ? 'saved in Settings' : ($fallback ? 'from config.local.php' : 'missing');
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
        .info-box{margin-top:24px;padding:16px;background:#f9f9f9;border-radius:8px;font-size:12px;border-left:4px solid #c9947f;line-height:1.6}
        .info-box strong{color:#3a2a24}
        .info-box a{color:#6e4856;font-weight:600}
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
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(posCsrfToken()) ?>">

                <div class="form-group">
                    <label>Phone Number *</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($phone) ?>" placeholder="+14155552671 or 415-555-2671" required>
                    <div class="note">Include country code (+1 for USA)</div>
                </div>

                <div class="form-group">
                    <label>Test Message *</label>
                    <textarea name="message" placeholder="Enter your test message..." required><?= htmlspecialchars($text) ?></textarea>
                    <div class="note">Max 160 characters per SMS</div>
                </div>

                <button type="submit" class="btn-test">Send Test SMS</button>
            </form>

            <div class="info-box">
                <?php foreach ($twilio as $label => $where): ?>
                    <strong><?= $label ?>:</strong> <?= $where ?><br>
                <?php endforeach; ?>
                <a href="<?= BASE_PATH ?>/pos/settings.php">Change them in Settings</a>
            </div>
        </div>
    </div>
</body>
</html>
