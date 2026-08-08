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
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: Manrope, sans-serif; background: #f9f9f9; color: #333; }
.container { display: flex; height: 100vh; }
.sidebar { width: 220px; background: #3a2a24; color: white; overflow-y: auto; padding: 24px 0; }
.sidebar-brand { padding: 0 20px 24px; font-size: 18px; font-weight: 700; border-bottom: 1px solid rgba(255,255,255,.1); }
.sidebar-nav { padding: 16px 0; }
.sidebar-nav a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: rgba(255,255,255,.7); text-decoration: none; font-size: 14px; transition: all 0.2s; }
.sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(255,255,255,.1); color: white; }
.sidebar-footer { position: absolute; bottom: 0; width: 220px; padding: 20px; border-top: 1px solid rgba(255,255,255,.1); font-size: 12px; }
.logout-btn { display: inline-block; margin-top: 12px; padding: 6px 12px; background: #c9a87d; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; }
.main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.topbar { background: white; border-bottom: 1px solid #eee; padding: 16px 28px; display: flex; align-items: center; justify-content: space-between; }
.page-title { font-size: 20px; font-weight: 700; }
.main-content { flex: 1; overflow-y: auto; padding: 28px 28px; background: #f9f9f9; }
.panel { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: visible; }
.section-header { padding-bottom: 16px; margin-bottom: 16px; border-bottom: 1px solid #eee; }
.section-title { font-size: 18px; font-weight: 700; margin: 0 0 6px 0; color: #333; }
.panel-body { padding: 20px; }
.panel-body.no-pad { padding: 0; }
.empty-state { padding: 40px; text-align: center; color: #999; }
table { width: 100%; border-collapse: collapse; }
table th { background: #f5f5f5; padding: 12px; text-align: left; font-weight: 600; font-size: 13px; border-bottom: 1px solid #ddd; }
table td { padding: 12px; border-bottom: 1px solid #eee; }
table tr:hover { background: #f9f9f9; }
.btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; }
.btn-primary { background: #28a745; color: white; }
.btn-secondary { background: #6c757d; color: white; }
.btn-danger { background: #dc3545; color: white; }
.btn-warning { background: #ffc107; color: black; }
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
      <a href="<?= BASE_PATH ?>/studio/stamp-cards.php" class="nav-item <?= $activeNav === 'stamp-cards' ? 'active' : '' ?>"><span>🎫</span> Stamp Cards</a>
      <a href="<?= BASE_PATH ?>/studio/stamp-monitor.php" class="nav-item <?= $activeNav === 'stamp-monitor' ? 'active' : '' ?>"><span>📊</span> Monitor</a>
      <a href="<?= BASE_PATH ?>/studio/stamp-history.php" class="nav-item <?= $activeNav === 'stamp-history' ? 'active' : '' ?>"><span>📝</span> History</a>
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
    </header>

    <!-- CONTENT -->
    <main class="main-content">
