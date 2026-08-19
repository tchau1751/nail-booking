<?php
require_once __DIR__ . '/includes/functions.php';

$business = get_business_settings();
$services = get_active_services();
$staffList = get_active_staff();
$token = csrf_token();

$heroImg = $business['hero_image_url'] ?: 'https://images.pexels.com/photos/13068361/pexels-photo-13068361.jpeg?auto=compress&cs=tinysrgb&w=1800';
$aboutImg = $business['about_image_url'] ?: 'https://images.pexels.com/photos/7195809/pexels-photo-7195809.jpeg?auto=compress&cs=tinysrgb&w=1000';
$aboutFloatImg = $business['about_float_image_url'] ?: 'https://images.pexels.com/photos/6135675/pexels-photo-6135675.jpeg?auto=compress&cs=tinysrgb&w=400';
$heroCardImg = $business['hero_card_image_url'] ?: 'https://images.pexels.com/photos/5870539/pexels-photo-5870539.jpeg?auto=compress&cs=tinysrgb&w=500';

$galleryRows = get_db()->query('SELECT image_url, alt_text, layout_class FROM gallery_images ORDER BY sort_order ASC, id ASC')->fetchAll();
$gallery = array_map(fn($r) => ['img' => $r['image_url'], 'class' => $r['layout_class'], 'alt' => $r['alt_text']], $galleryRows);

// Hero carousel: the hero image first, then a few gallery photos for variety.
$heroCarousel = array_values(array_unique(array_merge(
    [$heroImg],
    array_slice(array_column($gallery, 'img'), 0, 4)
)));

$testimonials = [
  ['name' => 'Ashley R.', 'text' => 'The most relaxing manicure experience in Layton. The attention to detail on my nail art was incredible and the studio itself feels so elevated.'],
  ['name' => 'Megan T.', 'text' => 'My gel pedicure lasted almost a month with zero chipping. The chairs, the lighting, the little touches — everything feels genuinely premium.'],
  ['name' => 'Priya K.', 'text' => 'Booked online in under a minute and got a text confirmation right away. Front desk remembered my name at check-in. This is how it should be.'],
];

function initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $letters = array_map(fn($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2));
    return mb_strtoupper(implode('', $letters));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($business['business_name']) ?> — Premium Nail Studio in Layton, UT</title>
<meta name="description" content="Reserve your nail session at <?= e($business['business_name']) ?>, a premium nail studio in Layton, Utah. Manicures, pedicures, gel, nail art, and acrylic full sets.">
<link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/img/favicon-16.png">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<!-- Bootstrap (loaded before our own stylesheet so our styles still win everywhere they're defined — this keeps Bootstrap's grid available for the gallery section without changing the look of the rest of the site) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="/assets/css/style-v4.css">
</head>
<body>

<nav class="navbar">
  <div class="container">
    <a href="#" class="brand">
      <?php if (!empty($business['logo_url'])): ?>
        <img src="<?= e($business['logo_url']) ?>" alt="<?= e($business['business_name']) ?>" style="height:52px;width:auto;">
      <?php else: ?>
        <span class="brand-mark">D</span>
        <span class="brand-name"><?= e($business['business_name']) ?><span>Nail Studio &amp; Spa</span></span>
      <?php endif; ?>
    </a>
    <div class="nav-links">
      <a href="#services">Services</a>
      <a href="/menu.php">Menu</a>
      <a href="#gallery">Gallery</a>
      <a href="#about">About</a>
      <a href="#booking">Book Now</a>
    </div>
    <div class="nav-actions">
      <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $business['business_phone'])) ?>" class="nav-phone">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        <?= e($business['business_phone']) ?>
      </a>
      <a href="#booking" class="btn btn-primary">Book Your Appointment</a>
      <button class="nav-toggle" aria-label="Open menu" onclick="document.querySelector('.mobile-menu').classList.add('open')">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
      </button>
    </div>
  </div>
</nav>

<div class="mobile-menu">
  <button class="mobile-menu-close" aria-label="Close menu">&times;</button>
  <a href="#services">Services</a>
  <a href="/menu.php">Menu</a>
  <a href="#gallery">Gallery</a>
  <a href="#about">About</a>
  <a href="#booking">Book Now</a>
