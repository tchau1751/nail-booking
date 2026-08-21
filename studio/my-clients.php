<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../../includes/functions.php';

$pdo = get_db();
$stmt = $pdo->query("SELECT id, full_name, phone, COALESCE(stamp_count, 0) as stamps,
                            (SELECT COUNT(*) FROM bookings WHERE client_id=clients.id AND status='completed') as visits
                     FROM clients ORDER BY full_name");
$clients = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Clients - Diamond Nails</title>
  <meta charset="utf-8">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
    .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
    h1 { color: #333; margin-bottom: 10px; }
    .subtitle { color: #999; margin-bottom: 30px; }
    .stats { padding: 30px; background: #f0f0f0; border-radius: 8px; text-align: center; margin-bottom: 30px; }
    .stats-number { font-size: 48px; font-weight: bold; color: #1ba0c8; }
    .stats-label { font-size: 16px; color: #666; margin-top: 10px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
    th { background: #f5f5f5; padding: 14px; text-align: left; border: 1px solid #ddd; font-weight: bold; }
    td { padding: 14px; border: 1px solid #ddd; }
    tr:hover { background: #f9f9f9; }
    .nav { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
    .nav-btn { padding: 16px; text-align: center; border-radius: 8px; text-decoration: none; font-weight: bold; color: white; }
    .nav-bookings { background: #1ba0c8; }
    .nav-stamps { background: #28a745; }
    .nav-monitor { background: #ffc107; color: black; }
    .nav-history { background: #dc3545; }
  </style>
</head>
<body>
<div class="container">
  <h1>👥 Clients</h1>
  <p class="subtitle">Everyone who has booked or visited the studio</p>

  <div class="stats">
    <div class="stats-number"><?= count($clients) ?></div>
    <div class="stats-label">Total Clients</div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Name</th>
        <th>Phone</th>
        <th>Visits</th>
        <th>Stamps</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($clients as $c): ?>
      <tr>
        <td><strong><?= e($c['full_name']) ?></strong></td>
        <td><?= e($c['phone'] ?: '—') ?></td>
        <td><?= (int)$c['visits'] ?></td>
        <td style="text-align: center;"><strong><?= (int)$c['stamps'] ?>/10</strong></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="nav">
    <a href="<?= BASE_PATH ?>/studio/" class="nav-btn nav-bookings">📋 Bookings</a>
    <a href="<?= BASE_PATH ?>/studio/stamp-cards.php" class="nav-btn nav-stamps">🎫 Stamp Cards</a>
    <a href="<?= BASE_PATH ?>/studio/stamp-monitor.php" class="nav-btn nav-monitor">📊 Monitor</a>
    <a href="<?= BASE_PATH ?>/studio/stamp-history.php" class="nav-btn nav-history">📝 History</a>
  </div>
</div>
</body>
</html>
