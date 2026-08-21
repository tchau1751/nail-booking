<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
$admin = currentAdmin();

$pageTitle = $pageTitle ?? 'Studio';
$pageSubtitle = $pageSubtitle ?? '';
$activeNav = $activeNav ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= e($pageTitle) ?> — Diamond Nails Studio</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

/* The page itself scrolls, exactly like the register. No nested scroll boxes:
   on a tablet those trap the finger and fight momentum scrolling. */
html { scroll-behavior: smooth; }
body { font-family: Manrope, sans-serif; background: #f4f1ef; color: #33251f; -webkit-text-size-adjust: 100%; }

.container { display: flex; align-items: flex-start; min-height: 100vh; }

/* Sidebar sticks while the content scrolls past it. */
.sidebar {
  width: 236px; flex: 0 0 236px; align-self: stretch;
  background: #3a2a24; color: #fff; padding: 24px 0 20px;
  position: sticky; top: 0; height: 100vh; overflow-y: auto;
  display: flex; flex-direction: column;
}
.sidebar-brand { padding: 0 20px 22px; font-size: 19px; font-weight: 800; border-bottom: 1px solid rgba(255,255,255,.12); }
.sidebar-nav { padding: 14px 0; flex: 1; }
.sidebar-nav a {
  display: flex; align-items: center; gap: 12px;
  min-height: 50px; padding: 10px 20px;           /* finger-sized, like the POS */
  color: rgba(255,255,255,.72); text-decoration: none; font-size: 15px; font-weight: 600;
  border-left: 3px solid transparent; transition: background .15s, color .15s;
}
.sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(255,255,255,.1); color: #fff; border-left-color: #c9a87d; }
.sidebar-footer { padding: 18px 20px 0; border-top: 1px solid rgba(255,255,255,.12); font-size: 13px; }
.logout-btn { display: inline-block; margin-top: 12px; padding: 9px 16px; background: #c9a87d; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 700; text-decoration: none; }

.main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.topbar {
  background: #fff; border-bottom: 1px solid #e7e0db; padding: 18px 34px;
  display: flex; align-items: center; justify-content: space-between; gap: 16px;
  position: sticky; top: 0; z-index: 20;          /* title stays put while reading */
}
.page-title { font-size: 22px; font-weight: 800; }

/* Room to breathe, and it grows with the screen instead of hugging a fixed width. */
.main-content { padding: 26px clamp(16px, 3vw, 40px) 60px; background: transparent; }

.panel { background: #fff; border-radius: 14px; padding: 22px; margin-bottom: 22px; box-shadow: 0 1px 3px rgba(58,42,36,.09); overflow: visible; }
.section-header { padding-bottom: 16px; margin-bottom: 18px; border-bottom: 1px solid #eee; }
.section-title { font-size: 19px; font-weight: 800; margin: 0 0 6px 0; }
.panel-body { padding: 20px 0 0; }
.panel-body.no-pad { padding: 0; }
.empty-state { padding: 44px; text-align: center; color: #999; }

/* Wide tables scroll inside their own box so the page never shifts sideways. */
table { width: 100%; border-collapse: collapse; }
table th { background: #faf7f5; padding: 14px 12px; text-align: left; font-weight: 700; font-size: 13px; border-bottom: 1px solid #e7e0db; white-space: nowrap; }
table td { padding: 14px 12px; border-bottom: 1px solid #f0ebe7; }
table tr:hover { background: #fbf9f8; }

.btn { padding: 11px 18px; border: none; border-radius: 9px; cursor: pointer; font-size: 14px; font-weight: 700; min-height: 44px; }
.btn-primary { background: #1f9d55; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-danger { background: #d64545; color: white; }
.btn-warning { background: #e0a800; color: black; }

/* Tablet and phone: the sidebar becomes a scrolling strip across the top. */
@media (max-width: 900px) {
  .container { flex-direction: column; }
  .sidebar {
    width: 100%; flex: none; height: auto; position: static;
    padding: 12px 0 8px; flex-direction: column;
  }
  .sidebar-brand { padding: 0 16px 12px; }
  .sidebar-nav { display: flex; gap: 6px; overflow-x: auto; padding: 10px 12px; scrollbar-width: none; }
  .sidebar-nav::-webkit-scrollbar { display: none; }
  .sidebar-nav a { border-left: none; border-radius: 10px; white-space: nowrap; padding: 10px 16px; }
  .sidebar-nav a:hover, .sidebar-nav a.active { border-left: none; background: rgba(255,255,255,.16); }
  .sidebar-footer { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px 0; }
  .logout-btn { margin-top: 0; }
  .topbar { padding: 14px 18px; position: static; }
  .main-content { padding: 18px 14px 48px; }
}

.table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 12px; }
</style>
</head>
<body>

<div class="container">
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-brand">💎 Studio</div>
    <nav class="sidebar-nav">
      <a href="<?= BASE_PATH ?>/studio/" class="nav-item <?= $activeNav === 'bookings' ? 'active' : '' ?>"><span>📋</span> Bookings</a>
      <a href="<?= BASE_PATH ?>/studio/clients.php" class="nav-item <?= $activeNav === 'clients' ? 'active' : '' ?>"><span>👥</span> Clients</a>
      <a href="<?= BASE_PATH ?>/pos/stamps.php" class="nav-item <?= $activeNav === 'stamp-cards' ? 'active' : '' ?>"><span>🎫</span> Stamp Cards</a>
      <a href="<?= BASE_PATH ?>/pos/stamps.php" class="nav-item <?= $activeNav === 'stamp-monitor' ? 'active' : '' ?>"><span>📊</span> Monitor</a>
      <a href="<?= BASE_PATH ?>/pos/stamps.php" class="nav-item <?= $activeNav === 'stamp-history' ? 'active' : '' ?>"><span>📝</span> History</a>
      <a href="<?= BASE_PATH ?>/pos/" class="nav-item" style="margin-top:14px;background:#1f9d55;color:#fff;font-weight:700;"><span>💅</span> Back to Register</a>
      <a href="<?= BASE_PATH ?>/pos/queue.php" class="nav-item"><span>🪑</span> Queue</a>
      <a href="<?= BASE_PATH ?>/admin/" class="nav-item"><span>📊</span> Booking admin</a>
    </nav>
    <div class="sidebar-footer">
      <div><?= e($admin['name']) ?></div>
      <a href="<?= BASE_PATH ?>/admin/logout.php" class="logout-btn">Sign out</a>
    </div>
  </aside>

  <!-- MAIN -->
  <div class="main">
    <!-- TOPBAR -->
    <header class="topbar">
      <div>
        <h2 class="page-title"><?= e($pageTitle) ?></h2>
        <?php if ($pageSubtitle): ?>
          <p style="font-size:13px;color:#999;margin:4px 0 0 0;"><?= e($pageSubtitle) ?></p>
        <?php endif; ?>
      </div>
      <!-- TOP NAVIGATION BAR (4 BUTTONS) -->
      <div style="display:flex;gap:10px;">
        <a href="<?= BASE_PATH ?>/studio/" style="padding:10px 14px;background:#1ba0c8;color:white;text-align:center;border-radius:6px;text-decoration:none;font-weight:600;font-size:12px;display:flex;align-items:center;gap:6px;white-space:nowrap;">📋 Bookings</a>
        <a href="<?= BASE_PATH ?>/pos/stamps.php" style="padding:10px 14px;background:#28a745;color:white;text-align:center;border-radius:6px;text-decoration:none;font-weight:600;font-size:12px;display:flex;align-items:center;gap:6px;white-space:nowrap;">🎫 Stamp Cards</a>
        <a href="<?= BASE_PATH ?>/pos/stamps.php" style="padding:10px 14px;background:#ffc107;color:black;text-align:center;border-radius:6px;text-decoration:none;font-weight:600;font-size:12px;display:flex;align-items:center;gap:6px;white-space:nowrap;">📊 Monitor</a>
        <a href="<?= BASE_PATH ?>/pos/stamps.php" style="padding:10px 14px;background:#dc3545;color:white;text-align:center;border-radius:6px;text-decoration:none;font-weight:600;font-size:12px;display:flex;align-items:center;gap:6px;white-space:nowrap;">📝 History</a>
      </div>
    </header>

    <!-- CONTENT -->
    <main class="main-content">