</div>

<!-- ================= HERO ================= -->
<header class="hero">
  <div class="hero-bg">
    <?php foreach ($heroCarousel as $i => $img): ?>
      <div class="hero-bg-slide<?= $i === 0 ? ' active' : '' ?>" style="background-image:url('<?= e($img) ?>')" data-hero-slide="<?= $i ?>"></div>
    <?php endforeach; ?>
  </div>
  <?php if (count($heroCarousel) > 1): ?>
  <div class="hero-carousel-dots">
    <?php foreach ($heroCarousel as $i => $img): ?>
      <button type="button" class="hero-carousel-dot<?= $i === 0 ? ' active' : '' ?>" data-hero-dot="<?= $i ?>" aria-label="Show photo <?= $i + 1 ?>"></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="container">
    <div class="hero-content">
      <div class="eyebrow">Layton's Premium Nail Studio</div>
      <h1>Where every <em>detail</em><br>is a work of art.</h1>
      <p class="hero-sub">
        From refined gel manicures to hand-painted nail art, <?= e($business['business_name']) ?> blends
        skilled craftsmanship with a calm, elevated studio experience — reserved just for you.
      </p>
      <div class="hero-cta">
        <a href="#booking" class="btn btn-primary">Reserve Your Nail Session</a>
        <a href="#services" class="btn btn-secondary" style="color:#fff;border-color:rgba(255,255,255,0.4)">View Services</a>
      </div>
      <div class="hero-stats">
        <div class="hero-stat"><strong>12+</strong><span>Years of Craft</span></div>
        <div class="hero-stat"><strong>4.8★</strong><span>Client Rating</span></div>
        <div class="hero-stat"><strong>6</strong><span>Signature Services</span></div>
      </div>
    </div>
    <div class="hero-card">
      <img src="<?= e($heroCardImg) ?>" alt="Fresh gel manicure detail">
      <div class="hero-card-badge">
        <span class="stars">★★★★★</span>
        <div><small>"An absolutely elevated experience."</small></div>
      </div>
      <div class="hero-float">
        <strong>Book in 60s</strong>
        <span>Instant Confirmation</span>
      </div>
    </div>
  </div>
</header>

<!-- ================= SERVICES ================= -->
<section class="section bg-ivory" id="services">
  <div class="container">
    <div class="section-header center" data-reveal>
      <div class="eyebrow" style="justify-content:center">Nail Services</div>
      <h2>Signature treatments, tailored to you</h2>
      <p>Every service begins with a consultation and ends with a finish that lasts. Explore our menu and reserve your visit in moments.</p>
    </div>

    <?php if (empty($services)): ?>
      <div class="services-empty">Our service menu is being updated — please call <?= e($business['business_phone']) ?> to book directly.</div>
    <?php else: ?>
      <div class="services-grid">
        <?php foreach ($services as $i => $svc): ?>
          <div class="service-card" data-reveal data-reveal-delay="<?= $i % 3 + 1 ?>">
            <div class="service-media">
              <img src="<?= e($svc['image_url']) ?>" alt="<?= e($svc['name']) ?>" loading="lazy">
              <span class="service-category"><?= e($svc['category']) ?></span>
            </div>
            <div class="service-body">
              <h3><?= e($svc['name']) ?></h3>
              <p><?= e($svc['description']) ?></p>
              <div class="service-meta">
                <span class="service-duration">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                  <?= e(format_duration((int)$svc['duration_minutes'])) ?>
                </span>
                <a href="#booking" class="service-book-link" data-book-service="<?= (int)$svc['id'] ?>">
                  Book Now
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<div class="container"><div class="section-divider"></div></div>

