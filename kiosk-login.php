<?php
require_once __DIR__ . '/includes/functions.php';

$business = get_business_settings();
$kioskMode = $business['kiosk_mode'] ?? 'checkin';
$hasPromo = !empty($business['kiosk_promo_message']);
$hasPromoImage = !empty($business['kiosk_promo_image_url']);

if ($kioskMode === 'display'):
?>
<!DOCTYPE html>
<html lang="en">
<head><style>
/* iOS ignores the manifest orientation lock, so on a portrait-mounted
   iPad we rotate the page itself to force the landscape layout. */
@media screen and (orientation: portrait) {
  html {
    transform: rotate(90deg);
    transform-origin: left top;
    width: 100vh;
    height: 100vw;
    overflow-x: hidden;
    position: absolute;
    top: 100%;
    left: 0;
  }
}
body { padding: 40px 20px 10px !important; }
.kiosk-card { padding: 8px 12px 10px !important; overflow: hidden !important; }
.kiosk-emoji { font-size: 56px !important; margin-bottom: 12px !important; margin-top: 0 !important; }
.kiosk-header { margin-bottom: 10px !important; }
</style>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Diamond Nails">
<meta name="mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#1c1512">
<link rel="manifest" href="/manifest.json">
<link rel="apple-touch-icon" href="/assets/img/kiosk-icon.svg">
<title><?= e($business['business_name']) ?> — Welcome</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/kiosk-display-v4.css">
</head>
<body>

<div class="kd-screen<?= $hasPromoImage ? ' has-image' : '' ?>">
  <?php if ($hasPromoImage): ?>
    <img class="kd-bgimg" src="<?= e($business['kiosk_promo_image_url']) ?>" alt="">
    <div class="kd-scrim"></div>
  <?php endif; ?>

  <div class="kd-content">
    <div class="kd-brand">
      <span class="kd-brand-mark">💎</span>
      <div class="kd-brand-name"><?= e($business['business_name']) ?></div>
    </div>

    <div class="kd-promo">
      <?php if ($hasPromo): ?>
        <div class="kd-badge">✨ Special Offer</div>
        <div class="kd-promo-text"><?= nl2br(e($business['kiosk_promo_message'])) ?></div>
      <?php else: ?>
        <div class="kd-welcome">Welcome</div>
        <div class="kd-tagline">Please check in with our front desk team</div>
      <?php endif; ?>
    </div>

    <div class="kd-footer">
      <?php if (!empty($business['hours_note'])): ?>
        <div class="kd-hours"><?= e($business['hours_note']) ?></div>
      <?php endif; ?>
      <?php if (!empty($business['business_phone'])): ?>
        <div class="kd-phone"><?= e($business['business_phone']) ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>

</body>
</html>
<?php
return;
endif;

