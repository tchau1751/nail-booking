<?php
require_once __DIR__ . '/includes/functions.php';

$business = get_business_settings();
$updatedDate = 'August 3, 2026';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Privacy Policy — <?= e($business['business_name']) ?></title>
<meta name="description" content="Privacy policy for <?= e($business['business_name']) ?> — how we collect, use, and protect your information.">
<link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/img/favicon-16.png">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style-v4.css">
<style>
  .legal-hero { padding: 160px 0 40px; text-align: center; }
  .legal-hero h1 { font-family:'Playfair Display', serif; font-size: clamp(30px, 5vw, 46px); color: var(--ink); margin: 0 0 10px; }
  .legal-hero p { color: var(--ink-soft); font-size: 14px; }
  .legal-wrap { padding: 20px 0 100px; }
  .legal-body { max-width: 760px; margin: 0 auto; color: var(--ink); font-size: 15.5px; line-height: 1.75; }
  .legal-body h2 { font-family:'Playfair Display', serif; font-size: 22px; color: var(--rose-gold); margin: 40px 0 12px; }
  .legal-body h2:first-child { margin-top: 0; }
  .legal-body p { margin: 0 0 14px; color: var(--ink-soft); }
  .legal-body ul { margin: 0 0 14px; padding-left: 22px; color: var(--ink-soft); }
  .legal-body li { margin-bottom: 6px; }
  .legal-body a { color: var(--rose-gold); }
  .legal-body strong { color: var(--ink); }
</style>
</head>
<body>

<nav class="navbar is-scrolled">
  <div class="container">
    <a href="/booking-login.php" class="brand">
      <?php if (!empty($business['logo_url'])): ?>
        <img src="<?= e($business['logo_url']) ?>" alt="<?= e($business['business_name']) ?>" style="height:52px;width:auto;">
      <?php else: ?>
        <span class="brand-mark">D</span>
        <span class="brand-name"><?= e($business['business_name']) ?><span>Nail Studio &amp; Spa</span></span>
      <?php endif; ?>
    </a>
    <div class="nav-links">
      <a href="/booking-login.php#services">Services</a>
      <a href="/menu.php">Menu</a>
      <a href="/booking-login.php#gallery">Gallery</a>
      <a href="/booking-login.php#about">About</a>
      <a href="/booking-login.php#booking">Book Now</a>
    </div>
    <div class="nav-actions">
      <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $business['business_phone'])) ?>" class="nav-phone">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        <?= e($business['business_phone']) ?>
      </a>
      <a href="/booking-login.php#booking" class="btn btn-primary">Book Your Appointment</a>
      <button class="nav-toggle" aria-label="Open menu" onclick="document.querySelector('.mobile-menu').classList.add('open')">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
      </button>
    </div>
  </div>
</nav>

<div class="mobile-menu">
  <button class="mobile-menu-close" aria-label="Close menu">&times;</button>
  <a href="/booking-login.php#services">Services</a>
  <a href="/menu.php">Menu</a>
  <a href="/booking-login.php#gallery">Gallery</a>
  <a href="/booking-login.php#about">About</a>
  <a href="/booking-login.php#booking">Book Now</a>
</div>

<section class="legal-hero">
  <div class="container">
    <h1>Privacy Policy</h1>
    <p>Last updated: <?= e($updatedDate) ?></p>
  </div>
</section>