<!-- ================= GALLERY ================= -->
<style>
  /* Scoped to the gallery section — sits on top of Bootstrap's grid without
     touching the rest of the page's look. */
  #gallery .gallery-lightbox-link { display:block; border-radius:18px; overflow:hidden; aspect-ratio:1/1; cursor:zoom-in; }
  #gallery .row .col-12.col-md-8 .gallery-lightbox-link { aspect-ratio:16/9; }
  #gallery .gallery-lightbox-img { object-fit:cover; transition:transform .35s ease; }
  #gallery .gallery-lightbox-link:hover .gallery-lightbox-img { transform:scale(1.04); }

  /* Simple, self-contained lightbox — no third-party library. */
  .simple-lightbox { display:none; position:fixed; inset:0; z-index:2000; background:rgba(10,8,7,0.92); align-items:center; justify-content:center; padding:40px; }
  .simple-lightbox.open { display:flex; }
  .simple-lightbox img { max-width:90vw; max-height:85vh; width:auto; height:auto; object-fit:contain; border-radius:8px; box-shadow:0 20px 60px rgba(0,0,0,0.5); }
  .simple-lightbox-close, .simple-lightbox-prev, .simple-lightbox-next {
    position:fixed; background:rgba(255,255,255,0.12); border:none; color:#fff; cursor:pointer;
    display:flex; align-items:center; justify-content:center; border-radius:50%; transition:background .2s ease;
  }
  .simple-lightbox-close:hover, .simple-lightbox-prev:hover, .simple-lightbox-next:hover { background:rgba(255,255,255,0.25); }
  .simple-lightbox-close { top:20px; right:20px; width:44px; height:44px; font-size:22px; }
  .simple-lightbox-prev, .simple-lightbox-next { top:50%; transform:translateY(-50%); width:52px; height:52px; font-size:26px; }
  .simple-lightbox-prev { left:20px; }
  .simple-lightbox-next { right:20px; }
  @media (max-width: 640px) {
    .simple-lightbox-prev, .simple-lightbox-next { width:40px; height:40px; font-size:20px; }
  }
</style>
<section class="section-tight bg-ivory" id="gallery">
  <div class="container">
    <div class="section-header center" data-reveal>
      <div class="eyebrow" style="justify-content:center">Studio Gallery</div>
      <h2>A closer look at our craft</h2>
      <p>A glimpse inside the studio — our space, our finishes, our details.</p>
    </div>
    <?php if (!empty($gallery)): ?>
    <div class="container-fluid px-0" data-reveal>
      <div class="row g-3">
        <?php foreach ($gallery as $i => $g):
          $colClass = $g['class'] === 'wide' ? 'col-12 col-md-8' : ($g['class'] === 'tall' ? 'col-6 col-md-4' : 'col-6 col-md-4');
        ?>
          <div class="<?= $colClass ?>">
            <a href="<?= e($g['img']) ?>" data-lightbox-index="<?= $i ?>" class="gallery-lightbox-link js-lightbox-trigger">
              <img src="<?= e($g['img']) ?>" alt="<?= e($g['alt'] ?: $business['business_name'] . ' studio detail') ?>" loading="lazy" class="img-fluid w-100 h-100 gallery-lightbox-img">
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ================= ABOUT ================= -->
<section class="section bg-pearl" id="about">
  <div class="container">
    <div class="about-grid">
      <div class="about-visual" data-reveal>
        <img class="main" src="<?= e($aboutImg) ?>" alt="Inside <?= e($business['business_name']) ?>">
        <div class="float-card">
          <img src="<?= e($aboutFloatImg) ?>" alt="Manicure detail">
          <strong>Est. Craft</strong>
          <span>Skilled technicians, refined technique</span>
        </div>
      </div>
      <div class="about-body" data-reveal data-reveal-delay="1">
        <div class="eyebrow">About the Studio</div>
        <h2>A premium nail studio built around you</h2>
        <p>
          <?= e($business['business_name']) ?> was founded on a simple idea: a nail appointment should feel like
          a genuine escape, not a transaction. Every visit is thoughtfully paced, every tool sanitized to the highest
          standard, and every finish held to the same exacting eye for detail.
        </p>
        <p>
          Located in the heart of Layton, we welcome clients from Clearfield, Kaysville, Farmington, Roy, Ogden,
          and Syracuse who are looking for more than a quick polish change — they're looking for a ritual.
        </p>
        <ul class="about-list">
          <li><span class="check">✓</span> Hospital-grade sanitation on every tool, every visit</li>
          <li><span class="check">✓</span> Premium polish &amp; gel brands, hundreds of shades</li>
          <li><span class="check">✓</span> Licensed, experienced nail technicians</li>
          <li><span class="check">✓</span> Calm, elevated studio atmosphere</li>
        </ul>
        <a href="#booking" class="btn btn-primary">Schedule Your Visit</a>
      </div>
    </div>
  </div>
