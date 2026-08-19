<?php
$pageTitle = 'All Bookings';
$pageSubtitle = 'Every reservation, searchable and filterable';
$activeNav = 'bookings';
require_once __DIR__ . '/includes/layout_start.php';

$pdo = get_db();
$csrf = admin_csrf_token();

$status = $_GET['status'] ?? '';
$search = trim((string) ($_GET['q'] ?? ''));
$allowedStatus = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];

$where = [];
$params = [];
if ($status !== '' && in_array($status, $allowedStatus, true)) {
    $where[] = 'b.status = :status';
    $params['status'] = $status;
}
if ($search !== '') {
    $where[] = '(b.full_name LIKE :q OR b.phone LIKE :q OR b.email LIKE :q)';
    $params['q'] = '%' . $search . '%';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("
  SELECT b.*, s.name AS service_name, s.price, st.full_name AS staff_name, st.color_hex AS staff_color
  FROM bookings b
  JOIN services s ON s.id = b.service_id
  LEFT JOIN staff st ON st.id = b.staff_id
  $whereSql
  ORDER BY b.appointment_date DESC, b.appointment_time DESC
  LIMIT 200
");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

function initials4(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $letters = array_map(fn($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2));
    return mb_strtoupper(implode('', $letters)) ?: '?';
}
function qs(array $overrides): string {
    $params = array_merge($_GET, $overrides);
    return '?' . http_build_query($params);
}
?>

<div class="panel">
  <div class="filter-bar">
    <a href="<?= e(qs(['status' => ''])) ?>" class="filter-pill <?= $status === '' ? 'active' : '' ?>">All</a>
    <?php foreach ($allowedStatus as $s): ?>
      <a href="<?= e(qs(['status' => $s])) ?>" class="filter-pill <?= $status === $s ? 'active' : '' ?>"><?= e(ucfirst(str_replace('_',' ',$s))) ?></a>
    <?php endforeach; ?>
    <form method="get" style="margin-left:auto;display:flex;gap:8px;">
      <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
      <input type="text" name="q" placeholder="Search name, phone, email..." value="<?= e($search) ?>">
      <button class="btn btn-secondary btn-sm" type="submit">Search</button>
    </form>
  </div>

  <div class="panel-body no-pad">
    <?php if (empty($bookings)): ?>
      <div class="empty-state">No bookings match these filters.</div>
    <?php else: ?>
      <table class="table">
        <thead><tr><th><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"></th><th>Client</th><th>Service</th><th>Technician</th><th>Date &amp; Time</th><th>Price</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($bookings as $b): ?>
          <tr>
            <td><input type="checkbox" class="booking-check" value="<?= (int)$b['id'] ?>"></td>
            <td>
              <div class="table-client">
                <div class="table-avatar"><?= e(initials4($b['full_name'])) ?></div>
                <div><strong><?= e($b['full_name']) ?></strong><span><?= e($b['phone'] ?: $b['email'] ?: '—') ?></span></div>
              </div>
            </td>
            <td><?= e($b['service_name']) ?></td>
            <td><?php if ($b['staff_name']): ?><span style="color:<?= e($b['staff_color']) ?>;font-weight:600;"><?= e($b['staff_name']) ?></span><?php else: ?><span style="color:var(--a-ink-faint);">No preference</span><?php endif; ?></td>
            <td><?= e(date('M j, Y', strtotime($b['appointment_date']))) ?> · <?= e(date('g:i A', strtotime($b['appointment_time']))) ?></td>
            <td><?= e(format_price((float)$b['price'])) ?></td>
            <td><span class="badge badge-<?= e($b['status']) ?>"><?= e(ucfirst(str_replace('_',' ',$b['status']))) ?></span></td>
            <td>
              <div class="row-actions" style="display:flex;gap:6px;align-items:center;">
                <select onchange="quickStatus(<?= (int)$b['id'] ?>, this.value)" style="padding:7px 10px;border-radius:8px;border:1.5px solid var(--a-border);font-size:12.5px;">
                  <option value="">Update...</option>
                  <?php foreach ($allowedStatus as $s): if ($s === $b['status']) continue; ?>
                    <option value="<?= e($s) ?>"><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
                  <?php endforeach; ?>
                </select>
                <button onclick="sendSMS(<?= (int)$b['id'] ?>, '<?= e($b['phone']) ?>', 'confirm')" style="padding:6px 12px;border-radius:6px;background:#1ba0c8;color:white;border:none;cursor:pointer;font-size:12px;font-weight:600;">✓ Confirm</button>
                <button onclick="sendSMS(<?= (int)$b['id'] ?>, '<?= e($b['phone']) ?>', 'remind')" style="padding:6px 12px;border-radius:6px;background:#d63d7c;color:white;border:none;cursor:pointer;font-size:12px;font-weight:600;">💬 Remind</button>
                <button onclick="deleteBooking(<?= (int)$b['id'] ?>, '<?= e($b['full_name']) ?>')" style="padding:6px 12px;border-radius:6px;background:#dc3545;color:white;border:none;cursor:pointer;font-size:12px;font-weight:600;">🗑 Delete</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div style="display:flex;gap:12px;margin-top:20px;justify-content:flex-end;padding:16px;border-top:1px solid var(--a-border);">
    <button id="bulkDeleteBtn" onclick="deleteBulkBookings()" style="display:none;padding:10px 20px;border-radius:8px;background:#dc3545;color:white;border:none;cursor:pointer;font-weight:600;">🗑 Delete Selected</button>
    <button onclick="deleteTestingBookings()" style="padding:10px 20px;border-radius:8px;background:#ffc107;color:black;border:none;cursor:pointer;font-weight:600;">⚠ Delete Testing</button>
    <button onclick="window.location.href='bookings.php'" style="padding:10px 20px;border-radius:8px;background:#6c757d;color:white;border:none;cursor:pointer;font-weight:600;">Cancel</button>
    <button onclick="window.open('bookings.php?new=1', '_self')" style="padding:10px 20px;border-radius:8px;background:#28a745;color:white;border:none;cursor:pointer;font-weight:600;">+ New Client</button>
  </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($csrf) ?>;
async function quickStatus(id, status) {
  if (!status) return;
  const ok = await updateBookingStatus(id, status, CSRF_TOKEN);
  if (ok) window.location.reload();
}

async function sendSMS(id, phone, type) {
  if (!phone) {
    alert('No phone number on file for this client');
    return;
  }

  const typeLabel = type === 'confirm' ? 'Confirmation' : 'Reminder';
  if (!confirm(`Send ${typeLabel} SMS to ${phone}?`)) {
    return;
  }

  try {
    const response = await fetch('actions/send-sms.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ booking_id: id, phone: phone, type: type, csrf_token: CSRF_TOKEN })
    });

    const result = await response.json();
    console.log('SMS Response:', result);
    if (result.ok) {
      alert(type === 'confirm' ? 'Confirmation SMS sent!' : 'Reminder SMS sent!');
    } else {
      console.error('SMS Error:', result.error);
      alert('SMS Error: ' + (result.error || 'Failed to send SMS'));
    }
  } catch (err) {
    console.error('SMS Exception:', err);
    alert('Error: ' + err.message);
  }
}

function deleteBooking(id, clientName) {
  if (!confirm(`Delete booking for ${clientName}? This cannot be undone!`)) {
    return;
  }

  if (!confirm('Are you SURE? This will permanently delete this booking!')) {
    return;
  }

  fetch('actions/delete-booking.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ booking_id: id, csrf_token: CSRF_TOKEN })
  })
  .then(r => r.json())
  .then(result => {
    if (result.ok) {
      alert('Booking deleted!');
      window.location.reload();
    } else {
      alert('Error: ' + (result.error || 'Failed to delete'));
    }
  })
  .catch(err => alert('Error: ' + err.message));
}

