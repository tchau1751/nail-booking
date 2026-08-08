<?php
$pageTitle = 'Stamp History';
$activeNav = 'stamp-history';
require_once __DIR__ . '/includes/layout_start.php';

$pdo = get_db();
$error = '';

// Get stamp history (from booking completions)
try {
  $history = $pdo->query("
  SELECT
    DATE(b.appointment_date) as date,
    c.full_name as client_name,
    c.phone,
    COALESCE(c.stamp_count, 0) as current_stamps,
    COUNT(*) as completions_that_day
  FROM bookings b
  LEFT JOIN clients c ON b.client_id = c.id
  WHERE b.status = 'completed'
  GROUP BY DATE(b.appointment_date), c.id, c.full_name, c.phone, c.stamp_count
  ORDER BY DATE(b.appointment_date) DESC
  LIMIT 100
")->fetchAll();
} catch (Exception $e) {
  $error = 'Error loading history: ' . $e->getMessage();
  $history = [];
}

// Get clients who just earned rewards
try {
  $rewards = $pdo->query("
  SELECT
    DATE(b.appointment_date) as date,
    c.full_name as client_name,
    c.phone,
    b.appointment_date
  FROM bookings b
  LEFT JOIN clients c ON b.client_id = c.id
  WHERE b.status = 'completed'
    AND b.appointment_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
  ORDER BY b.appointment_date DESC
  LIMIT 50
")->fetchAll();
} catch (Exception $e) {
  $error = 'Error loading rewards: ' . $e->getMessage();
  $rewards = [];
}
?>

<div class="panel">
  <div class="section-header">
    <h2 class="section-title">📝 Stamp History & Audit Log</h2>
    <p style="font-size:13px;color:rgba(58,42,36,.6);margin:0">Track all stamp card activity and rewards</p>
  </div>

  <?php if ($error): ?>
    <div style="padding:16px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;margin:16px 0;">
      <strong>Error:</strong> <?= e($error) ?>
    </div>
  <?php endif; ?>

  <div style="margin-bottom:28px;">
    <h3 style="font-size:14px;margin-bottom:12px;font-weight:600;">🎁 Recent Rewards (Last 7 Days)</h3>
    <?php if (empty($rewards)): ?>
      <p style="text-align:center;color:#999;padding:20px;">No recent check-ins</p>
    <?php else: ?>
      <div style="overflow-x:auto;border:1px solid #ddd;border-radius:8px;">
        <table style="width:100%;font-size:13px;border-collapse:collapse;">
          <thead style="background:#f5f5f5;">
            <tr>
              <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd;">Client</th>
              <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd;">Phone</th>
              <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd;">Check-In Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (array_slice($rewards, 0, 20) as $r): ?>
            <tr style="border-bottom:1px solid #eee;">
              <td style="padding:12px;"><strong><?= e($r['client_name']) ?></strong></td>
              <td style="padding:12px;"><span style="font-family:monospace;font-size:12px;"><?= e($r['phone'] ?: '—') ?></span></td>
              <td style="padding:12px;font-size:12px;"><?= date('M d, Y H:i', strtotime($r['appointment_date'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div>
    <h3 style="font-size:14px;margin-bottom:12px;font-weight:600;">📊 Completion History</h3>
    <div style="overflow-x:auto;border:1px solid #ddd;border-radius:8px;">
      <table style="width:100%;font-size:13px;border-collapse:collapse;">
        <thead style="background:#f5f5f5;">
          <tr>
            <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd;">Date</th>
            <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd;">Client</th>
            <th style="padding:12px;text-align:center;border-bottom:1px solid #ddd;">Current Stamps</th>
            <th style="padding:12px;text-align:center;border-bottom:1px solid #ddd;">Completions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $h): ?>
          <tr style="border-bottom:1px solid #eee;">
            <td style="padding:12px;font-size:12px;font-weight:600;"><?= date('M d, Y', strtotime($h['date'])) ?></td>
            <td style="padding:12px;"><strong><?= e($h['client_name']) ?></strong></td>
            <td style="padding:12px;text-align:center;">
              <span style="display:inline-block;padding:4px 10px;border-radius:4px;background:#e3f2fd;color:#0066cc;font-weight:600;">
                <?= (int)$h['current_stamps'] ?>/10
              </span>
            </td>
            <td style="padding:12px;text-align:center;font-weight:600;"><?= (int)$h['completions_that_day'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