</section>

<!-- ================= TESTIMONIALS ================= -->
<section class="section-tight bg-ivory">
  <div class="container">
    <div class="section-header center" data-reveal>
      <div class="eyebrow" style="justify-content:center">Client Love</div>
      <h2>Trusted by clients across Davis County</h2>
    </div>
    <div class="testimonial-strip">
      <?php foreach ($testimonials as $i => $t): ?>
        <div class="testimonial-card" data-reveal data-reveal-delay="<?= $i + 1 ?>">
          <div class="testimonial-stars">★★★★★</div>
          <p>"<?= e($t['text']) ?>"</p>
          <div class="testimonial-author">
            <div class="testimonial-avatar"><?= e(initials($t['name'])) ?></div>
            <div><strong><?= e($t['name']) ?></strong><span>Verified Client</span></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= BOOKING ================= -->
<section class="section booking-section" id="booking">
  <div class="container">
    <div class="section-header" data-reveal>
      <div class="eyebrow">Reserve Your Visit</div>
      <h2>Book your appointment</h2>
      <p>Choose your service, pick a time that works, and you're set — instant confirmation by email and text.</p>
    </div>

    <div class="booking-wrap">
      <div class="booking-notice-card" data-reveal>
        <h3>What to expect</h3>
        <div class="booking-info-row">
          <span class="booking-info-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg>
          </span>
          <div><strong>Arrive 10 minutes early</strong><?= e($business['booking_notice'] ?: 'We hold reservations for 15 minutes past your scheduled time.') ?></div>
        </div>
        <div class="booking-info-row">
          <span class="booking-info-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M4 4h16v16H4z"/><path d="M4 9h16M9 4v16"/></svg>
          </span>
          <div><strong>Instant confirmation</strong>You'll receive a text and email as soon as your visit is booked.</div>
        </div>
        <div class="booking-info-row">
          <span class="booking-info-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          </span>
          <div><strong><?= e($business['business_name']) ?></strong><?= e($business['business_address']) ?></div>
        </div>
        <div class="booking-info-row">
          <span class="booking-info-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          </span>
          <div><strong>Questions?</strong>Call or text <?= e($business['business_phone']) ?></div>
        </div>
      </div>

      <div class="booking-card" data-reveal data-reveal-delay="1">
        <div class="booking-steps">
          <div class="booking-step-dot active" data-step-dot="0">1</div>
          <div class="booking-step-line" data-step-line="0"></div>
          <div class="booking-step-dot" data-step-dot="1">2</div>
          <div class="booking-step-line" data-step-line="1"></div>
          <div class="booking-step-dot" data-step-dot="2">3</div>
          <div class="booking-step-line" data-step-line="2"></div>
          <div class="booking-step-dot" data-step-dot="3">✓</div>
        </div>

        <div id="booking-alert" class="booking-alert"></div>

        <form id="booking-form" novalidate>
          <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
          <input type="hidden" id="service_id" name="service_id" value="">

          <!-- Step 1: service -->
          <div class="booking-step active">
            <h3 style="font-size:19px;margin-bottom:4px;">Choose your service</h3>
            <p style="font-size:13.5px;color:var(--ink-soft,#7a6a5f);margin:0 0 16px;">Select up to 3 services to book together, back-to-back.</p>
            <div class="form-field" data-field="service_id" style="margin-bottom:6px;">
              <div class="service-pick-grid">
                <?php foreach ($services as $svc): ?>
                  <div class="service-pick"
                       data-service-id="<?= (int)$svc['id'] ?>"
                       data-service-name="<?= e($svc['name']) ?>"
                       data-service-price="<?= e(format_price((float)$svc['price'])) ?>"
                       data-service-price-raw="<?= (float)$svc['price'] ?>"
                       data-service-duration="<?= (int)$svc['duration_minutes'] ?>">
                    <?php if (!empty($svc['image_url'])): ?>
                      <img class="service-pick-thumb" src="<?= e($svc['image_url']) ?>" alt="" loading="lazy">
                    <?php endif; ?>
                    <div>
                      <div class="service-pick-name"><?= e($svc['name']) ?></div>
                      <div class="service-pick-meta"><?= e(format_duration((int)$svc['duration_minutes'])) ?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="form-error">Please select at least one service.</div>
            </div>
            <div id="service-pick-summary" style="display:none;font-size:13.5px;color:var(--ink-soft,#7a6a5f);margin-bottom:14px;"></div>
            <div class="booking-nav" style="justify-content:flex-end;">
              <button type="button" class="btn btn-primary" data-next>Continue</button>
            </div>
          </div>

          <!-- Step 2: date & time -->
          <div class="booking-step">
            <h3 style="font-size:19px;margin-bottom:16px;">Pick a date &amp; time</h3>
            <div class="form-grid">
              <div class="form-field" data-field="appointment_date">
                <label for="appointment_date">Date</label>
                <input type="date" id="appointment_date" required>
                <div class="form-error">Please choose a date.</div>
              </div>
              <div class="form-field" data-field="appointment_time">
                <label for="appointment_time">Time</label>
                <select id="appointment_time" required>
                  <option value="">Select a time</option>
                  <?php
                  foreach (['09:30','10:15','11:00','11:45','12:30','13:15','14:00','14:45','15:30','16:15','17:00','17:45'] as $t) {
                      $label = date('g:i A', strtotime($t));
                      echo '<option value="' . e($t) . '">' . e($label) . '</option>';
                  }
                  ?>
                </select>
                <div class="form-error">Please choose a time.</div>
              </div>
            </div>
            <?php if (!empty($staffList)): ?>
            <div class="form-field full" data-field="staff_id">
              <label>Preferred Technician (optional)</label>
              <div id="staff-per-service" style="display:flex;flex-direction:column;gap:10px;"></div>
            </div>
            <?php endif; ?>
            <div class="booking-nav">
              <button type="button" class="btn btn-secondary" data-prev>Back</button>
              <button type="button" class="btn btn-primary" data-next>Continue</button>
            </div>
          </div>

          <!-- Step 3: contact info -->
          <div class="booking-step">
            <h3 style="font-size:19px;margin-bottom:16px;">Your details</h3>
            <div class="form-field" data-field="full_name">
              <label for="full_name">Full name</label>
              <input type="text" id="full_name" placeholder="Jane Doe" required>
              <div class="form-error">Please enter your name.</div>
            </div>
            <div class="form-grid">
              <div class="form-field" data-field="email">
                <label for="email">Email</label>
                <input type="email" id="email" placeholder="jane@email.com">
                <div class="form-error">Add an email or phone number.</div>
              </div>
              <div class="form-field" data-field="phone">
                <label for="phone">Phone (for SMS confirmation)</label>
                <input type="tel" id="phone" placeholder="(801) 555-0123">
              </div>
            </div>
            <div class="form-field" data-field="dob">
              <label for="dob">Date of Birth (optional)</label>
              <input type="date" id="dob">
            </div>
            <div class="form-field full" data-field="notes">
              <label for="notes">Notes (optional)</label>
              <textarea id="notes" rows="3" placeholder="Anything we should know before your visit?"></textarea>
            </div>

            <div class="booking-summary">
              <div class="booking-summary-row"><span>Service</span><span id="summary-service">—</span></div>
              <?php if (!empty($staffList)): ?>
              <div class="booking-summary-row"><span>Technician</span><span id="summary-staff">No preference</span></div>
              <?php endif; ?>
            </div>

            <div class="booking-nav">
              <button type="button" class="btn btn-secondary" data-prev>Back</button>
              <button type="submit" class="btn btn-primary" data-submit>Confirm Reservation</button>
            </div>
          </div>

          <!-- Step 4: confirmation -->
          <div class="booking-step">
            <div class="confirmation-view">
              <div class="confirmation-badge">✓</div>
              <h3>You're all set, <span id="confirm-name"></span>!</h3>
              <p>A confirmation has been sent by email and text. We can't wait to see you.</p>
              <div class="booking-summary" style="text-align:left;">
                <div class="booking-summary-row"><span>Confirmation #</span><span id="confirm-id"></span></div>
                <div class="booking-summary-row"><span>Service</span><span id="confirm-service"></span></div>
                <div class="booking-summary-row"><span>Duration</span><span id="confirm-duration"></span></div>
                <?php if (!empty($staffList)): ?>
                <div class="booking-summary-row" id="confirm-staff-row" style="display:none;"><span>Technician</span><span id="confirm-staff"></span></div>
                <?php endif; ?>
              </div>
              <a href="/booking-login.php" class="btn btn-primary btn-block">Done</a>
            </div>
          </div>
        </form>
      </div>
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
        <p><a href="#services">Services</a></p>
        <p><a href="/menu.php">Menu</a></p>
        <p><a href="#gallery">Gallery</a></p>
        <p><a href="#about">About</a></p>
        <p><a href="#booking">Book Now</a></p>
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

