<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$admin = currentAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard — Diamond Nail &amp; Spa</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/admin.css?t=<?= time() ?>">
<style>
html, body { overflow: auto !important; }
.main-content { padding: 12px 28px 28px !important; display: block !important; }
.fc { height: auto !important; display: block !important; }
.fc .fc-toolbar { padding: 8px 0 !important; margin-bottom: 6px !important; background: #f5f5f5 !important; border-radius: 12px !important; }
.fc .fc-toolbar-title { font-size: 16px !important; }
.fc .fc-button { padding: 4px 12px !important; font-size: 12px !important; }
.fc .fc-col-header-cell { padding: 6px 2px !important; font-size: 12px !important; }
.fc .fc-daygrid-body { height: auto !important; }
</style>
</head>
<body class="dashboard">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <span class="brand-icon">💎</span>
    <span class="brand-name">Diamond Nail &amp; Spa</span>
  </div>
  <nav class="sidebar-nav">
    <a href="#" data-page="overview"      class="nav-item active"><span class="nav-icon">📊</span> Overview</a>
    <a href="#" data-page="calendar"      class="nav-item"><span class="nav-icon">📅</span> Calendar</a>
    <a href="#" data-page="appointments"  class="nav-item"><span class="nav-icon">📋</span> All Bookings</a>
    <a href="#" data-page="technicians"   class="nav-item"><span class="nav-icon">💆</span> Technicians</a>
    <a href="#" data-page="services"      class="nav-item"><span class="nav-icon">✨</span> Services</a>
    <a href="#" data-page="hours"         class="nav-item"><span class="nav-icon">🕐</span> Business Hours</a>
    <a href="#" data-page="blocked"       class="nav-item"><span class="nav-icon">🚫</span> Blocked Dates</a>
    <a href="#" data-page="sms"           class="nav-item"><span class="nav-icon">💬</span> SMS Log</a>
    <a href="#" data-page="settings"      class="nav-item"><span class="nav-icon">⚙️</span> Settings</a>
  </nav>
  <div class="sidebar-footer">
    <span><?= htmlspecialchars($admin['name']) ?> <small>(<?= htmlspecialchars($admin['role']) ?>)</small></span>
    <a href="<?= BASE_PATH ?>/admin/logout.php" class="logout-btn">Sign out</a>
  </div>
</aside>

<!-- TOPBAR -->
<header class="topbar">
  <button class="menu-toggle" id="menuToggle">☰</button>
  <h2 class="page-title" id="pageTitle">Overview</h2>
  <div class="topbar-right">
    <a href="<?= BASE_PATH ?>/studio/stamp-cards.php" class="btn btn-primary btn-sm" style="text-decoration:none;background:#c9a87d;margin-right:8px;">🎫 Stamp Cards</a>
    <a href="<?= BASE_PATH ?>/" target="_blank" class="btn btn-secondary btn-sm" style="text-decoration:none">🌐 View site</a>
  </div>
</header>

<!-- MAIN -->
<main class="main-content" id="mainContent">
  <div id="pageLoader" class="loader-wrap"><div class="spinner"></div></div>
</main>

<!-- MODAL -->
<div class="modal-overlay" id="modalOverlay" style="display:none">
  <div class="modal" id="modal">
    <button class="modal-close" id="modalClose">✕</button>
    <div id="modalBody"></div>
  </div>
</div>

<!-- Inject XAMPP base path into JS -->
<script>
  window.BASE_PATH = '<?= BASE_PATH ?>';
  window.APP_URL   = '<?= APP_URL ?>';
</script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/admin.js"></script>
</body>
</html>
