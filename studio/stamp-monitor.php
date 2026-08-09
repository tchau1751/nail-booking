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

// Summary stats
try {
  $summary = $pdo->query("
    SELECT
      SUM(stamp_count) as total_stamps_earned,
      COUNT(CASE WHEN stamp_count = 0 THEN 1 END) as rewards_given,
      ROUND(AVG(stamp_count), 1) as avg_stamps_per_client
    FROM clients
  ")->fetch();
} catch (Exception $e) {
  $summary = ['total_stamps_earned' => 0, 'rewards_given' => 0, 'avg_stamps_per_client' => 0];
}

// Distribution by level
try {
  $distribution = $pdo->query("
    SELECT
      SUM(CASE WHEN stamp_count = 0 THEN 1 ELSE 0 END) as level_0,
      SUM(CASE WHEN stamp_count BETWEEN 1 AND 2 THEN 1 ELSE 0 END) as level_1_2,
      SUM(CASE WHEN stamp_count BETWEEN 3 AND 5 THEN 1 ELSE 0 END) as level_3_5,
      SUM(CASE WHEN stamp_count BETWEEN 6 AND 8 THEN 1 ELSE 0 END) as level_6_8,
      SUM(CASE WHEN stamp_count >= 9 THEN 1 ELSE 0 END) as level_9_10
    FROM clients
  ")->fetch();
} catch (Exception $e) {
  $distribution = ['level_0'=>0, 'level_1_2'=>0, 'level_3_5'=>0, 'level_6_8'=>0, 'level_9_10'=>0];
}

// Goals breakdown
try {
  $goals = $pdo->query("
    SELECT
      SUM(CASE WHEN stamp_count >= 9 THEN 1 ELSE 0 END) as almost_done,
      SUM(CASE WHEN stamp_count >= 5 AND stamp_count < 9 THEN 1 ELSE 0 END) as halfway,
      SUM(CASE WHEN stamp_count > 0 AND stamp_count < 5 THEN 1 ELSE 0 END) as started,
      SUM(CASE WHEN stamp_count = 0 THEN 1 ELSE 0 END) as not_started
    FROM clients
  ")->fetch();
} catch (Exception $e) {
  $goals = ['almost_done'=>0, 'halfway'=>0, 'started'=>0, 'not_started'=>0];
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
  <div style="margin-bottom:28px;">
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

  <!-- SECTION 1: Summary Stats -->
  <div style="margin-bottom:28px;">
    <h3 style="font-size:16px;margin-bottom:12px;font-weight:600;">📊 Summary Statistics</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:12px;">
      <div style="background:#e8f5e9;padding:16px;border-radius:8px;text-align:center;">
        <div style="font-size:28px;font-weight:700;color:#2e7d32;"><?= (int)$summary['total_stamps_earned'] ?></div>
        <div style="font-size:12px;color:#666;margin-top:4px;">Total Stamps Earned</div>
      </div>
      <div style="background:#fff3e0;padding:16px;border-radius:8px;text-align:center;">
        <div style="font-size:28px;font-weight:700;color:#e65100;"><?= (int)$summary['rewards_given'] ?></div>
        <div style="font-size:12px;color:#666;margin-top:4px;">Rewards Given</div>
      </div>
      <div style="background:#f3e5f5;padding:16px;border-radius:8px;text-align:center;">
        <div style="font-size:28px;font-weight:700;color:#6a1b9a;"><?= $summary['avg_stamps_per_client'] ?></div>
        <div style="font-size:12px;color:#666;margin-top:4px;">Avg per Client</div>
      </div>
    </div>
  </div>

  <!-- SECTION 2: Distribution Chart -->
  <div style="margin-bottom:28px;">
    <h3 style="font-size:16px;margin-bottom:12px;font-weight:600;">📈 Stamp Distribution</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(120px, 1fr));gap:12px;">
      <div style="background:#f5f5f5;padding:12px;border-radius:8px;text-align:center;">
        <div style="font-size:16px;font-weight:700;color:#333;"><?= (int)$distribution['level_0'] ?></div>
        <div style="font-size:11px;color:#666;margin-top:4px;">0 Stamps</div>
      </div>
      <div style="background:#bbdefb;padding:12px;border-radius:8px;text-align:center;">
        <div style="font-size:16px;font-weight:700;color:#1976d2;"><?= (int)$distribution['level_1_2'] ?></div>
        <div style="font-size:11px;color:#666;margin-top:4px;">1-2 Stamps</div>
      </div>
      <div style="background:#81c784;padding:12px;border-radius:8px;text-align:center;">
        <div style="font-size:16px;font-weight:700;color:#fff;"><?= (int)$distribution['level_3_5'] ?></div>
        <div style="font-size:11px;color:#666;margin-top:4px;">3-5 Stamps</div>
      </div>
      <div style="background:#ffa726;padding:12px;border-radius:8px;text-align:center;">
        <div style="font-size:16px;font-weight:700;color:#fff;"><?= (int)$distribution['level_6_8'] ?></div>
        <div style="font-size:11px;color:#666;margin-top:4px;">6-8 Stamps</div>
      </div>
      <div style="background:#ef5350;padding:12px;border-radius:8px;text-align:center;">
        <div style="font-size:16px;font-weight:700;color:#fff;"><?= (int)$distribution['level_9_10'] ?></div>
        <div style="font-size:11px;color:#666;margin-top:4px;">9-10 Stamps</div>
      </div>
    </div>
  </div>

  <!-- SECTION 3: Goals/Progress -->
  <div style="margin-bottom:28px;">
    <h3 style="font-size:16px;margin-bottom:12px;font-weight:600;">🎯 Progress Goals</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(140px, 1fr));gap:12px;">
      <div style="border-left:4px solid #ef5350;background:#ffebee;padding:14px;border-radius:4px;">
        <div style="font-weight:600;color:#c62828;"><?= (int)$goals['almost_done'] ?> Almost Done</div>
        <div style="font-size:11px;color:#999;margin-top:2px;">9-10 stamps</div>
      </div>
      <div style="border-left:4px solid #ffa726;background:#fff3e0;padding:14px;border-radius:4px;">
        <div style="font-weight:600;color:#e65100;"><?= (int)$goals['halfway'] ?> Halfway</div>
        <div style="font-size:11px;color:#999;margin-top:2px;">5-8 stamps</div>
      </div>
      <div style="border-left:4px solid #81c784;background:#e8f5e9;padding:14px;border-radius:4px;">
        <div style="font-weight:600;color:#2e7d32;"><?= (int)$goals['started'] ?> Started</div>
        <div style="font-size:11px;color:#999;margin-top:2px;">1-4 stamps</div>
      </div>
      <div style="border-left:4px solid #9e9e9e;background:#f5f5f5;padding:14px;border-radius:4px;">
        <div style="font-weight:600;color:#424242;"><?= (int)$goals['not_started'] ?> Not Started</div>
        <div style="font-size:11px;color:#999;margin-top:2px;">0 stamps</div>
      </div>
    </div>
  </div>
  </div>
</div>

<!-- BOTTOM NAVIGATION -->
<div style="margin-top:28px;padding:20px;background:#f9f9f9;border-radius:12px;display:grid;grid-template-columns:repeat(auto-fit, minmax(120px, 1fr));gap:12px;">
  <a href="<?= BASE_PATH ?>/studio/" style="padding:12px;background:#1ba0c8;color:white;text-align:center;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px;">📋 Bookings</a>
  <a href="<?= BASE_PATH ?>/studio/clients.php" style="padding:12px;background:#6c757d;color:white;text-align:center;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px;">👥 Clients</a>
  <a href="<?= BASE_PATH ?>/studio/stamp-cards.php" style="padding:12px;background:#28a745;color:white;text-align:center;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px;">🎫 Stamp Cards</a>
  <a href="<?= BASE_PATH ?>/studio/stamp-history.php" style="padding:12px;background:#dc3545;color:white;text-align:center;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px;">📝 History</a>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
