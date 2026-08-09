<?php
$pageTitle = 'Clients';
$pageSubtitle = 'Everyone who has booked or visited the studio';
$activeNav = 'clients';
require_once __DIR__ . '/includes/layout_start.php';

$pdo = get_db();
$error = '';

// Get all clients with their stamp counts
try {
  $stmt = $pdo->prepare("
    SELECT c.id, c.full_name, c.phone, c.email,
           COALESCE(c.stamp_count, 0) as stamp_count,
           COUNT(b.id) as total_visits
    FROM clients c
    LEFT JOIN bookings b ON b.client_id = c.id AND b.status = 'completed'
    GROUP BY c.id, c.full_name, c.phone, c.email, c.stamp_count
    ORDER BY c.full_name ASC
  ");
  $stmt->execute();
  $clients = $stmt->fetchAll();
} catch (Exception $e) {
  $error = 'Error loading clients: ' . $e->getMessage();
  $clients = [];
}
?>

<div class="panel">
  <div class="section-header">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <div>
        <h2 class="section-title">Clients</h2>
        <p style="font-size:13px;color:rgba(58,42,36,.6);margin:0;">Everyone who has booked or visited the studio</p>
      </div>
      <a href="<?= BASE_PATH ?>/studio/stamp-cards.php" style="padding:8px 16px;background:#c9a87d;color:white;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600;">🎫 Stamp Cards</a>
    </div>
  </div>

  <?php if ($error): ?>
    <div style="padding:16px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;margin:16px;">
      <strong>Error:</strong> <?= e($error) ?>
    </div>
  <?php endif; ?>

  <div class="panel-body no-pad">
    <?php if (empty($clients)): ?>
      <div class="empty-state">No clients found.</div>
    <?php else: ?>
      <table class="table" style="font-size:13px;">
        <thead>
          <tr>
            <th>Client Name</th>
            <th>Contact</th>
            <th>Total Visits</th>
            <th>Stamps</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($clients as $c): ?>
          <tr>
            <td><strong><?= e($c['full_name']) ?></strong></td>
            <td><span style="font-family:monospace;font-size:12px;"><?= e($c['phone'] ?: $c['email'] ?: '—') ?></span></td>
            <td style="text-align:center;"><?= (int)$c['total_visits'] ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:80px;height:16px;background:#f0f0f0;border-radius:4px;overflow:hidden;flex-shrink:0;">
                  <div style="width:<?= min(100, ($c['stamp_count'] / 10) * 100) ?>%;height:100%;background:#28a745;"></div>
                </div>
                <span style="font-weight:700;min-width:30px;font-size:12px;"><?= (int)$c['stamp_count'] ?>/10</span>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
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
