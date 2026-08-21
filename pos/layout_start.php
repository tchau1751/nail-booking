<?php
require_once __DIR__ . '/pos.php';
requireLogin();
// Role gate runs here, before a single byte of HTML, so the 403 page
// can set its own status code and replace the screen entirely.
if (!empty($requireRole)) requireRole($requireRole);
$admin = currentAdmin();

if (!posInstalled() && basename($_SERVER['SCRIPT_NAME']) !== 'install.php') {
    header('Location: ' . BASE_PATH . '/pos/install.php');
    exit;
}

$pageTitle = $pageTitle ?? 'POS';
$activeNav = $activeNav ?? '';
$fullBleed = $fullBleed ?? false;   // register screen fills the viewport, no page scroll
$navMin = [   // minimum role for each screen; everything else is open to staff
    'sales' => 'manager', 'giftcards' => 'manager', 'services' => 'manager',
    'products' => 'manager', 'reports' => 'manager', 'payroll' => 'manager',
    'marketing' => 'manager', 'feedback' => 'manager', 'settings' => 'manager',
    'staff' => 'owner',
];
$navItems  = [
    'register'  => ['💅', 'Register',  'index.php'],
    'queue'     => ['🪑', 'Queue',     'queue.php'],
    'clients'   => ['👥', 'Clients',   'clients.php'],
    'sales'     => ['🧾', 'Sales',     'sales.php'],
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
<link rel="stylesheet" href="<?= BASE_PATH ?>/pos/assets/pos.css">
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
    <span class="who"><?= e($admin['name'] ?? '') ?></span>
    <a class="btn btn-ghost" href="<?= BASE_PATH ?>/studio/">Studio</a>
    <a class="btn btn-ghost" href="<?= BASE_PATH ?>/admin/logout.php">Sign out</a>
  </div>
</header>

<div id="installBar" hidden>
  <span>📲 Install the POS on this device for full-screen use.</span>
  <button type="button" class="btn btn-sm">Install</button>
</div>

<main class="<?= $fullBleed ? 'screen' : 'page' ?>">
