<?php
/**
 * Expects $pageTitle, $pageSubtitle (optional), $activeNav to be set before include.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_login();

$admin = current_admin();
$business = get_business_settings();
$pendingCount = get_db()->query("SELECT COUNT(*) c FROM bookings WHERE status = 'pending'")->fetch()['c'];

function nav_active(string $key, string $active): string { return $key === $active ? 'active' : ''; }
function admin_initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $letters = array_map(fn($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2));
    return mb_strtoupper(implode('', $letters)) ?: 'A';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'Dashboard') ?> — Diamond Nails Admin</title>
<link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon-32.png">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/studio/assets/css/admin-v6.css">
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <?php if (!empty($business['logo_url'])): ?>
        <img src="<?= e($business['logo_url']) ?>" alt="<?= e($business['business_name']) ?>" style="height:38px;width:auto;">
      <?php else: ?>
        <span class="brand-mark">D</span>
      <?php endif; ?>
      <div>Diamond Nails<small>Studio Dashboard</small></div>
    </div>
    <nav class="sidebar-nav">
      <a href="/studio/index.php" class="sidebar-link <?= nav_active('dashboard', $activeNav ?? '') ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9"/><path d="M5 10v10h14V10"/></svg>
        Dashboard
      </a>
      <a href="/studio/calendar.php" class="sidebar-link <?= nav_active('calendar', $activeNav ?? '') ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        Calendar
      </a>
      <a href="/studio/checkin.php" class="sidebar-link <?= nav_active('checkin', $activeNav ?? '') ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        Front Desk Check-In
      </a>
      <a href="/studio/bookings.php" class="sidebar-link <?= nav_active('bookings', $activeNav ?? '') ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16M4 12h16M4 19h10"/></svg>
        All Bookings
        <?php if ($pendingCount > 0): ?><span class="badge-count"><?= (int)$pendingCount ?></span><?php endif; ?>
      </a>
      <div class="sidebar-section-label">Manage</div>
      <a href="/studio/clients.php" class="sidebar-link <?= nav_active('clients', $activeNav ?? '') ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Clients
      </a>
      <a href="/studio/staff.php" class="sidebar-link <?= nav_active('staff', $activeNav ?? '') ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.5-7 8-7s8 3 8 7"/></svg>
        Staff
      </a>
      <a href="/studio/services.php" class="sidebar-link <?= nav_active('services', $activeNav ?? '') ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l2.4 7.2H22l-6 4.6 2.3 7.2L12 16.4l-6.3 4.6 2.3-7.2-6-4.6h7.6z"/></svg>
        Services
      </a>
      <a href="/studio/gallery.php" class="sidebar-link <?= nav_active('gallery', $activeNav ?? '') ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M20.4 14.5L16 10l-6.9 6.9M5 21l4.6-4.6"/></svg>
        Gallery
      </a>
      <a href="/studio/promotions.php" class="sidebar-link <?= nav_active('promotions', $activeNav ?? '') ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4z"/></svg>
        Promotions
      </a>
      <a href="/studio/discounts.php" class="sidebar-link <?= nav_active('discounts', $activeNav ?? '') ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41 11 3.83A2 2 0 0 0 9.59 3.24L4 3a1 1 0 0 0-1 1l.24 5.59a2 2 0 0 0 .59 1.41l9.58 9.58a2 2 0 0 0 2.83 0l4.35-4.35a2 2 0 0 0 0-2.82Z"/><circle cx="8.5" cy="8.5" r="1.5"/></svg>
        Discounts
      </a>
      <a href="/studio/menu.php" class="sidebar-link <?= nav_active('menu', $activeNav ?? '') ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        Menu
      </a>
      <a href="/studio/gift-cards.php" class="sidebar-link <?= nav_active('gift-cards', $activeNav ?? '') ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M2 10h20M8 15h.01M12 15h4"/><path d="M12 7a2.5 2.5 0 0 1-5 0 2.5 2.5 0 0 1 5-5 2.5 2.5 0 0 1 5 5 2.5 2.5 0 0 1-5 0Z"/></svg>
        Gift Cards
      </a>
      <a href="/studio/settings.php" class="sidebar-link <?= nav_active('settings', $activeNav ?? '') ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        Studio Settings
      </a>
      <a href="/kiosk-login.php" target="_blank" rel="noopener" class="sidebar-link">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 10h8M8 14h4"/></svg>
        Open Lobby Display
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left:auto;opacity:0.6;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14L21 3"/></svg>
      </a>
    </nav>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-user-avatar"><?= e(admin_initials($admin['username'])) ?></div>
        <div><strong><?= e($admin['username']) ?></strong><span><?= e(ucfirst($admin['role'])) ?></span></div>
        <a href="/studio/logout.php" class="sidebar-logout" title="Sign out">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
        </a>
      </div>
    </div>
  </aside>

  <div class="main">
    <div class="topbar">
      <div style="display:flex;align-items:center;gap:14px;">
        <button class="mobile-sidebar-toggle icon-btn" onclick="document.getElementById('sidebar').classList.toggle('open')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
        </button>
        <div>
          <h1><?= e($pageTitle ?? 'Dashboard') ?></h1>
          <?php if (!empty($pageSubtitle)): ?><div class="topbar-sub"><?= e($pageSubtitle) ?></div><?php endif; ?>
        </div>
      </div>
      <div class="topbar-actions">
        <div class="notif-bell-wrap">
          <button class="icon-btn notif-bell" id="notif-bell-btn" onclick="toggleNotifDropdown()">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span class="notif-badge" id="notif-badge" style="display:none;">0</span>
          </button>
          <div class="notif-dropdown" id="notif-dropdown">
            <div class="notif-dropdown-header">Recent Activity</div>
            <div id="notif-list">
              <div class="notif-empty">No activity yet.</div>
            </div>
          </div>
        </div>
        <a href="/" target="_blank" class="btn btn-secondary btn-sm">View Site</a>
      </div>
    </div>
    <div class="content">
