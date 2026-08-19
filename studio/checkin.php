<?php
$pageTitle = 'Front Desk Check-In';
$pageSubtitle = date('l, F j, Y') . ' — today\'s arrivals';
$activeNav = 'checkin';
require_once __DIR__ . '/includes/layout_start.php';

$pdo = get_db();
$csrf = admin_csrf_token();

$stmt = $pdo->query("
  SELECT b.*, s.name AS service_name, s.duration_minutes, s.price, st.full_name AS staff_name, st.color_hex AS staff_color
  FROM bookings b
  JOIN services s ON s.id = b.service_id
  LEFT JOIN staff st ON st.id = b.staff_id
  WHERE b.appointment_date = CURDATE() AND b.status NOT IN ('cancelled')
  ORDER BY b.appointment_time ASC
");
$all = $stmt->fetchAll();

$waiting = array_filter($all, fn($b) => !$b['checked_in_at'] && $b['status'] !== 'completed');
$checkedIn = array_filter($all, fn($b) => $b['checked_in_at'] && $b['status'] !== 'completed');
$completed = array_filter($all, fn($b) => $b['status'] === 'completed');

$activeServices = get_active_services();
$activeStaff = get_active_staff();

function initials3(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $letters = array_map(fn($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2));
    return mb_strtoupper(implode('', $letters)) ?: '?';
}

function render_checkin_card(array $b, string $mode): void {
    $time = date('g:i A', strtotime($b['appointment_time']));
    echo '<div class="checkin-card">';
    echo '<div class="checkin-card-top"><span class="checkin-time">' . e($time) . '</span>';
    echo '<span class="badge badge-' . e($b['status']) . '">' . e(ucfirst(str_replace('_',' ',$b['status']))) . '</span></div>';
    echo '<strong class="name">' . e($b['full_name']) . '</strong>';
    $serviceLine = e($b['service_name']) . ' · ' . e($b['phone'] ?: $b['email'] ?: 'no contact');
    if (!empty($b['staff_name'])) {
        $serviceLine .= ' · <span style="color:' . e($b['staff_color']) . ';font-weight:600;">' . e($b['staff_name']) . '</span>';
    }
    echo '<div class="service">' . $serviceLine . '</div>';
    echo '<div class="checkin-actions">';
    if ($mode === 'waiting') {
        echo '<button class="btn btn-primary btn-sm" onclick="checkIn(' . (int)$b['id'] . ')">Check In</button>';
    } elseif ($mode === 'checked_in') {
        echo '<button class="btn btn-secondary btn-sm" onclick="undoCheckIn(' . (int)$b['id'] . ')">Undo</button>';
        echo '<button class="btn btn-primary btn-sm" onclick="completeBooking(' . (int)$b['id'] . ')">Mark Completed</button>';
    } else {
        echo '<span style="font-size:12px;color:var(--a-ink-faint);">Checked out ' . e(date('g:i A', strtotime($b['appointment_time']))) . '</span>';
    }
    echo '</div></div>';
}
?>

<div style="display:flex;justify-content:flex-end;margin-bottom:20px;">
  <button class="btn btn-primary" onclick="openModal('walkin-modal')">+ Add Walk-In</button>
</div>

<div class="checkin-grid">
  <div>
    <div class="checkin-column-title"><h3>Waiting to Arrive</h3><span class="checkin-count"><?= count($waiting) ?></span></div>
    <?php if (empty($waiting)): ?>
      <div class="checkin-empty">No one waiting right now.</div>
    <?php else: foreach ($waiting as $b): render_checkin_card($b, 'waiting'); endforeach; endif; ?>
  </div>
  <div>
    <div class="checkin-column-title"><h3>Checked In</h3><span class="checkin-count"><?= count($checkedIn) ?></span></div>
    <?php if (empty($checkedIn)): ?>
      <div class="checkin-empty">No clients currently in the studio.</div>
    <?php else: foreach ($checkedIn as $b): render_checkin_card($b, 'checked_in'); endforeach; endif; ?>
  </div>
  <div>
    <div class="checkin-column-title"><h3>Completed Today</h3><span class="checkin-count"><?= count($completed) ?></span></div>
    <?php if (empty($completed)): ?>
      <div class="checkin-empty">No completed visits yet today.</div>
    <?php else: foreach ($completed as $b): render_checkin_card($b, 'completed'); endforeach; endif; ?>
  </div>
</div>

<!-- Walk-in modal -->
<div class="modal-backdrop" id="walkin-modal">
  <div class="modal">
    <div class="modal-header">
      <h3>Add Walk-In Client</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <div id="walkin-alert" style="display:none;background:var(--a-danger-bg);color:var(--a-danger);padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;"></div>
      <div class="form-field">
        <label>Client Name</label>
        <input type="text" id="wi-name" placeholder="Jane Doe">
      </div>
      <div class="form-row-2">
        <div class="form-field">
          <label>Phone</label>
          <input type="tel" id="wi-phone" placeholder="(801) 555-0123">
        </div>
        <div class="form-field">
          <label>Date of Birth (optional)</label>
          <input type="date" id="wi-dob">
        </div>
      </div>
      <div class="form-field">
        <label>Email (optional)</label>
        <input type="email" id="wi-email" placeholder="jane@email.com">
      </div>
      <div class="form-field">
        <label>Services (up to 3, booked back-to-back)</label>
        <div id="wi-service-list" style="display:flex;flex-direction:column;gap:6px;max-height:180px;overflow-y:auto;border:1.5px solid var(--a-border);border-radius:10px;padding:8px;">
          <?php foreach ($activeServices as $svc): ?>
            <label style="display:flex;align-items:center;gap:8px;font-size:13.5px;font-weight:500;cursor:pointer;padding:4px 2px;">
              <input type="checkbox" class="wi-service-check" value="<?= (int)$svc['id'] ?>" data-price="<?= (float)$svc['price'] ?>" data-duration="<?= (int)$svc['duration_minutes'] ?>">
              <?= e($svc['name']) ?> — <?= e(format_price((float)$svc['price'])) ?>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="form-hint" id="wi-service-summary"></div>
      </div>
      <div class="form-field" id="wi-tech-field" style="display:none;">
        <label>Technician (per service)</label>
        <div id="wi-tech-list" style="display:flex;flex-direction:column;gap:8px;"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-modal-close>Cancel</button>
      <button class="btn btn-primary" id="wi-submit" onclick="submitWalkin()">Check In Client</button>
    </div>
  </div>
</div>

<!-- Stamp card modal -->
<div class="modal-backdrop" id="stamp-card-modal">
  <div class="modal" style="max-width:380px;">
    <div class="modal-header">
      <h3 id="stamp-card-title">Reward Stamp Earned</h3>
      <button class="modal-close" onclick="closeStampCardAndReload()">&times;</button>
    </div>
    <div class="modal-body" style="text-align:center;">
      <div id="stamp-card-body"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary" onclick="closeStampCardAndReload()" style="width:100%;">Done</button>
    </div>
  </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($csrf) ?>;
const WI_STAFF_LIST = <?= json_encode(array_map(fn($s) => ['id' => (int) $s['id'], 'name' => $s['full_name']], $activeStaff)) ?>;

const wiDobInput = document.getElementById('wi-dob');
if (wiDobInput) wiDobInput.max = new Date().toISOString().split('T')[0];

const WI_MAX_SERVICES = 3;
const wiServiceChecks = Array.from(document.querySelectorAll('.wi-service-check'));
const wiServiceSummary = document.getElementById('wi-service-summary');
const wiTechField = document.getElementById('wi-tech-field');
const wiTechList = document.getElementById('wi-tech-list');
const wiTechChoices = {}; // service_id -> chosen staff_id, preserved across re-renders

function refreshWiServiceSummary() {
  const checked = wiServiceChecks.filter((c) => c.checked);
  if (checked.length === 0) {
    wiServiceSummary.textContent = '';
    return;
  }
  const totalPrice = checked.reduce((sum, c) => sum + parseFloat(c.dataset.price || '0'), 0);
  const totalMinutes = checked.reduce((sum, c) => sum + parseInt(c.dataset.duration || '0', 10), 0);
  wiServiceSummary.textContent = `${checked.length} selected — $${totalPrice.toFixed(0)} · ${totalMinutes} min total`;
}

function refreshWiTechList() {
  const checked = wiServiceChecks.filter((c) => c.checked);
  wiTechField.style.display = checked.length ? 'block' : 'none';
  wiTechList.innerHTML = checked.map((c) => {
    const id = c.value;
    const label = c.closest('label').textContent.trim();
    const options = ['<option value="">No preference</option>']
      .concat(WI_STAFF_LIST.map((s) => `<option value="${s.id}">${s.name}</option>`))
      .join('');
    return `<div style="display:flex;align-items:center;gap:8px;">
      <span style="flex:1;font-size:12.5px;color:var(--a-ink-faint);">${label}</span>
      <select class="wi-tech-select" data-service-id="${id}" style="flex:1;">${options}</select>
    </div>`;
  }).join('');
  wiTechList.querySelectorAll('.wi-tech-select').forEach((sel) => {
    const sid = sel.dataset.serviceId;
    if (wiTechChoices[sid]) sel.value = wiTechChoices[sid];
    sel.addEventListener('change', () => { wiTechChoices[sid] = sel.value; });
  });
}

wiServiceChecks.forEach((check) => {
  check.addEventListener('change', () => {
    const checked = wiServiceChecks.filter((c) => c.checked);
    if (checked.length > WI_MAX_SERVICES) {
      check.checked = false;
      showToast(`You can select up to ${WI_MAX_SERVICES} services.`, 'error');
      return;
    }
    refreshWiServiceSummary();
    refreshWiTechList();
  });
});

function showStampCard(totalPoints, clientName) {
  document.getElementById('stamp-card-title').textContent = clientName ? `${clientName} — Reward Stamp Earned` : 'Reward Stamp Earned';
  const points = Math.max(0, totalPoints || 0);
  const completedBlocks = Math.floor(points / 10);
  const filled = points % 10;
  const remaining = filled === 0 ? 10 : 10 - filled;

  let grid = '<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;max-width:260px;margin:0 auto 14px;">';
  for (let i = 0; i < 10; i++) {
    const isFilled = i < filled;
    grid += `<div style="aspect-ratio:1;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:15px;` +
      (isFilled
        ? 'background:linear-gradient(155deg, var(--a-rose-gold-light), var(--a-rose-gold) 60%, var(--a-plum));color:#fff;'
        : 'border:1.5px dashed var(--a-border);color:var(--a-ink-faint);') +
      `">${isFilled ? '♦' : ''}</div>`;
  }
  grid += '</div>';

  let note = '';
  if (completedBlocks > 0) {
    note += `<div style="font-weight:700;color:var(--a-rose-gold);margin-bottom:6px;">💎 $${completedBlocks * 10} in rewards ready to redeem</div>`;
  }
  note += `<div style="font-size:13.5px;color:var(--a-ink-faint);">${remaining} more visit${remaining === 1 ? '' : 's'} until the next $10 off.</div>`;

  document.getElementById('stamp-card-body').innerHTML = grid + note;
  openModal('stamp-card-modal');
}

function closeStampCardAndReload() {
  closeModal('stamp-card-modal');
  window.location.reload();
}

async function checkIn(id) {
  const res = await postJSON('/studio/actions/toggle-checkin.php', { booking_id: id, action: 'check_in', csrf_token: CSRF_TOKEN });
  if (res.ok) {
    showToast('Client checked in.');
    if (res.total_points > 0) {
      showStampCard(res.total_points, res.client_name);
    } else {
      window.location.reload();
    }
  } else showToast(res.error || 'Could not check in.', 'error');
}
async function undoCheckIn(id) {
  const res = await postJSON('/studio/actions/toggle-checkin.php', { booking_id: id, action: 'undo', csrf_token: CSRF_TOKEN });
  if (res.ok) { showToast('Check-in undone.'); window.location.reload(); }
  else showToast(res.error || 'Could not update.', 'error');
}
async function completeBooking(id) {
  const ok = await updateBookingStatus(id, 'completed', CSRF_TOKEN);
  if (ok) window.location.reload();
}

async function submitWalkin() {
  const alertBox = document.getElementById('walkin-alert');
  alertBox.style.display = 'none';
  const selectedServiceIds = wiServiceChecks.filter((c) => c.checked).map((c) => c.value);
  if (selectedServiceIds.length === 0) {
    alertBox.textContent = 'Please select at least one service.';
    alertBox.style.display = 'block';
    return;
  }
  const staffIds = {};
  wiTechList.querySelectorAll('.wi-tech-select').forEach((sel) => {
    staffIds[sel.dataset.serviceId] = sel.value || null;
  });
  const payload = {
    csrf_token: CSRF_TOKEN,
    full_name: document.getElementById('wi-name').value.trim(),
    phone: document.getElementById('wi-phone').value.trim(),
    email: document.getElementById('wi-email').value.trim(),
    date_of_birth: document.getElementById('wi-dob').value,
    service_ids: selectedServiceIds,
    staff_ids: staffIds,
  };
  const btn = document.getElementById('wi-submit');
  btn.disabled = true; btn.textContent = 'Saving...';
  try {
    const res = await postJSON('/studio/actions/create-walkin.php', payload);
    if (!res.ok) {
      alertBox.textContent = res.error || 'Could not save this walk-in.';
      alertBox.style.display = 'block';
      btn.disabled = false; btn.textContent = 'Check In Client';
      return;
    }
    showToast('Walk-in added and checked in.');
    closeModal('walkin-modal');
    if (res.total_points > 0) {
      showStampCard(res.total_points, payload.full_name);
    } else {
      window.location.reload();
    }
  } catch (e) {
    alertBox.textContent = 'Network error — please try again.';
    alertBox.style.display = 'block';
    btn.disabled = false; btn.textContent = 'Check In Client';
  }
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
