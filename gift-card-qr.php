<?php
require_once __DIR__ . '/includes/functions.php';

$code = trim((string) ($_GET['code'] ?? ''));
$pdo = get_db();
$card = null;

if ($code !== '') {
    $stmt = $pdo->prepare("SELECT * FROM gift_cards WHERE code = :code AND status = 'active' AND balance > 0");
    $stmt->execute(['code' => $code]);
    $card = $stmt->fetch() ?: null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Diamond Nails & Spa — Gift Card</title>
<link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon-32.png">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; }
  body {
    margin: 0;
    min-height: 100vh;
    min-height: 100dvh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #faf6f2;
    font-family: 'Poppins', sans-serif;
    color: #3a2e28;
    padding: 24px;
  }
  .card {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 12px 40px rgba(184,131,106,0.18);
    padding: 36px 32px;
    max-width: 380px;
    width: 100%;
    text-align: center;
  }
  h1 {
    font-family: 'Playfair Display', serif;
    font-size: 22px;
    margin: 0 0 4px;
    color: #b8836a;
  }
  .sub { font-size: 13px; color: #8a7a70; margin-bottom: 24px; }
  .qr-box { display: flex; justify-content: center; margin-bottom: 20px; }
  .code {
    font-family: monospace;
    font-size: 24px;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: #b8836a;
    background: #faf1ea;
    border-radius: 12px;
    padding: 14px;
    margin-bottom: 14px;
  }
  .balance { font-size: 15px; color: #3a2e28; }
  .balance strong { color: #b8836a; }
  .hint { font-size: 12px; color: #8a7a70; margin-top: 18px; }
  .error { font-size: 15px; color: #a3453b; }
</style>
</head>
<body>
<div class="card">
  <h1>Diamond Nails &amp; Spa</h1>
  <div class="sub">Gift Card</div>
  <?php if ($card): ?>
    <div class="qr-box" id="qr"></div>
    <div class="code"><?= e($card['code']) ?></div>
    <div class="balance">Balance: <strong><?= e(format_price((float) $card['balance'])) ?></strong></div>
    <div class="hint">Show this screen or QR code at check-in to redeem.</div>
    <script src="/studio/assets/js/qrcode.min.js"></script>
    <script>
      new QRCode(document.getElementById('qr'), {
        text: <?= json_encode('https://diamondnaillayton.com/studio/gift-cards.php?redeem=' . $card['code']) ?>,
        width: 180,
        height: 180,
        correctLevel: QRCode.CorrectLevel.M,
      });
    </script>
  <?php else: ?>
    <div class="error">This gift card code isn't valid, has already been fully redeemed, or has been disabled.</div>
  <?php endif; ?>
</div>
</body>
</html>
