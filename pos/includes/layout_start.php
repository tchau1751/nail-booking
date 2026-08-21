<?php
require_once __DIR__ . '/pos.php';
requireTillLogin();
// Role gate runs here, before a single byte of HTML, so the 403 page
// can set its own status code and replace the screen entirely.
if (!empty($requireRole)) requireRole($requireRole);
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
$navMin = [   // minimum role for each screen; everything else is open to staff
    'sales' => 'manager', 'giftcards' => 'manager', 'services' => 'manager',
    'products' => 'manager', 'reports' => 'manager', 'payroll' => 'manager',
    'marketing' => 'manager', 'feedback' => 'manager', 'settings' => 'manager',
    'staff' => 'manager',   // the page itself keeps owners-only actions owner-only
];
$navItems  = [
    'register'  => ['💅', 'Register',  'index.php'],
    'queue'     => ['🪑', 'Queue',     'queue.php'],
    'clients'   => ['👥', 'Clients',   'clients.php'],
    'sales'     => ['🧾', 'Sales',     'sales.php'],
    'stamps'    => ['🎫', 'Stamps',    'stamps.php'],
    'giftcards' => ['🎁', 'Gift cards','giftcards.php'],
    'services'  => ['💅', 'Services',  'services.php'],
    'products'  => ['📦', 'Products',  'products.php'],
    'reports'   => ['📊', 'Reports',   'reports.php'],
    'payroll'   => ['💵', 'Payroll',   'payroll.php'],
    'marketing' => ['📣', 'Marketing', 'marketing.php'],
    'feedback'  => ['⭐', 'Feedback',  'feedback.php'],
    'settings'  => ['⚙️', 'Settings',  'settings.php'],
    'staff'     => ['👤', 'Staff',     'staff.php'],
];
foreach ($navItems as $k => $v) {
    if (isset($navMin[$k]) && !hasRole($navMin[$k])) unset($navItems[$k]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no,viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#3a2a24">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="POS">
<title><?= e($pageTitle) ?> — Diamond Nail POS</title>
<link rel="manifest" href="<?= BASE_PATH ?>/pos/manifest.php">
<link rel="icon" href="<?= BASE_PATH ?>/pos/assets/icons/favicon-32.png" sizes="32x32">
<link rel="apple-touch-icon" href="<?= BASE_PATH ?>/pos/assets/icons/icon-180.png">
<link rel="stylesheet" href="<?= BASE_PATH ?>/pos/assets/pos.css?v=<?= @filemtime(__DIR__ . '/../assets/pos.css') ?>">
</head>
<body class="<?= $fullBleed ? 'is-fullbleed' : '' ?>">

<header class="topbar">
  <div class="brand">💎 <span>Diamond POS</span></div>
  <nav class="topnav">
    <?php foreach ($navItems as $key => [$icon, $label, $href]): ?>
      <a href="<?= BASE_PATH ?>/pos/<?= $href ?>" class="<?= $activeNav === $key ? 'active' : '' ?>">
        <span class="ico"><?= $icon ?></span><span class="lbl"><?= $label ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="topright">
    <span class="clock" id="posClock"></span>
    <!-- The signed-in name is not shown at the till — it takes room on the
         tablet's top bar and the guest can see it. It still prints on the
         receipt as the cashier, and the title below says who is signed in. -->
    <a class="btn btn-ghost" href="<?= BASE_PATH ?>/studio/sms-log.php">SMS log</a>
    <a class="btn btn-ghost" href="<?= BASE_PATH ?>/admin/logout.php"
       title="Signed in as <?= e($admin['name'] ?? '') ?>">Sign out</a>
  </div>
</header>

<main class="<?= $fullBleed ? 'screen' : 'page' ?>">