<script>
(function () {
  var slides = document.querySelectorAll('.hero-bg-slide');
  var dots = document.querySelectorAll('.hero-carousel-dot');
  if (slides.length <= 1) return;
  var current = 0;

  function goTo(index) {
    current = (index + slides.length) % slides.length;
    slides.forEach(function (s, i) { s.classList.toggle('active', i === current); });
    dots.forEach(function (d, i) { d.classList.toggle('active', i === current); });
  }

  dots.forEach(function (dot, i) {
    dot.addEventListener('click', function () { goTo(i); resetTimer(); });
  });

  var timer;
  function resetTimer() {
    clearInterval(timer);
    timer = setInterval(function () { goTo(current + 1); }, 5500);
  }
  resetTimer();
})();
</script>

<div class="simple-lightbox" id="simple-lightbox" role="dialog" aria-modal="true" aria-label="Gallery photo viewer">
  <button type="button" class="simple-lightbox-close" id="simple-lightbox-close" aria-label="Close">&times;</button>
  <button type="button" class="simple-lightbox-prev" id="simple-lightbox-prev" aria-label="Previous photo">&#8249;</button>
  <img id="simple-lightbox-img" src="" alt="">
  <button type="button" class="simple-lightbox-next" id="simple-lightbox-next" aria-label="Next photo">&#8250;</button>
