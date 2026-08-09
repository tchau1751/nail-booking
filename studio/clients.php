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
  $total_clients = count($clients);
} catch (Exception $e) {
  $error = 'Error loading clients: ' . $e->getMessage();
  $clients = [];
  $total_clients = 0;
}
?>

<div class="panel">
  <div class="section-header">
    <h2 class="section-title">👥 Clients</h2>
    <p style="font-size:13px;color:rgba(58,42,36,.6);margin:0;">Everyone who has booked or visited the studio</p>
  </div>

  <div class="panel-body">
    <?php if ($error): ?>
      <div style="padding:16px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;margin-bottom:16px;">
        <strong>Error:</strong> <?= e($error) ?>
      </div>
    <?php endif; ?>

    <!-- Stats Card -->
    <div style="padding:20px;background:#f9f9f9;border-radius:8px;text-align:center;margin-bottom:28px;">
      <div style="font-size:36px;font-weight:700;color:#1ba0c8;"><?= $total_clients ?></div>
      <div style="font-size:14px;color:#666;margin-top:8px;">Total Clients in Database</div>
    </div>

    <!-- Clients Table -->
    <?php if (empty($clients)): ?>
      <div style="padding:40px;text-align:center;color:#999;">No clients found.</div>
    <?php else: ?>
      <div style="overflow-x:auto;border:1px solid #ddd;border-radius:8px;">
        <table style="width:100%;font-size:13px;border-collapse:collapse;">
          <thead style="background:#f5f5f5;">
            <tr>
              <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd;">Client Name</th>
              <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd;">Contact</th>
              <th style="padding:12px;text-align:center;border-bottom:1px solid #ddd;">Visits</th>
              <th style="padding:12px;text-align:center;border-bottom:1px solid #ddd;">Stamps</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($clients as $c): ?>
            <tr style="border-bottom:1px solid #eee;">
              <td style="padding:12px;"><strong><?= e($c['full_name']) ?></strong></td>
              <td style="padding:12px;font-size:12px;color:#666;">
                <?php if ($c['phone']): ?>
                  <div><?= e($c['phone']) ?></div>
                <?php endif; ?>
                <?php if ($c['email']): ?>
                  <div style="color:#999;"><?= e($c['email']) ?></div>
                <?php endif; ?>
              </td>
              <td style="padding:12px;text-align:center;"><?= (int)$c['total_visits'] ?></td>
              <td style="padding:12px;">
                <div style="display:flex;align-items:center;gap:8px;justify-content:center;">
                  <div style="width:60px;height:16px;background:#f0f0f0;border-radius:4px;overflow:hidden;">
                    <div style="width:<?= min(100, ($c['stamp_count'] / 10) * 100) ?>%;height:100%;background:#28a745;"></div>
                  </div>
                  <span style="font-weight:700;min-width:25px;font-size:11px;"><?= (int)$c['stamp_count'] ?>/10</span>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- BOTTOM NAVIGATION -->
<div style="margin-top:28px;padding:20px;background:#f9f9f9;border-radius:12px;display:grid;grid-template-columns:repeat(auto-fit, minmax(110px, 1fr));gap:12px;">
  <a href="<?= BASE_PATH ?>/studio/" style="padding:12px;background:#1ba0c8;color:white;text-align:center;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px;">📋 Bookings</a>
  <a href="<?= BASE_PATH ?>/studio/stamp-cards.php" style="padding:12px;background:#28a745;color:white;text-align:center;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px;">🎫 Stamps</a>
  <a href="<?= BASE_PATH ?>/studio/stamp-monitor.php" style="padding:12px;background:#ffc107;color:black;text-align:center;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px;">📊 Monitor</a>
  <a href="<?= BASE_PATH ?>/studio/stamp-history.php" style="padding:12px;background:#dc3545;color:white;text-align:center;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px;">📝 History</a>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