function toggleSelectAll(checkbox) {
  const checks = document.querySelectorAll('.booking-check');
  checks.forEach(c => c.checked = checkbox.checked);
  updateBulkDeleteBtn();
}

function updateBulkDeleteBtn() {
  const checked = document.querySelectorAll('.booking-check:checked');
  const btn = document.getElementById('bulkDeleteBtn');
  if (btn) {
    btn.style.display = checked.length > 0 ? 'block' : 'none';
  }
}

document.addEventListener('DOMContentLoaded', function() {
  const checks = document.querySelectorAll('.booking-check');
  checks.forEach(c => c.addEventListener('change', updateBulkDeleteBtn));
});

function deleteBulkBookings() {
  const checked = Array.from(document.querySelectorAll('.booking-check:checked')).map(c => parseInt(c.value));

  if (checked.length === 0) {
    alert('Please select bookings to delete');
    return;
  }

  if (!confirm(`Delete ${checked.length} booking(s)? This cannot be undone!`)) {
    return;
  }

  if (!confirm('Are you SURE? This will permanently delete these bookings!')) {
    return;
  }

  fetch('actions/delete-bulk.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ booking_ids: checked, csrf_token: CSRF_TOKEN })
  })
  .then(r => r.json())
  .then(result => {
    if (result.ok) {
      alert('Deleted ' + (result.deleted || 0) + ' bookings!');
      window.location.reload();
    } else {
      alert('Error: ' + (result.error || 'Failed to delete'));
    }
  })
  .catch(err => alert('Error: ' + err.message));
}

function deleteTestingBookings() {
  if (!confirm('Delete all test/demo bookings? This cannot be undone!')) {
    return;
  }

  if (!confirm('Are you SURE? This will permanently delete test bookings!')) {
    return;
  }

  fetch('actions/delete-testing.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ csrf_token: CSRF_TOKEN })
  })
  .then(r => r.json())
  .then(result => {
    if (result.ok) {
      alert('Test bookings deleted: ' + (result.deleted || 0) + ' records');
      window.location.reload();
    } else {
      alert('Error: ' + (result.error || 'Failed to delete'));
    }
  })
  .catch(err => alert('Error: ' + err.message));
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
