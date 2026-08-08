<?php
$pageTitle = 'Stamp Cards';
$activeNav = 'stamp-cards';
require_once __DIR__ . '/includes/layout_start.php';

$pdo = get_db();
$csrf = admin_csrf_token();
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
    <h2 class="section-title">Stamp Cards</h2>
    <p style="font-size:13px;color:rgba(58,42,36,.6);margin:0">Manage client loyalty stamps (10 stamps = free service)</p>
  </div>

  <?php if ($error): ?>
    <div style="padding:16px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;margin:16px;">
      <strong>Error:</strong> <?= e($error) ?>
    </div>
  <?php endif; ?>

  <div class="panel-body no-pad">
    <?php if (empty($clients)): ?>
      <div class="empty-state">No clients found. Create some clients in the Clients section first.</div>
    <?php else: ?>
      <table class="table" style="font-size:13px;">
        <thead>
          <tr>
            <th>Client Name</th>
            <th>Phone</th>
            <th>Total Visits</th>
            <th>Stamps</th>
            <th>Actions</th>
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
                <div style="width:120px;height:20px;background:#f0f0f0;border-radius:4px;overflow:hidden;flex-shrink:0;">
                  <div style="width:<?= min(100, ($c['stamp_count'] / 10) * 100) ?>%;height:100%;background:#28a745;transition:width 0.3s;"></div>
                </div>
                <span style="font-weight:700;min-width:30px;"><?= (int)$c['stamp_count'] ?>/10</span>
              </div>
            </td>
            <td>
              <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <button onclick="addStamp(<?= (int)$c['id'] ?>)" style="padding:6px 12px;border-radius:6px;background:#28a745;color:white;border:none;cursor:pointer;font-size:12px;font-weight:600;">+ Add</button>
                <button onclick="removeStamp(<?= (int)$c['id'] ?>)" style="padding:6px 12px;border-radius:6px;background:#ffc107;color:black;border:none;cursor:pointer;font-size:12px;font-weight:600;">- Remove</button>
                <button onclick="resetStamps(<?= (int)$c['id'] ?>)" style="padding:6px 12px;border-radius:6px;background:#dc3545;color:white;border:none;cursor:pointer;font-size:12px;font-weight:600;">Reset</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($csrf) ?>;

async function updateStamps(clientId, action) {
  try {
    const response = await fetch('actions/update-stamps.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ client_id: clientId, action: action, csrf_token: CSRF_TOKEN })
    });
    const result = await response.json();
    if (result.ok) {
      window.location.reload();
    } else {
      alert('Error: ' + (result.error || 'Failed'));
    }
  } catch (err) {
    alert('Error: ' + err.message);
  }
}

function addStamp(clientId) {
  if (confirm('Add 1 stamp to this client?')) {
    updateStamps(clientId, 'add');
  }
}

function removeStamp(clientId) {
  if (confirm('Remove 1 stamp from this client?')) {
    updateStamps(clientId, 'remove');
  }
}

function resetStamps(clientId) {
  if (confirm('Reset stamps to 0? This cannot be undone!')) {
    if (confirm('Are you sure?')) {
      updateStamps(clientId, 'reset');
    }
  }
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