</div>
<script>
(function () {
  var images = <?= json_encode(array_map(fn($g) => $g['img'], $gallery)) ?>;
  var current = 0;
  var lightbox = document.getElementById('simple-lightbox');
  var img = document.getElementById('simple-lightbox-img');

  function show(index) {
    if (!images.length) return;
    current = (index + images.length) % images.length;
    img.src = images[current];
    lightbox.classList.add('open');
  }
  function close() {
    lightbox.classList.remove('open');
    img.src = '';
  }

  document.querySelectorAll('.js-lightbox-trigger').forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      show(parseInt(link.dataset.lightboxIndex, 10) || 0);
    });
  });

  document.getElementById('simple-lightbox-close').addEventListener('click', close);
  document.getElementById('simple-lightbox-prev').addEventListener('click', function () { show(current - 1); });
  document.getElementById('simple-lightbox-next').addEventListener('click', function () { show(current + 1); });
  lightbox.addEventListener('click', function (e) { if (e.target === lightbox) close(); });
  document.addEventListener('keydown', function (e) {
    if (!lightbox.classList.contains('open')) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowLeft') show(current - 1);
    if (e.key === 'ArrowRight') show(current + 1);
  });
})();
</script>
<script>
  const STAFF_LIST = <?= json_encode(array_map(fn($s) => ['id' => (int) $s['id'], 'name' => $s['full_name'], 'title' => $s['title']], $staffList)) ?>;
</script>
<script src="/assets/js/main-v8.js"></script>
</body>
</html>
