<?php
require_once __DIR__ . '/pos.php';
requireTillLogin();
// Role gate runs here, before a single byte of HTML, so the 403 page
// can set its own status code and replace the screen entirely. A page that
// does not say who it is for is treated as a manager's page: the mistake then
// shows up as a locked screen, never as an open one.
requireRole($requireRole ?? 'manager');
$admin = currentAdmin();

if (!posInstalled() && basename($_SERVER['SCRIPT_NAME']) !== 'install.php') {
    header('Location: ' . BASE_PATH . '/pos/install.php');
    exit;
}

// Every POST to a till page passes through here before the page's own handler
// runs, so no screen can forget the check. The matching hidden field is added
// to each form on the way out in layout_end — one place to enforce it, one
// place to supply it, and nothing to remember when a new page is written.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !posCsrfValid($_POST['_csrf'] ?? null)) {
    http_response_code(419);
    echo '<!doctype html><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Session expired</title>'
       . '<div style="font:16px/1.6 system-ui;max-width:34em;margin:12vh auto;padding:0 24px">'
       . '<h1 style="font-size:22px">That form went stale</h1>'
       . '<p>The till was signed out or left sitting too long, so the form was not accepted '
       . 'and nothing was changed. Open the page again and redo it.</p>'
       . '<p><a href="' . BASE_PATH . '/pos/">Back to the register</a></p></div>';
    exit;
}

// The whole page is buffered so layout_end can drop the token into every form.
ob_start();

