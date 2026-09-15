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

// Back-office screens also need the salon's admin password, on top of the role
// above: a till left signed in as the owner should not open Staff for whoever
// walks up. A new back-office screen goes into ADMIN_LOCKED_PAGES.
if (in_array(basename($_SERVER['SCRIPT_NAME'], '.php'), ADMIN_LOCKED_PAGES, true)) {
    requireAdminUnlock();
}

// The whole page is buffered so layout_end can drop the token into every form.
ob_start();

$pageTitle = $pageTitle ?? 'POS';
$activeNav = $activeNav ?? '';
$fullBleed = $fullBleed ?? false;   // register screen fills the viewport, no page scroll
// The top bar uses the tabs salons know from other nail POS systems, so moving
// over needs no retraining: icon, label, link from the site root, and the least
// trusted role that sees the tab. Each page still enforces its own $requireRole.
$isManager = hasRole('manager');
$navTabs = [
    'queue'       => ['🪑', 'Sign-in list', 'pos/queue.php',        'technician'],
    'register'    => ['💅', 'Checkout',     'pos/index.php',        'cashier'],
    // Gift cards, stamp cards and points. The front desk cannot open gift
    // cards, so for them the tab opens on the stamp cards.
    'rewards'     => ['🎁', 'Gift-card',    $isManager ? 'pos/giftcards.php' : 'pos/stamps.php', 'front_desk'],
    'appointment' => ['📅', 'Appointment',  'pos/appointments.php', 'front_desk'],
    'clients'     => ['👥', 'Customer',     'pos/clients.php',      'front_desk'],
];
$navTabs = array_filter($navTabs, function ($t) { return hasRole($t[3]); });
// Everything else is the back office: a manager's, behind the admin password.
$navAdmin = !$isManager ? [] : [
    'services'  => ['💅', 'Services',  'pos/services.php'],
    'products'  => ['📦', 'Products',  'pos/products.php'],
    'staff'     => ['👤', 'Staff',     'pos/staff.php'],
    'settings'  => ['⚙️', 'Settings',  'pos/settings.php'],
    'devices'   => ['📱', 'Devices',   'pos/devices.php'],
    'sales'     => ['🧾', 'Sales',     'pos/sales.php'],
    'reports'   => ['📊', 'Reports',   'pos/reports.php'],
    'payroll'   => ['💵', 'Payroll',   'pos/payroll.php'],
    'marketing' => ['📣', 'Marketing', 'pos/marketing.php'],
    'feedback'  => ['⭐', 'Feedback',  'pos/feedback.php'],
    'lookbook'  => ['🎨', 'Designs',   'pos/lookbook.php'],
    'smslog'    => ['💬', 'SMS log',   'studio/sms-log.php'],
];
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
// Five tabs for the day's work and Admin for the back office. Fifteen screens
// will not fit across a tablet's bar, so the back office folds into one menu
// and nothing hides behind a sideways swipe.
$adminActive = isset($navAdmin[$activeNav]) || $activeNav === 'admin';
$adminOpen   = $navAdmin && adminUnlocked();
?>
  <nav class="topnav">
    <?php foreach ($navTabs as $key => [$icon, $label, $href]): ?>
      <a href="<?= BASE_PATH ?>/<?= $href ?>" class="<?= $activeNav === $key ? 'active' : '' ?>">
        <span class="ico"><?= $icon ?></span><span class="lbl"><?= $label ?></span>
      </a>
    <?php endforeach; ?>
    <?php if ($navAdmin): ?>
      <details class="navmore">
        <summary class="<?= $adminActive ? 'active' : '' ?>"
                 title="<?= $adminOpen ? 'The admin screens are open' : 'The admin screens ask for the admin password' ?>">
          <span class="ico"><?= $adminOpen ? '🔓' : '🔒' ?></span><span class="lbl">Admin</span>
        </summary>
        <div class="navmore-panel">
          <?php if ($adminOpen): ?>
            <form method="post" action="<?= BASE_PATH ?>/pos/unlock.php" style="margin:0 0 6px">
              <input type="hidden" name="action" value="lock">
              <button class="btn btn-light btn-sm" type="submit" style="width:100%">🔒 Lock the admin screens</button>
            </form>
          <?php endif; ?>
          <?php foreach ($navAdmin as $key => [$icon, $label, $href]): ?>
            <a href="<?= BASE_PATH ?>/<?= $href ?>" class="<?= $activeNav === $key ? 'active' : '' ?>">
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
    <!-- "Hi" and the role, never the name: the guest can see this screen. The
         name still prints on the receipt as the cashier. -->
    <span class="who">Hi <?= e(roleLabel($admin['role'] ?? '')) ?></span>
    <a class="btn btn-ghost" href="<?= BASE_PATH ?>/admin/logout.php" title="Sign out">Exit</a>
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
