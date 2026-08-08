<?php
$pageTitle = 'Stamp Card Monitor';
$activeNav = 'stamp-monitor';
require_once __DIR__ . '/includes/layout_start.php';

$pdo = get_db();
$error = '';

// Get stamp statistics
try {
  $stats = $pdo->query("
    SELECT
      COUNT(*) as total_clients,
      SUM(CASE WHEN stamp_count >= 9 THEN 1 ELSE 0 END) as near_reward,
      SUM(CASE WHEN stamp_count = 0 THEN 1 ELSE 0 END) as at_zero,
      AVG(stamp_count) as avg_stamps
    FROM clients
  ")->fetch();
} catch (Exception $e) {
  $error = 'Error loading stats: ' . $e->getMessage();
  $stats = ['total_clients' => 0, 'near_reward' => 0, 'at_zero' => 0, 'avg_stamps' => 0];
}

// Get top clients (most stamps)
try {
  $topClients = $pdo->query("
    SELECT c.id, c.full_name, c.phone, c.stamp_count,
           COUNT(b.id) as total_visits
    FROM clients c
    LEFT JOIN bookings b ON c.id = b.client_id AND b.status = 'completed'
    GROUP BY c.id, c.full_name, c.phone, c.stamp_count
    ORDER BY c.stamp_count DESC
    LIMIT 10
  ")->fetchAll();
} catch (Exception $e) {
  $error = 'Error loading top clients: ' . $e->getMessage();
  $topClients = [];
}

// Get recent completions
try {
  $recent = $pdo->query("
    SELECT b.id, b.full_name, b.appointment_date, c.stamp_count,
           COALESCE(c.full_name, b.full_name) as client_name
    FROM bookings b
    LEFT JOIN clients c ON b.client_id = c.id
    WHERE b.status = 'completed'
    ORDER BY b.appointment_date DESC
    LIMIT 15
  ")->fetchAll();
} catch (Exception $e) {
  $error = 'Error loading recent completions: ' . $e->getMessage();
  $recent = [];
}
?>

<div class="panel">
  <div class="section-header">
    <h2 class="section-title">🎫 Stamp Card Monitor</h2>
    <p style="font-size:13px;color:rgba(58,42,36,.6);margin:0">Real-time loyalty stamp tracking</p>
  </div>

  <div class="panel-body">
    <?php if ($error): ?>
      <div style="padding:16px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;margin-bottom:16px;">
        <strong>Error:</strong> <?= e($error) ?>
      </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin-bottom:28px;">
    <div style="background:#f0f0f0;padding:20px;border-radius:12px;text-align:center;">
      <div style="font-size:32px;font-weight:700;color:#1ba0c8;"><?= (int)$stats['total_clients'] ?></div>
      <div style="font-size:12px;color:#666;margin-top:4px;">Total Clients</div>
    </div>
    <div style="background:#fff3cd;padding:20px;border-radius:12px;text-align:center;">
      <div style="font-size:32px;font-weight:700;color:#ff6b6b;"><?= (int)$stats['near_reward'] ?></div>
      <div style="font-size:12px;color:#666;margin-top:4px;">Close to Reward (9+)</div>
    </div>
    <div style="background:#d1fae5;padding:20px;border-radius:12px;text-align:center;">
      <div style="font-size:32px;font-weight:700;color:#28a745;"><?= (int)$stats['at_zero'] ?></div>
      <div style="font-size:12px;color:#666;margin-top:4px;">Just Reset (0 stamps)</div>
    </div>
    <div style="background:#e7f3ff;padding:20px;border-radius:12px;text-align:center;">
      <div style="font-size:32px;font-weight:700;color:#0066cc;"><?= round($stats['avg_stamps'], 1) ?></div>
      <div style="font-size:12px;color:#666;margin-top:4px;">Average Stamps</div>
    </div>
  </div>

  <!-- Top Clients -->
  <div style="margin-bottom:28px;">
    <h3 style="font-size:16px;margin-bottom:12px;font-weight:600;">⭐ Top Clients (by stamps)</h3>
    <div style="overflow-x:auto;border:1px solid #ddd;border-radius:8px;">
      <table style="width:100%;font-size:13px;border-collapse:collapse;">
        <thead style="background:#f5f5f5;">
          <tr>
            <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd;">Client</th>
            <th style="padding:12px;text-align:center;border-bottom:1px solid #ddd;">Stamps</th>
            <th style="padding:12px;text-align:center;border-bottom:1px solid #ddd;">Progress</th>
            <th style="padding:12px;text-align:center;border-bottom:1px solid #ddd;">Visits</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($topClients as $c): ?>
          <tr style="border-bottom:1px solid #eee;">
            <td style="padding:12px;"><strong><?= e($c['full_name']) ?></strong></td>
            <td style="padding:12px;text-align:center;font-weight:700;color:#1ba0c8;"><?= (int)$c['stamp_count'] ?>/10</td>
            <td style="padding:12px;">
              <div style="width:100%;height:20px;background:#f0f0f0;border-radius:4px;overflow:hidden;">
                <div style="width:<?= min(100, ($c['stamp_count'] / 10) * 100) ?>%;height:100%;background:linear-gradient(90deg, #1ba0c8, #0066cc);"></div>
              </div>
            </td>
            <td style="padding:12px;text-align:center;"><?= (int)$c['total_visits'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recent Completions -->
  <div>
    <h3 style="font-size:16px;margin-bottom:12px;font-weight:600;">📋 Recent Check-Ins</h3>
    <div style="overflow-x:auto;border:1px solid #ddd;border-radius:8px;">
      <table style="width:100%;font-size:13px;border-collapse:collapse;">
        <thead style="background:#f5f5f5;">
          <tr>
            <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd;">Client</th>
            <th style="padding:12px;text-align:center;border-bottom:1px solid #ddd;">Date</th>
            <th style="padding:12px;text-align:center;border-bottom:1px solid #ddd;">Current Stamps</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $r): ?>
          <tr style="border-bottom:1px solid #eee;">
            <td style="padding:12px;"><strong><?= e($r['client_name']) ?></strong></td>
            <td style="padding:12px;text-align:center;font-size:12px;"><?= date('M d, Y H:i', strtotime($r['appointment_date'])) ?></td>
            <td style="padding:12px;text-align:center;">
              <span style="display:inline-block;padding:4px 10px;border-radius:4px;background:<?= $r['stamp_count'] >= 9 ? '#ffebee' : '#e3f2fd' ?>;color:<?= $r['stamp_count'] >= 9 ? '#c62828' : '#0066cc' ?>;font-weight:600;">
                <?= (int)$r['stamp_count'] ?>/10
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
