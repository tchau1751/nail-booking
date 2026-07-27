<?php
require_once __DIR__ . '/includes/db.php';
$settings = settings();
$bizName  = $settings['business_name'] ?? 'Diamond Nail & Spa';
$bizPhone = $settings['business_phone'] ?? '';
$bizEmail = $settings['business_email'] ?? '';
$bizAddr  = $settings['business_address'] ?? '';

function esc($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= esc($bizName) ?> — Premium Nail &amp; Spa</title>
<meta name="description" content="Book your appointment at <?= esc($bizName) ?>. Diamond-quality manicures, pedicures, gel nails and nail art in a premium spa environment.">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/public.css">
</head>
<body>

<!-- NAVBAR -->
<header class="navbar" id="navbar">
  <div class="nav-inner">
    <a href="#top" class="nav-brand">
      <span class="brand-gem">💎</span>
      <span><?= esc($bizName) ?></span>
    </a>
    <nav class="nav-links">
      <a href="#services">Services</a>
      <a href="#about">Studio</a>
      <a href="#booking">Book</a>
    </nav>
    <a href="#booking" class="btn btn-gold">Reserve a session</a>
    <button class="nav-toggle" id="navToggle">☰</button>
  </div>
  <div class="nav-mobile" id="navMobile">
    <a href="#services" onclick="closeMobileNav()">Services</a>
    <a href="#about"    onclick="closeMobileNav()">Studio</a>
    <a href="#booking"  onclick="closeMobileNav()">Book</a>
    <a href="#booking"  onclick="closeMobileNav()" class="btn btn-gold" style="margin-top:8px">Reserve a session</a>
  </div>
</header>

<!-- HERO -->
<section id="top" class="hero">
  <div class="hero-bg">
    <img src="https://images.unsplash.com/photo-1604654894610-df63bc536371?q=80&w=1740&auto=format&fit=crop"
         alt="Elegant Diamond Nail & Spa interior" class="hero-img">
    <div class="hero-overlay"></div>
  </div>
  <div class="hero-content">
    <span class="eyebrow">Diamond Nail &amp; Spa — Premium nail care</span>
    <h1>Where every<br><em>detail becomes art.</em></h1>
    <p class="hero-sub">Manicures, pedicures, gel finishes and hand-painted nail art — crafted in a calm, polished spa designed around your visit.</p>
    <div class="hero-ctas">
      <a href="#booking" class="btn btn-primary">Book your appointment</a>
      <a href="#services" class="btn btn-outline">View services</a>
    </div>
    <div class="hero-stars">★★★★★ <span>Trusted by clients across the city</span></div>
  </div>
  <div class="hero-card">
    <p class="hero-card-num">4.9<span>/5</span></p>
    <p class="hero-card-label">Client satisfaction</p>
  </div>
</section>

<!-- SERVICES -->
<section id="services" class="section section-pearl">
  <div class="container">
    <div class="section-label">
      <span class="eyebrow">Nail &amp; Spa services</span>
      <h2>Crafted for every visit</h2>
      <p>From classic manicures to hand-painted nail art, each service is performed with care, premium polish and a spa-level touch.</p>
    </div>
    <div class="services-grid" id="servicesGrid">
      <div class="skeleton-grid">
        <div class="skel"></div><div class="skel"></div><div class="skel"></div>
        <div class="skel"></div><div class="skel"></div><div class="skel"></div>
      </div>
    </div>
  </div>
</section>

<!-- ABOUT -->
<section id="about" class="section">
  <div class="container about-layout">
    <div class="about-img-wrap">
      <img src="https://images.unsplash.com/photo-1610992015734-5933e1aab1c5?q=80&w=1200&auto=format&fit=crop"
           alt="Nail technician at Diamond Nail & Spa" loading="lazy">
    </div>
    <div class="about-text">
      <span class="eyebrow">The spa</span>
      <h2>A calm space, built around your hands.</h2>
      <p><?= esc($bizName) ?> was created for clients who expect more — a spa-grade environment where diamond-standard craftsmanship, immaculate cleanliness and genuine comfort come first, every single visit.</p>
      <ul class="about-points">
        <li><span class="dot"></span><div><strong>Clean by design</strong><p>Sterilized tools and single-use essentials for every client, every visit.</p></div></li>
        <li><span class="dot"></span><div><strong>Hand-selected polish</strong><p>Curated gel and lacquer shades sourced from premium nail care brands.</p></div></li>
        <li><span class="dot"></span><div><strong>Unhurried spa care</strong><p>Every appointment is paced thoughtfully so nothing ever feels rushed.</p></div></li>
      </ul>
    </div>
  </div>
</section>

<!-- BOOKING -->
<section id="booking" class="section section-pearl">
  <div class="container">
    <div class="section-label">
      <span class="eyebrow">Reserve your nail session</span>
      <h2>Book your appointment</h2>
      <p>Choose a service, pick a time that suits you, and we'll take care of the rest.</p>
    </div>

    <div class="steps" id="stepIndicator">
      <div class="step active" data-s="1"><div class="step-n">1</div><span>Service</span></div>
      <div class="step-line"></div>
      <div class="step" data-s="2"><div class="step-n">2</div><span>Date &amp; time</span></div>
      <div class="step-line"></div>
      <div class="step" data-s="3"><div class="step-n">3</div><span>Details</span></div>
      <div class="step-line"></div>
      <div class="step" data-s="4"><div class="step-n">4</div><span>Confirmed</span></div>
    </div>

    <div class="booking-card" id="bookingCard">

      <!-- STEP 1 -->
      <div id="step1" class="book-step">
        <h3>Choose a service</h3>
        <div id="step1Services" class="service-choices"></div>
      </div>

      <!-- STEP 2 -->
      <div id="step2" class="book-step" style="display:none">
        <button class="back-btn" onclick="goStep(1)">← Change service</button>
        <p class="selected-svc" id="selSvcLabel"></p>
        <h3>Pick a date</h3>
        <div class="date-strip" id="dateStrip"></div>
        <div id="techSection" style="display:none;margin-top:20px">
          <h4>Choose a technician <span style="font-weight:400;font-size:13px;color:rgba(58,42,36,.5)">(optional)</span></h4>
          <div id="techChoices" class="tech-choices"></div>
        </div>
        <div id="slotsSection" style="display:none;margin-top:24px">
          <h4>Available times</h4>
          <div id="slotsGrid" class="slots-grid"></div>
        </div>
        <div class="book-actions" style="margin-top:24px">
          <button class="btn btn-primary" id="step2Next" onclick="goStep(3)" disabled>Continue →</button>
        </div>
      </div>

      <!-- STEP 3 -->
      <div id="step3" class="book-step" style="display:none">
        <button class="back-btn" onclick="goStep(2)">← Change date &amp; time</button>
        <div class="step3-layout">
          <div class="step3-form">
            <h3>Your details</h3>
            <div class="field"><label>Full name *</label><input id="bName" type="text" placeholder="Jane Smith"></div>
            <div class="field"><label>Email address *</label><input id="bEmail" type="email" placeholder="jane@email.com"></div>
            <div class="field"><label>Phone number *</label><input id="bPhone" type="tel" placeholder="+1 555 000 0000"></div>
            <div class="field"><label>Notes for your technician</label><textarea id="bNotes" rows="3" placeholder="e.g. Prefer short almond shape, no glitter…"></textarea></div>
            <div id="bookError" class="alert alert-error" style="display:none"></div>
            <button class="btn btn-primary btn-full" id="confirmBtn" onclick="submitBooking()">Confirm appointment</button>
          </div>
          <div class="appt-summary" id="apptSummary"></div>
        </div>
      </div>

      <!-- STEP 4 -->
      <div id="step4" class="book-step" style="display:none">
        <div class="success-screen">
          <div class="success-icon">💎</div>
          <h3>You're all set!</h3>
          <p>Your appointment at <?= esc($bizName) ?> has been requested. We'll confirm by SMS or phone shortly.</p>
          <div class="success-detail" id="successDetail"></div>
          <button class="btn btn-secondary" onclick="resetBooking()">Book another appointment</button>
        </div>
      </div>

    </div><!-- /booking-card -->
  </div>
</section>

<!-- FOOTER -->
<footer class="footer">
  <div class="container footer-inner">
    <div>
      <p class="footer-brand">💎 <?= esc($bizName) ?></p>
      <p class="footer-sub">Diamond-quality nail care — manicures, pedicures, gel and nail art in a premium spa setting.</p>
    </div>
    <div>
      <p class="footer-heading">Visit &amp; Contact</p>
      <?php if ($bizAddr): ?><p>📍 <?= esc($bizAddr) ?></p><?php endif; ?>
      <?php if ($bizPhone): ?><p>📞 <?= esc($bizPhone) ?></p><?php endif; ?>
      <?php if ($bizEmail): ?><p>✉️ <?= esc($bizEmail) ?></p><?php endif; ?>
    </div>
    <div>
      <p class="footer-heading">Book online</p>
      <a href="#booking" class="btn btn-gold" style="margin-top:8px">Reserve a session</a>
    </div>
  </div>
  <div class="footer-copy">
    © <?= date('Y') ?> <?= esc($bizName) ?>. All rights reserved.
    <a href="<?= BASE_PATH ?>/admin/" style="color:rgba(255,255,255,.25);font-size:11px">Admin</a>
  </div>
</footer>

<!-- Inject base path for JS fetch calls -->
<script>
  window.BASE_PATH = '<?= BASE_PATH ?>';
  window.APP_URL   = '<?= APP_URL ?>';
</script>
<script src="<?= BASE_PATH ?>/assets/js/public.js"></script>
</body>
</html>