// ---- Interactive check-in mode -----------------------------------------
$services = get_active_services();
$discounts = get_db()->query("SELECT * FROM discounts WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head><style>
/* iOS ignores the manifest orientation lock, so on a portrait-mounted
   iPad we rotate the page itself to force the landscape layout. */
@media screen and (orientation: portrait) {
  html {
    transform: rotate(90deg);
    transform-origin: left top;
    width: 100vh;
    height: 100vw;
    overflow-x: hidden;
    position: absolute;
    top: 100%;
    left: 0;
  }
}
body { padding: 40px 20px 10px !important; }
.kiosk-card { padding: 8px 12px 10px !important; overflow: hidden !important; }
.kiosk-emoji { font-size: 56px !important; margin-bottom: 12px !important; margin-top: 0 !important; }
.kiosk-header { margin-bottom: 10px !important; }
</style>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Diamond Nails">
<meta name="mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#1c1512">
<link rel="manifest" href="/manifest.json">
<link rel="apple-touch-icon" href="/assets/img/kiosk-icon.svg">
<title>Check-In Kiosk — <?= e($business['business_name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/kiosk-final-v31.css?t=1785852507720?t=1785817848?t=1785816816?t=1785816389">
</head>
<body>

<div class="kiosk-layout">
  <?php if ($hasPromo): ?>
  <div class="kiosk-promo-banner<?= $hasPromoImage ? ' has-image' : '' ?>">
    <?php if ($hasPromoImage): ?>
      <div class="kiosk-promo-imgwrap">
        <img class="kiosk-promo-bgimg" src="<?= e($business['kiosk_promo_image_url']) ?>" alt="">
      </div>
    <?php endif; ?>
    <div class="kiosk-promo-body">
      <div class="kiosk-promo-badge">✨ Special Offer</div>
      <div class="kiosk-promo-text"><?= nl2br(e($business['kiosk_promo_message'])) ?></div>
    </div>
  </div>
  <?php endif; ?>

<div class="kiosk-card">
  <div class="kiosk-topbar">
    <span class="kiosk-brand-mark">💎</span>
    <button type="button" class="kiosk-back" id="kiosk-back" aria-label="Back" style="visibility:hidden;">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
    </button>
  </div>

  <div class="kiosk-header">
    <div class="kiosk-emoji">💅</div>
    <h1><?= e($business['business_name']) ?> Kiosk</h1>
    <p id="kiosk-subtitle">Enter your phone number to check in</p>
  </div>

  <div class="kiosk-alert" id="kiosk-alert"></div>

  <!-- Step 0: phone entry -->
  <div class="kiosk-step active" data-step="0">
    <div class="kiosk-display" id="phone-display">Phone Number</div>
    <div class="kiosk-keypad">
      <?php foreach (['1','2','3','4','5','6','7','8','9'] as $d): ?>
        <button type="button" class="kiosk-key" data-digit="<?= $d ?>"><?= $d ?></button>
      <?php endforeach; ?>
      <div></div>
      <button type="button" class="kiosk-key" data-digit="0">0</button>
      <button type="button" class="kiosk-key kiosk-key-back" id="kiosk-erase" aria-label="Erase">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"/><path d="M18 9l-6 6M12 9l6 6"/></svg>
      </button>
    </div>
    <button type="button" class="kiosk-btn-primary" id="phone-continue" disabled>Continue</button>
    <?php if (!empty($business['kiosk_points_lookup_enabled'])): ?>
    <button type="button" class="kiosk-btn-secondary" id="check-points-btn" disabled>✨ Check My Points</button>
    <?php endif; ?>
    <div class="terms">By checking in you agree to receive text messages about your appointments, and occasional birthday or promotional offers from us. Reply STOP at any time to opt out. Message and data rates may apply. We never sell your information.</div>
  </div>

  <!-- Step 1: name entry (new clients only) -->
  <div class="kiosk-step" data-step="1">
    <input type="text" class="kiosk-input" id="name-input" placeholder="Your full name" autocomplete="off">
    <div class="kiosk-input-label">Date of birth (optional)</div>
    <input type="text" class="kiosk-input" id="dob-input" inputmode="numeric" placeholder="MM/DD/YYYY" maxlength="10" autocomplete="off">
    <button type="button" class="kiosk-btn-primary" id="name-continue">Continue</button>
  </div>

  <!-- Step 2: service selection (up to 3, back-to-back) -->
  <div class="kiosk-step" data-step="2">
    <p style="text-align:center;color:var(--k-ink-soft);margin:0 0 14px;">Select up to 3 services</p>
    <div class="kiosk-service-grid">
      <?php foreach ($services as $svc): ?>
        <div class="kiosk-service kiosk-service-media" data-service-id="<?= (int)$svc['id'] ?>" data-service-name="<?= e($svc['name']) ?>" data-service-price-raw="<?= (float)$svc['price'] ?>" data-service-duration="<?= (int)$svc['duration_minutes'] ?>">
          <?php if (!empty($svc['image_url'])): ?>
            <img class="kiosk-service-thumb" src="<?= e($svc['image_url']) ?>" alt="" loading="lazy">
          <?php endif; ?>
          <div class="kiosk-service-text">
            <div class="kiosk-service-name"><?= e($svc['name']) ?></div>
            <div class="kiosk-service-meta"><?= e(format_duration((int)$svc['duration_minutes'])) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div id="kiosk-service-summary" style="display:none;text-align:center;color:var(--k-ink-soft);font-size:13.5px;margin-bottom:16px;"></div>
    <button type="button" class="kiosk-btn-primary" id="service-continue" disabled>Continue</button>
  </div>

  <!-- Step 3: discount (optional) -->
  <div class="kiosk-step" data-step="3">
    <p style="text-align:center;color:var(--k-ink-soft);margin:0 0 18px;">Have a discount? Select it below, or skip.</p>
    <div class="kiosk-service-grid">
      <div class="kiosk-service selected" data-discount-id="" data-discount-name="">
        <div class="kiosk-service-name">No discount</div>
      </div>
      <?php foreach ($discounts as $d): ?>
        <div class="kiosk-service" data-discount-id="<?= (int) $d['id'] ?>" data-discount-name="<?= e($d['name']) ?>">
          <div class="kiosk-service-name"><?= e($d['name']) ?></div>
          <div class="kiosk-service-meta"><?= $d['type'] === 'flat' ? '$' . number_format((float) $d['amount'], 2) : (int) $d['amount'] . '%' ?> off</div>
        </div>
      <?php endforeach; ?>
    </div>
    <div id="redeem-points-block" style="display:none;margin-bottom:18px;">
      <label style="display:flex;align-items:center;gap:12px;background:var(--k-surface-soft);border:1px solid var(--k-border);border-radius:14px;padding:14px 16px;cursor:pointer;">
        <input type="checkbox" id="redeem-points-checkbox" style="width:22px;height:22px;flex-shrink:0;">
        <span id="redeem-points-label" style="font-size:14px;color:var(--k-ink);line-height:1.4;"></span>
      </label>
    </div>
    <div class="kiosk-input-label">Gift card code (optional)</div>
    <input type="text" class="kiosk-input" id="gift-card-input" placeholder="DN-XXXX-XXXX" autocomplete="off" autocapitalize="characters" style="text-transform:uppercase;font-family:monospace;letter-spacing:0.05em;">
    <div id="gift-card-status" style="text-align:center;font-size:12.5px;margin:-16px 0 22px;min-height:16px;"></div>
    <button type="button" class="kiosk-btn-primary" id="discount-continue">Check Me In</button>
  </div>

  <!-- Step 4: confirmation -->
  <div class="kiosk-step" data-step="4">
    <div class="kiosk-confirm">
      <div class="kiosk-confirm-badge">✓</div>
      <h2>You're checked in, <span id="confirm-name"></span>!</h2>
      <p>Please have a seat — we'll be with you shortly for your <strong id="confirm-service"></strong>.</p>
      <p id="confirm-points" style="display:none;color:var(--k-rose-gold-light);font-weight:700;"></p>
      <div id="confirm-stamp-card" class="kiosk-stamp-card" style="display:none;"></div>
      <p id="confirm-gift-card" style="display:none;font-weight:700;"></p>
      <p id="confirm-points-redeemed" style="display:none;font-weight:700;"></p>
    </div>
    <button type="button" class="kiosk-btn-primary" id="kiosk-restart">Done</button>
  </div>

  <!-- Step 5: points lookup (no check-in) -->
  <div class="kiosk-step" data-step="5">
    <div class="kiosk-confirm">
      <div class="kiosk-confirm-badge">✨</div>
      <h2 id="points-lookup-name"></h2>
      <p id="points-lookup-balance" style="font-size:20px;font-weight:700;color:var(--k-rose-gold-light);"></p>
      <div id="points-lookup-stamp-card" class="kiosk-stamp-card" style="display:none;"></div>
    </div>
    <button type="button" class="kiosk-btn-primary" id="points-lookup-done">Done</button>
  </div>

  <div class="kiosk-footer">
    <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $business['business_phone'])) ?>">Need help? Ask the front desk</a>
  </div>
</div>
</div>

<input type="hidden" id="csrf_token" value="<?= e($token) ?>">
<script src="/assets/js/kiosk-v18.js?t=1785818723252?t=1785817078785816624"></script>

<!-- Session Keep-Alive -->
<script>
// Keep kiosk session alive by pinging server every 5 minutes
setInterval(function() {
  fetch('/kiosk-login.php', {
    method: 'GET',
    credentials: 'include',
    headers: { 'pragma': 'no-cache', 'cache-control': 'no-cache' }
  }).catch(function(err) {
    console.log('Session ping:', err);
  });
}, 300000); // 5 minutes
</script>
</body>
</html>
