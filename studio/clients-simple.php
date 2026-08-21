<?php
// Minimal test - no layout files, just raw PHP and HTML
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pdo = get_db();

try {
  $stmt = $pdo->query("
    SELECT c.id, c.full_name, c.phone, c.email,
           COALESCE(c.stamp_count, 0) as stamp_count,
           COUNT(b.id) as total_visits
    FROM clients c
    LEFT JOIN bookings b ON b.client_id = c.id AND b.status = 'completed'
    GROUP BY c.id, c.full_name, c.phone, c.email, c.stamp_count
    ORDER BY c.full_name ASC
  ");
  $clients = $stmt->fetchAll();
} catch (Exception $e) {
  die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Clients</title>
  <style>
    body { font-family: Arial; margin: 20px; background: #f5f5f5; }
    .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
    h1 { color: #333; }
    .stats { padding: 20px; background: #f0f0f0; border-radius: 8px; text-align: center; margin: 20px 0; font-size: 36px; font-weight: bold; color: #1ba0c8; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    th { background: #f5f5f5; padding: 12px; text-align: left; border: 1px solid #ddd; }
    td { padding: 12px; border: 1px solid #ddd; }
    tr:hover { background: #f9f9f9; }
    button { padding: 6px 10px; margin: 2px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
    .btn-add { background: #28a745; color: white; }
    .btn-remove { background: #ffc107; color: black; }
    .btn-reset { background: #dc3545; color: white; }
    .btn-delete { background: #6c3c3c; color: white; }
    .nav { margin-top: 30px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
    .nav a { padding: 14px; text-align: center; border-radius: 8px; text-decoration: none; font-weight: bold; color: white; }
    .nav-bookings { background: #1ba0c8; }
    .nav-stamps { background: #28a745; }
    .nav-monitor { background: #ffc107; color: black; }
    .nav-history { background: #dc3545; }
  </style>
</head>
<body>
<div class="container">
  <h1>👥 Clients</h1>
  <p style="color: #666;">Everyone who has booked or visited the studio</p>

  <div class="stats"><?= count($clients) ?> Clients</div>

  <?php if (count($clients) > 0): ?>
  <table>
    <thead>
      <tr>
        <th>Name</th>
        <th>Phone</th>
        <th>Visits</th>
        <th>Stamps</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($clients as $c): ?>
      <tr>
        <td><strong><?= e($c['full_name']) ?></strong></td>
        <td><?= e($c['phone'] ?: '—') ?></td>
        <td><?= (int)$c['total_visits'] ?></td>
        <td style="text-align: center;">
          <div style="width: 60px; height: 16px; background: #f0f0f0; border-radius: 4px; overflow: hidden; margin: 0 auto; border: 1px solid #ddd;">
            <div style="width: <?= min(100, ($c['stamp_count']/10)*100) ?>%; height: 100%; background: #28a745;"></div>
          </div>
          <div style="margin-top: 4px; font-weight: bold; font-size: 11px;"><?= (int)$c['stamp_count'] ?>/10</div>
        </td>
        <td style="text-align: center;">
          <button class="btn-add">+</button>
          <button class="btn-remove">−</button>
          <button class="btn-reset">Reset</button>
          <button class="btn-delete">Delete</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <p style="text-align: center; color: #999;">No clients found.</p>
  <?php endif; ?>

  <!-- NAVIGATION -->
  <div class="nav">
    <a href="<?= BASE_PATH ?>/studio/" class="nav-bookings">📋 Bookings</a>
    <a href="<?= BASE_PATH ?>/studio/stamp-cards.php" class="nav-stamps">🎫 Stamp Cards</a>
    <a href="<?= BASE_PATH ?>/studio/stamp-monitor.php" class="nav-monitor">📊 Monitor</a>
    <a href="<?= BASE_PATH ?>/studio/stamp-history.php" class="nav-history">📝 History</a>
  </div>

</div>
</body>
</html>
