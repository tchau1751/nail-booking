<?php
$pageTitle = 'Dashboard';
$pageSubtitle = 'Studio overview at a glance';
$activeNav = 'dashboard';
require_once __DIR__ . '/includes/layout_start.php';

$pdo = get_db();

$todayCount = (int) $pdo->query("SELECT COUNT(*) c FROM bookings WHERE appointment_date = CURDATE() AND status <> 'cancelled'")->fetch()['c'];
$pendingCount = (int) $pdo->query("SELECT COUNT(*) c FROM bookings WHERE status = 'pending'")->fetch()['c'];
$weekRevenue = (float) $pdo->query("
  SELECT COALESCE(SUM(s.price),0) r
  FROM bookings b JOIN services s ON s.id = b.service_id
  WHERE b.appointment_date BETWEEN CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY AND CURDATE() + INTERVAL (6 - WEEKDAY(CURDATE())) DAY
  AND b.status IN ('confirmed','completed')
")->fetch()['r'];
$totalClients = (int) $pdo->query("SELECT COUNT(*) c FROM clients")->fetch()['c'];

$todaySchedule = $pdo->query("
  SELECT b.*, s.name AS service_name, s.duration_minutes, s.price
  FROM bookings b JOIN services s ON s.id = b.service_id
  WHERE b.appointment_date = CURDATE() AND b.status <> 'cancelled'
  ORDER BY b.appointment_time ASC
")->fetchAll();

$recentBookings = $pdo->query("
  SELECT b.*, s.name AS service_name
  FROM bookings b JOIN services s ON s.id = b.service_id
  ORDER BY b.created_at DESC LIMIT 8
")->fetchAll();

function initials2(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $letters = array_map(fn($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2));
    return mb_strtoupper(implode('', $letters)) ?: '?';
}
?>

<div class="stat-grid">
  <div class="stat-tile">
    <div class="stat-tile-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></div>
    <strong><?= $todayCount ?></strong>
    <span>Appointments Today</span>
  </div>
  <div class="stat-tile">
    <div class="stat-tile-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg></div>
    <strong><?= $pendingCount ?></strong>
    <span>Pending Confirmation</span>
  </div>
  <div class="stat-tile">
    <div class="stat-tile-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
    <strong><?= e(format_price($weekRevenue)) ?></strong>
    <span>This Week (booked revenue)</span>
  </div>
  <div class="stat-tile">
    <div class="stat-tile-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
    <strong><?= $totalClients ?></strong>
    <span>Total Clients</span>
  </div>
</div>

<div style="display:grid;grid-template-columns:1.3fr 1fr;gap:22px;align-items:start;">
  <div class="panel">
    <div class="panel-header">
      <h2>Today's Schedule</h2>
      <a href="/studio/calendar.php" class="btn btn-secondary btn-sm">Open Calendar</a>
    </div>
    <div class="panel-body no-pad">
      <?php if (empty($todaySchedule)): ?>
        <div class="empty-state">No appointments scheduled for today yet.</div>
      <?php else: ?>
        <table class="table">
          <thead><tr><th>Time</th><th>Client</th><th>Service</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($todaySchedule as $b): ?>
            <tr>
              <td><strong><?= e(date('g:i A', strtotime($b['appointment_time']))) ?></strong></td>
              <td>
                <div class="table-client">
                  <div class="table-avatar"><?= e(initials2($b['full_name'])) ?></div>
                  <div><strong><?= e($b['full_name']) ?></strong><span><?= e($b['phone'] ?: $b['email']) ?></span></div>
                </div>
              </td>
              <td><?= e($b['service_name']) ?></td>
              <td><span class="badge badge-<?= e($b['status']) ?>"><?= e(ucfirst(str_replace('_',' ',$b['status']))) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-header">
      <h2>Recent Bookings</h2>
      <a href="/studio/bookings.php" class="btn btn-secondary btn-sm">View All</a>
    </div>
    <div class="panel-body no-pad">
      <?php if (empty($recentBookings)): ?>
        <div class="empty-state">No bookings yet — once clients book online, they'll show up here instantly.</div>
      <?php else: ?>
        <?php foreach ($recentBookings as $b): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 26px;border-bottom:1px solid var(--a-border);">
            <div class="table-client">
              <div class="table-avatar"><?= e(initials2($b['full_name'])) ?></div>
              <div><strong><?= e($b['full_name']) ?></strong><span><?= e($b['service_name']) ?> · <?= e(date('M j', strtotime($b['appointment_date']))) ?></span></div>
            </div>
            <span class="badge badge-<?= e($b['status']) ?>"><?= e(ucfirst(str_replace('_',' ',$b['status']))) ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