$pageTitle = $pageTitle ?? 'POS';
$activeNav = $activeNav ?? '';
$fullBleed = $fullBleed ?? false;   // register screen fills the viewport, no page scroll
// The least trusted role that sees each screen. Anything not listed is a
// manager's screen; each page enforces the same minimum with $requireRole.
$navMin = [
    'register' => 'cashier',
    'queue'    => 'technician',
    'clients'  => 'front_desk',
    'rewards'  => 'front_desk',
];
// Stamp cards, points and gift cards are one thing to a guest — what coming
// back earns them — so they share one Rewards tab, with sub-tabs below the bar.
$navItems  = [
    'register'  => ['💅', 'Register',  'index.php'],
    'queue'     => ['🪑', 'Queue',     'queue.php'],
    'clients'   => ['👥', 'Clients',   'clients.php'],
    'sales'     => ['🧾', 'Sales',     'sales.php'],
    'rewards'   => ['🎁', 'Rewards',   'stamps.php'],
    'services'  => ['💅', 'Services',  'services.php'],
    'products'  => ['📦', 'Products',  'products.php'],
    'reports'   => ['📊', 'Reports',   'reports.php'],
    'payroll'   => ['💵', 'Payroll',   'payroll.php'],
    'marketing' => ['📣', 'Marketing', 'marketing.php'],
    'feedback'  => ['⭐', 'Feedback',  'feedback.php'],
    'lookbook'  => ['🎨', 'Designs',   'lookbook.php'],
    'settings'  => ['⚙️', 'Settings',  'settings.php'],
    'staff'     => ['👤', 'Staff',     'staff.php'],
    'devices'   => ['📱', 'Devices',   'devices.php'],
];
foreach ($navItems as $k => $v) {
    if (!hasRole($navMin[$k] ?? 'manager')) unset($navItems[$k]);
}
// Which station this is, when it is one of the salon's registered devices —
// with two tills side by side, staff need to see which one they are on.
$station = deviceFromCookie();
$stationName = ($station && (int)$station['is_active'] === 1 && (int)$station['tenant_id'] === tenantId())
    ? $station['name'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no,viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="theme-color" content="<?= e(posThemeColor()) ?>">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="POS">
<title><?= e($pageTitle) ?> — Diamond Nail POS</title>
<link rel="manifest" href="<?= BASE_PATH ?>/pos/manifest.php">
<link rel="icon" href="<?= BASE_PATH ?>/pos/assets/icons/favicon-32.png" sizes="32x32">
<link rel="apple-touch-icon" href="<?= BASE_PATH ?>/pos/assets/icons/icon-180.png">
<link rel="stylesheet" href="<?= BASE_PATH ?>/pos/assets/pos.css?v=<?= @filemtime(__DIR__ . '/../assets/pos.css') ?>">
</head>
<body data-theme="<?= e(posTheme()) ?>" class="<?= $fullBleed ? 'is-fullbleed' : '' ?>">

<header class="topbar">
  <div class="brand">💎 <span>Diamond POS</span></div>
<?php
// Fifteen screens will not fit across a tablet's top bar — on the salon's own
// device the strip needs 1544px and has 575, so ten of them sat off the edge
// behind a sideways swipe nobody thinks to try. The five used all day stay out
// front; the rest live one tap away under More, which at least announces that
// there is more.
$navFront = ['register', 'queue', 'clients', 'sales', 'rewards'];
$navPrimary = array_intersect_key($navItems, array_flip($navFront));
$navRest    = array_diff_key($navItems, $navPrimary);
$restActive = isset($navRest[$activeNav]);
?>
  <nav class="topnav">
    <?php foreach ($navPrimary as $key => [$icon, $label, $href]): ?>
      <a href="<?= BASE_PATH ?>/pos/<?= $href ?>" class="<?= $activeNav === $key ? 'active' : '' ?>">
        <span class="ico"><?= $icon ?></span><span class="lbl"><?= $label ?></span>
      </a>
    <?php endforeach; ?>
    <?php if ($navRest): ?>
      <details class="navmore">
        <summary class="<?= $restActive ? 'active' : '' ?>">
          <span class="ico">☰</span><span class="lbl">More</span>
        </summary>
        <div class="navmore-panel">
          <?php foreach ($navRest as $key => [$icon, $label, $href]): ?>
            <a href="<?= BASE_PATH ?>/pos/<?= $href ?>" class="<?= $activeNav === $key ? 'active' : '' ?>">
              <span class="ico"><?= $icon ?></span><span><?= $label ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endif; ?>
  </nav>
  <div class="topright">
    <?php if ($stationName): ?>
      <span class="station" title="This device"
            style="font-weight:700;font-size:13px;opacity:.85;white-space:nowrap"><?= e($stationName) ?></span>
    <?php endif; ?>
    <span class="clock" id="posClock"></span>
    <!-- The signed-in name is not shown at the till — it takes room on the
         tablet's top bar and the guest can see it. It still prints on the
         receipt as the cashier, and the title below says who is signed in. -->
    <?php if (hasRole('manager')): ?>
      <a class="btn btn-ghost" href="<?= BASE_PATH ?>/studio/sms-log.php">SMS log</a>
    <?php endif; ?>
    <a class="btn btn-ghost" href="<?= BASE_PATH ?>/admin/logout.php"
       title="Signed in as <?= e($admin['name'] ?? '') ?> (<?= e(roleLabel($admin['role'] ?? '')) ?>)">Sign out</a>
  </div>
</header>

<main class="<?= $fullBleed ? 'screen' : 'page' ?>">
<?php
// How the salon's account stands, for the people who can do something about it
// — and never on the register itself, where the guest can see the screen.
$account = currentTenant();
if (!$fullBleed && hasRole('manager')):
    if ($account['status'] === 'past_due'): ?>
  <div class="alert alert-err">The salon's subscription payment is past due. The till keeps working for now —
    please settle it before the account is suspended.</div>
<?php elseif ($account['status'] === 'trial' && !empty($account['trial_ends_on'])): ?>
  <div class="alert alert-ok">Trying the <?= e($account['plan_name'] ?? '') ?> plan — the trial ends
    <?= date('F j', strtotime($account['trial_ends_on'])) ?>.</div>
<?php endif;
endif;

// The Rewards tab is three screens; this strip moves between them. Each screen
// still checks its own role — the strip only leaves out what would be refused.
if ($activeNav === 'rewards'):
    $rewardTabs = [
        'stamps'    => ['🎫', 'Stamp cards', 'stamps.php',           'front_desk'],
        'points'    => ['⭐', 'Points',      'points.php',           'front_desk'],
        'giftcards' => ['🎁', 'Gift cards',  'giftcards.php',        'manager'],
        'settings'  => ['⚙️', 'Settings',    'settings.php#rewards', 'manager'],
    ];
    $rewardHere = basename($_SERVER['SCRIPT_NAME'], '.php'); ?>
  <nav class="tabs subtabs" aria-label="Rewards">
    <?php foreach ($rewardTabs as $tabKey => [$tabIcon, $tabLabel, $tabHref, $tabMin]):
      if (!hasRole($tabMin)) continue; ?>
      <a class="tab <?= $rewardHere === $tabKey ? 'active' : '' ?>" href="<?= BASE_PATH ?>/pos/<?= $tabHref ?>"><?= $tabIcon ?> <?= $tabLabel ?></a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>