<section class="legal-wrap">
  <div class="container">
    <div class="legal-body">

      <p>This policy explains how <?= e($business['business_name']) ?> ("we," "us") collects, uses, and protects
      information when you book an appointment on our website, use our in-store check-in kiosk, or use our mobile
      booking app.</p>

      <h2>Information We Collect</h2>
      <p>When you book an appointment or check in, we collect:</p>
      <ul>
        <li><strong>Contact information</strong> — your name, phone number, and (optionally) email address</li>
        <li><strong>Appointment details</strong> — service selected, date/time, and technician preference</li>
        <li><strong>Optional details</strong> — date of birth, if provided (used for birthday promotions)</li>
        <li><strong>Rewards &amp; gift cards</strong> — visit history, reward points balance, and gift card codes/balances tied to your phone number</li>
      </ul>
      <p>We do not collect or store any payment card information — payment is handled in person at the salon.</p>

      <h2>How We Use Your Information</h2>
      <ul>
        <li>To schedule, confirm, and remind you of appointments</li>
        <li>To track and apply loyalty rewards and gift card balances</li>
        <li>To send appointment confirmations, reminders, and occasional promotional offers by text message or email</li>
        <li>To contact you if there's an issue with your appointment</li>
      </ul>
      <p>You can opt out of promotional text messages at any time by replying STOP, or by asking us to remove you
      from marketing messages. You'll still receive appointment confirmations/reminders tied to bookings you make.</p>

      <h2>Sharing Your Information</h2>
      <p>We do not sell your personal information. We share information only with the service providers that help
      us operate:</p>
      <ul>
        <li><strong>Twilio</strong> — to deliver text message confirmations, reminders, and promotions</li>
        <li><strong>Resend</strong> — to deliver email confirmations</li>
      </ul>
      <p>These providers only receive what's needed to send your message and don't use your information for their
      own purposes.</p>

      <h2>Data Retention &amp; Your Choices</h2>
      <p>We keep appointment and rewards history so we can honor your loyalty points and gift card balances. If
      you'd like your information corrected or removed, contact us using the details below and we'll take care of
      it.</p>

      <h2>Children's Privacy</h2>
      <p>Our services are not directed at children under 13, and we do not knowingly collect information from
      children under 13.</p>

      <h2>Security</h2>
      <p>We take reasonable measures to protect your information, including encrypted connections (HTTPS) and
      access-controlled systems. No method of storage or transmission is 100% secure, but we work to protect your
      data appropriately.</p>

      <h2>Changes to This Policy</h2>
      <p>We may update this policy from time to time. The "Last updated" date above reflects the most recent
      changes.</p>

      <h2>Contact Us</h2>
      <p>
        <?= e($business['business_name']) ?><br>
        <?= e($business['business_address']) ?><br>
        Phone: <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $business['business_phone'])) ?>"><?= e($business['business_phone']) ?></a><br>
        <?php if (!empty($business['business_email'])): ?>
        Email: <a href="mailto:<?= e($business['business_email']) ?>"><?= e($business['business_email']) ?></a>
        <?php endif; ?>
      </p>

    </div>
  </div>
</section>

<!-- ================= FOOTER ================= -->
<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <span class="brand-name" style="color:#fff;"><?= e($business['business_name']) ?></span>
        <p>A premium nail studio in Layton, Utah — manicures, pedicures, nail art, and acrylics delivered with genuine care.</p>
        <div class="footer-social">
          <?php if (!empty($business['instagram_url'])): ?>
          <a href="<?= e($business['instagram_url']) ?>" aria-label="Instagram" target="_blank" rel="noopener">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg>
          </a>
          <?php endif; ?>
          <?php if (!empty($business['facebook_url'])): ?>
          <a href="<?= e($business['facebook_url']) ?>" aria-label="Facebook" target="_blank" rel="noopener">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
          </a>
          <?php endif; ?>
        </div>
      </div>
      <div>
        <h4>Explore</h4>
        <p><a href="/booking-login.php#services">Services</a></p>
        <p><a href="/menu.php">Menu</a></p>
        <p><a href="/booking-login.php#gallery">Gallery</a></p>
        <p><a href="/booking-login.php#about">About</a></p>
        <p><a href="/booking-login.php#booking">Book Now</a></p>
      </div>
      <div>
        <h4>Hours</h4>
        <p><?= nl2br(e(str_replace(' · ', "\n", $business['hours_note']))) ?></p>
      </div>
      <div>
        <h4>Visit Us</h4>
        <p><?= e($business['business_address']) ?></p>
        <p><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $business['business_phone'])) ?>"><?= e($business['business_phone']) ?></a></p>
        <?php if (!empty($business['business_email'])): ?>
        <p><a href="mailto:<?= e($business['business_email']) ?>"><?= e($business['business_email']) ?></a></p>
        <?php endif; ?>
        <p><a href="/privacy-policy.php">Privacy Policy</a></p>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> <?= e($business['business_name']) ?>. All rights reserved.</span>
      <span>Layton, Utah</span>
    </div>
  </div>
</footer>

</body>
</html>
