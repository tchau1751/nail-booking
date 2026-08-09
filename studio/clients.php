<?php
$pageTitle = 'Clients';
$pageSubtitle = 'Everyone who has booked or visited the studio';
$activeNav = 'clients';
require_once __DIR__ . '/includes/layout_start.php';

$pdo = get_db();

// Simple test - just show basic info
try {
  $stmt = $pdo->query("SELECT COUNT(*) as count FROM clients");
  $row = $stmt->fetch();
  $total_clients = $row['count'];
} catch (Exception $e) {
  $total_clients = 0;
}
?>

<div class="panel">
  <div class="section-header">
    <h2 class="section-title">👥 Clients</h2>
    <p style="font-size:13px;color:rgba(58,42,36,.6);margin:0;">Everyone who has booked or visited the studio</p>
  </div>

  <div class="panel-body">
    <div style="padding:20px;background:#f9f9f9;border-radius:8px;text-align:center;">
      <div style="font-size:32px;font-weight:700;color:#1ba0c8;"><?= $total_clients ?></div>
      <div style="font-size:14px;color:#666;margin-top:8px;">Total Clients in Database</div>
    </div>
  </div>
</div>

<!-- BOTTOM NAVIGATION -->
<div style="margin-top:28px;padding:20px;background:#f9f9f9;border-radius:12px;display:grid;grid-template-columns:repeat(auto-fit, minmax(120px, 1fr));gap:12px;">
  <a href="<?= BASE_PATH ?>/studio/" style="padding:12px;background:#1ba0c8;color:white;text-align:center;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px;">📋 Bookings</a>
  <a href="<?= BASE_PATH ?>/studio/stamp-cards.php" style="padding:12px;background:#28a745;color:white;text-align:center;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px;">🎫 Stamp Cards</a>
  <a href="<?= BASE_PATH ?>/studio/stamp-monitor.php" style="padding:12px;background:#ffc107;color:black;text-align:center;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px;">📊 Monitor</a>
  <a href="<?= BASE_PATH ?>/studio/stamp-history.php" style="padding:12px;background:#dc3545;color:white;text-align:center;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px;">📝 History</a>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
