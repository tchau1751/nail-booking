<?php
$pageTitle = 'Calendar';
$pageSubtitle = 'Drag to reschedule, tap an appointment to edit';
$activeNav = 'calendar';
require_once __DIR__ . '/includes/layout_start.php';

$pdo = get_db();
$csrf = admin_csrf_token();

$view = in_array($_GET['view'] ?? '', ['day', 'week', 'month'], true) ? $_GET['view'] : 'day';
$date = $_GET['date'] ?? date('Y-m-d');
$dateObj = DateTime::createFromFormat('Y-m-d', $date) ?: new DateTime();
$date = $dateObj->format('Y-m-d');
$today = date('Y-m-d');

$staffList = get_active_staff();
$activeServices = get_active_services();

$START_HOUR = 8;
$END_HOUR = 20;
$PX_PER_MIN = 1.4;
$SLOT_MINUTES = 15;
$colHeightPx = ($END_HOUR - $START_HOUR) * 60 * $PX_PER_MIN;

$allowedStatus = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];

function hex2rgba(?string $hex, float $alpha): string
{
    $hex = ltrim((string) $hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        $hex = 'b8836a';
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return "rgba($r,$g,$b,$alpha)";
}

function render_cal_items(array $bookings, int $startHour, float $pxPerMin, bool $flat = false): void
{
    foreach ($bookings as $b) {
        $color = $b['staff_color'] ?: '#9c8a80';
        $statusClass = 'badge-' . $b['status'];
        $statusLabel = ucfirst(str_replace('_', ' ', $b['status']));
        $timeLabel = date('g:i A', strtotime($b['appointment_time']));

        if ($flat) {
            echo '<div class="cal-item cal-item-flat" data-id="' . (int) $b['id'] . '" style="border-left-color:' . e($color) . ';background:' . hex2rgba($color, 0.12) . ';">';
            echo '<span class="cal-item-time">' . e($timeLabel) . '</span> <strong>' . e($b['full_name']) . '</strong>';
            echo '</div>';
            continue;
        }

        $t = explode(':', $b['appointment_time']);
        $minutesFromStart = ((int) $t[0] * 60 + (int) $t[1]) - $startHour * 60;
        $top = $minutesFromStart * $pxPerMin;
        $height = max((int) $b['duration_minutes'], 15) * $pxPerMin - 3;
        $height = max($height, 22);

        echo '<div class="cal-item" data-id="' . (int) $b['id'] . '" style="top:' . $top . 'px;height:' . $height . 'px;border-left-color:' . e($color) . ';background:' . hex2rgba($color, 0.14) . ';">';
        echo '<div class="cal-item-time">' . e($timeLabel) . '</div>';
        echo '<div class="cal-item-name">' . e($b['full_name']) . '</div>';
        echo '<div class="cal-item-service">' . e($b['service_name']);
        if (!empty($b['staff_name'])) {
            echo ' · ' . e($b['staff_name']);
        }
        echo '</div>';
        echo '<span class="badge ' . $statusClass . ' cal-item-badge">' . e($statusLabel) . '</span>';
        echo '</div>';
    }
}

// ---- Build the date range this view needs, then pull every booking in it in one query ----
if ($view === 'week') {
    $weekStart = clone $dateObj;
    $weekStart->modify('Monday this week');
    $weekEnd = clone $weekStart;
    $weekEnd->modify('+6 days');
    $rangeStart = $weekStart->format('Y-m-d');
    $rangeEnd = $weekEnd->format('Y-m-d');
} elseif ($view === 'month') {
    $gridStart = clone $dateObj;
    $gridStart->modify('first day of this month');
    $gridStart->modify('Monday this week');
    $monthLast = clone $dateObj;
    $monthLast->modify('last day of this month');
    $gridEnd = clone $monthLast;
    if ($gridEnd->format('N') != 7) {
        $gridEnd->modify('Sunday this week');
    }
    $rangeStart = $gridStart->format('Y-m-d');
    $rangeEnd = $gridEnd->format('Y-m-d');
} else {
    $rangeStart = $date;
    $rangeEnd = $date;
}

$stmt = $pdo->prepare("
  SELECT b.*, s.name AS service_name, s.duration_minutes, s.price, st.full_name AS staff_name, st.color_hex AS staff_color
  FROM bookings b
  JOIN services s ON s.id = b.service_id
  LEFT JOIN staff st ON st.id = b.staff_id
  WHERE b.appointment_date BETWEEN :start AND :end AND b.status != 'cancelled'
  ORDER BY b.appointment_date ASC, b.appointment_time ASC
");
$stmt->execute(['start' => $rangeStart, 'end' => $rangeEnd]);
$rangeBookings = $stmt->fetchAll();

$byDate = [];
foreach ($rangeBookings as $b) {
    $byDate[$b['appointment_date']][] = $b;
}

// ---- Columns for day (per staff) / week (per date) views ----
$columns = [];
if ($view === 'day') {
    foreach ($staffList as $st) {
        $sid = (int) $st['id'];
        $items = array_values(array_filter($byDate[$date] ?? [], fn($b) => (int) $b['staff_id'] === $sid));
        $columns[] = ['key' => (string) $sid, 'kind' => 'staff', 'label' => $st['full_name'], 'sub' => null, 'color' => $st['color_hex'], 'today' => false, 'bookings' => $items];
    }
    $noPref = array_values(array_filter($byDate[$date] ?? [], fn($b) => empty($b['staff_id'])));
    $columns[] = ['key' => '0', 'kind' => 'staff', 'label' => 'No Preference', 'sub' => null, 'color' => '#9c8a80', 'today' => false, 'bookings' => $noPref];
} elseif ($view === 'week') {
    $cursor = clone $weekStart;
    for ($i = 0; $i < 7; $i++) {
        $dKey = $cursor->format('Y-m-d');
        $columns[] = ['key' => $dKey, 'kind' => 'date', 'label' => $cursor->format('D'), 'sub' => $cursor->format('M j'), 'color' => null, 'today' => $dKey === $today, 'bookings' => $byDate[$dKey] ?? []];
        $cursor->modify('+1 day');
    }
}

$dateLabel = match ($view) {
    'week' => $weekStart->format('M j') . ' – ' . $weekEnd->format('M j, Y'),
    'month' => $dateObj->format('F Y'),
    default => $dateObj->format('D, M j, Y'),
};
?>
<style>
  .daycal-toolbar { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 16px; }
  .daycal-view-tabs { display: flex; gap: 4px; background: var(--a-surface-soft); border: 1.5px solid var(--a-border); border-radius: 999px; padding: 4px; }
  .daycal-tab { border: none; background: transparent; padding: 7px 16px; border-radius: 999px; font-size: 13px; font-weight: 600; font-family: var(--a-font-body); color: var(--a-ink-soft); cursor: pointer; }
  .daycal-tab.active { background: linear-gradient(135deg, var(--a-rose-gold-light), var(--a-rose-gold)); color: #fff; }
  .daycal-nav { display: flex; align-items: center; gap: 8px; }
  .daycal-date-label { font-weight: 700; font-size: 14px; min-width: 150px; text-align: center; }
  .daycal-toolbar > .btn:last-child { margin-left: auto; }
  .daycal-legend { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
  .daycal-legend-chip { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--a-ink-soft); background: var(--a-surface-soft); border: 1px solid var(--a-border); padding: 4px 10px; border-radius: 999px; }
  .daycal-dot { width: 9px; height: 9px; border-radius: 50%; flex: none; }

  .daycal-wrapper { background: var(--a-surface); border-radius: var(--a-radius-md); border: 1px solid var(--a-border); box-shadow: var(--a-shadow-sm); overflow: auto; height: calc(100vh - 300px); min-height: 420px; }
  .daycal-grid { display: grid; grid-template-rows: auto 1fr; }
  .daycal-corner { position: sticky; top: 0; left: 0; z-index: 6; background: var(--a-surface-soft); border-right: 1px solid var(--a-border); border-bottom: 2px solid var(--a-border); }
  .daycal-col-head { position: sticky; top: 0; z-index: 5; background: var(--a-surface-soft); border-right: 1px solid var(--a-border); border-bottom: 2px solid var(--a-border); padding: 10px 8px; text-align: center; font-size: 12.5px; font-weight: 700; }
  .daycal-col-head.is-today { background: var(--a-rose-gold-light); color: #fff; }
  .daycal-col-head .sub { display: block; font-weight: 500; font-size: 11px; opacity: 0.75; }
  .daycal-time-col { position: sticky; left: 0; z-index: 4; background: var(--a-surface-soft); border-right: 1px solid var(--a-border); }
  .daycal-hour-label { font-size: 11px; color: var(--a-ink-faint); text-align: right; padding: 2px 8px 0 0; box-sizing: border-box; border-bottom: 1px solid var(--a-border); }
  .daycal-col { border-right: 1px solid var(--a-border); position: relative; }
  .daycal-col-body { position: relative; background: repeating-linear-gradient(to bottom, transparent, transparent calc(100% / var(--hours, 12) - 1px), var(--a-border) calc(100% / var(--hours, 12) - 1px), var(--a-border) calc(100% / var(--hours, 12))); }

  .cal-item { position: absolute; left: 3px; right: 3px; border-left: 4px solid; border-radius: 6px; padding: 4px 8px; font-size: 11px; overflow: hidden; cursor: grab; touch-action: none; user-select: none; box-shadow: 0 1px 3px rgba(44,33,29,0.12); }
  .cal-item:active { cursor: grabbing; }
  .cal-item-name { font-weight: 700; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .cal-item-time { font-size: 10px; color: var(--a-ink-soft); font-weight: 600; }
  .cal-item-service { font-size: 10px; color: var(--a-ink-soft); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .cal-item-badge { position: absolute; top: 4px; right: 6px; font-size: 9px; padding: 1px 6px; }
  .cal-item-flat { position: static; border-radius: 5px; padding: 3px 6px; margin-bottom: 3px; font-size: 11px; cursor: grab; touch-action: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .apt-ghost { position: fixed; pointer-events: none; z-index: 3000; opacity: 0.9; transform: scale(1.03); box-shadow: var(--a-shadow-lg); }

  .month-grid { display: grid; grid-template-columns: repeat(7, 1fr); border-top: 1px solid var(--a-border); border-left: 1px solid var(--a-border); background: var(--a-surface); border-radius: var(--a-radius-md); overflow: hidden; box-shadow: var(--a-shadow-sm); }
  .month-head { padding: 10px; text-align: center; font-size: 11.5px; font-weight: 700; color: var(--a-ink-faint); text-transform: uppercase; background: var(--a-surface-soft); border-right: 1px solid var(--a-border); border-bottom: 1px solid var(--a-border); }
  .month-day { min-height: 108px; border-right: 1px solid var(--a-border); border-bottom: 1px solid var(--a-border); padding: 6px; cursor: pointer; }
  .month-day.other-month { background: var(--a-surface-soft); }
  .month-day.is-today .month-day-num { background: var(--a-rose-gold); color: #fff; }
  .month-day-num { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; font-size: 12.5px; font-weight: 700; margin-bottom: 4px; }
  .month-more { font-size: 10.5px; color: var(--a-ink-faint); font-weight: 600; }

  @media (max-width: 1180px) {
    .daycal-wrapper { height: calc(100vh - 340px); }
    .daycal-date-label { min-width: 120px; font-size: 13px; }
    .month-day { min-height: 78px; }
  }
</style>

<div class="daycal-toolbar">
  <div class="daycal-view-tabs">
    <button type="button" class="daycal-tab <?= $view === 'day' ? 'active' : '' ?>" onclick="switchView('day')">Day</button>
    <button type="button" class="daycal-tab <?= $view === 'week' ? 'active' : '' ?>" onclick="switchView('week')">Week</button>
    <button type="button" class="daycal-tab <?= $view === 'month' ? 'active' : '' ?>" onclick="switchView('month')">Month</button>
  </div>
  <div class="daycal-nav">
    <button type="button" class="btn btn-secondary btn-sm" onclick="navigate(-1)">‹</button>
    <div class="daycal-date-label"><?= e($dateLabel) ?></div>
    <button type="button" class="btn btn-secondary btn-sm" onclick="navigate(1)">›</button>
    <button type="button" class="btn btn-secondary btn-sm" onclick="goToday()">Today</button>
  </div>
  <button type="button" class="btn btn-primary btn-sm" onclick="openCreateModal(null, '09:00')">+ New Booking</button>
</div>

<?php if ($view === 'day'): ?>
  <div class="daycal-legend">
    <?php foreach ($staffList as $st): ?>
      <span class="daycal-legend-chip"><span class="daycal-dot" style="background:<?= e($st['color_hex']) ?>"></span><?= e($st['full_name']) ?></span>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($view === 'day' || $view === 'week'): ?>
  <div class="daycal-wrapper">
    <div class="daycal-grid" style="grid-template-columns: 60px repeat(<?= count($columns) ?>, minmax(<?= $view === 'day' ? 170 : 120 ?>px, 1fr));">
      <div class="daycal-corner"></div>
      <?php foreach ($columns as $col): ?>
        <div class="daycal-col-head <?= $col['today'] ? 'is-today' : '' ?>">
          <?php if ($col['kind'] === 'staff'): ?>
            <span class="daycal-dot" style="background:<?= e($col['color']) ?>;display:inline-block;margin-right:4px;"></span><?= e($col['label']) ?>
          <?php else: ?>
            <?= e($col['label']) ?><span class="sub"><?= e($col['sub']) ?></span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <div class="daycal-time-col" style="height:<?= $colHeightPx ?>px;">
        <?php for ($h = $START_HOUR; $h < $END_HOUR; $h++): ?>
          <div class="daycal-hour-label" style="height:<?= 60 * $PX_PER_MIN ?>px;"><?= date('g A', mktime($h, 0, 0)) ?></div>
        <?php endfor; ?>
      </div>

      <?php foreach ($columns as $col): ?>
        <div class="daycal-col">
          <div class="daycal-col-body <?= $col['kind'] === 'staff' ? 'cal-drop-staff' : 'cal-drop-date' ?>"
               data-staff-id="<?= $col['kind'] === 'staff' ? e($col['key']) : '' ?>"
               data-date="<?= $col['kind'] === 'date' ? e($col['key']) : '' ?>"
               style="height:<?= $colHeightPx ?>px;--hours:<?= $END_HOUR - $START_HOUR ?>;">
            <?php render_cal_items($col['bookings'], $START_HOUR, $PX_PER_MIN); ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php else: /* month */ ?>
  <div class="month-grid">
    <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $wd): ?>
      <div class="month-head"><?= $wd ?></div>
    <?php endforeach; ?>
    <?php
    $cursor = clone $gridStart;
    while ($cursor <= $gridEnd):
        $dKey = $cursor->format('Y-m-d');
        $items = $byDate[$dKey] ?? [];
        $isCurrentMonth = $cursor->format('m') === $dateObj->format('m');
        $isToday = $dKey === $today;
    ?>
      <div class="month-day <?= !$isCurrentMonth ? 'other-month' : '' ?> <?= $isToday ? 'is-today' : '' ?>" data-date="<?= e($dKey) ?>">
        <div class="month-day-num"><?= (int) $cursor->format('j') ?></div>
        <?php foreach (array_slice($items, 0, 3) as $b): ?>
          <?php render_cal_items([$b], $START_HOUR, $PX_PER_MIN, true); ?>
        <?php endforeach; ?>
        <?php if (count($items) > 3): ?>
          <div class="month-more">+<?= count($items) - 3 ?> more</div>
        <?php endif; ?>
      </div>
    <?php
        $cursor->modify('+1 day');
    endwhile;
    ?>
  </div>
<?php endif; ?>

<!-- Booking create/edit modal -->
<div class="modal-backdrop" id="booking-modal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="booking-modal-title">New Booking</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form id="booking-form" onsubmit="submitBookingForm(event)">
      <div class="modal-body">
        <div id="bk-alert" style="display:none;background:var(--a-danger-bg);color:var(--a-danger);padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;"></div>
        <input type="hidden" id="bk-id">
        <div class="form-field">
          <label>Client Name *</label>
          <input type="text" id="bk-name" required>
        </div>
        <div class="form-row-2">
          <div class="form-field">
            <label>Phone</label>
            <input type="tel" id="bk-phone">
          </div>
          <div class="form-field">
            <label>Email</label>
            <input type="email" id="bk-email">
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-field">
            <label>Service *</label>
            <select id="bk-service" required>
              <option value="">Select service</option>
              <?php foreach ($activeServices as $svc): ?>
                <option value="<?= (int) $svc['id'] ?>"><?= e($svc['name']) ?> — <?= e(format_price((float) $svc['price'])) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-field">
            <label>Technician</label>
            <select id="bk-staff">
              <option value="">No preference</option>
              <?php foreach ($staffList as $st): ?>
                <option value="<?= (int) $st['id'] ?>"><?= e($st['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-field">
            <label>Date *</label>
            <input type="date" id="bk-date" required>
          </div>
          <div class="form-field">
            <label>Time *</label>
            <input type="time" id="bk-time" required>
          </div>
        </div>
        <div class="form-field">
          <label>Status</label>
          <select id="bk-status">
            <?php foreach ($allowedStatus as $s): ?>
              <option value="<?= e($s) ?>"><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field">
          <label>Notes</label>
          <textarea id="bk-notes" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" id="bk-delete-btn" onclick="deleteBookingFromModal()">Delete</button>
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($csrf) ?>;
const CURRENT_DATE = <?= json_encode($date) ?>;
const VIEW = <?= json_encode($view) ?>;
const START_HOUR = <?= $START_HOUR ?>;
const PX_PER_MIN = <?= $PX_PER_MIN ?>;
const SLOT_MINUTES = <?= $SLOT_MINUTES ?>;
const BOOKINGS = <?= json_encode(array_map(function ($b) {
    return [
        'id' => (int) $b['id'],
        'full_name' => $b['full_name'],
        'phone' => (string) $b['phone'],
        'email' => (string) $b['email'],
        'service_id' => (int) $b['service_id'],
        'staff_id' => $b['staff_id'] ? (int) $b['staff_id'] : '',
        'appointment_date' => $b['appointment_date'],
        'appointment_time' => substr($b['appointment_time'], 0, 5),
        'status' => $b['status'],
        'notes' => (string) ($b['notes'] ?? ''),
    ];
}, $rangeBookings), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function findBooking(id) {
  return BOOKINGS.find(b => String(b.id) === String(id));
}

function switchView(v) {
  location.href = '?view=' + v + '&date=' + CURRENT_DATE;
}

function navigate(dir) {
  const d = new Date(CURRENT_DATE + 'T00:00:00');
  if (VIEW === 'week') d.setDate(d.getDate() + dir * 7);
  else if (VIEW === 'month') d.setMonth(d.getMonth() + dir);
  else d.setDate(d.getDate() + dir);
  location.href = '?view=' + VIEW + '&date=' + d.toISOString().split('T')[0];
}

function goToday() {
  location.href = '?view=' + VIEW + '&date=' + new Date().toISOString().split('T')[0];
}

function openCreateModal(staffId, time) {
  document.getElementById('booking-form').reset();
  document.getElementById('bk-id').value = '';
  document.getElementById('bk-date').value = CURRENT_DATE;
  document.getElementById('bk-time').value = time || '09:00';
  document.getElementById('bk-staff').value = staffId || '';
  document.getElementById('bk-status').value = 'confirmed';
  document.getElementById('booking-modal-title').textContent = 'New Booking';
  document.getElementById('bk-delete-btn').style.display = 'none';
  document.getElementById('bk-alert').style.display = 'none';
  openModal('booking-modal');
}

function openEditModal(id) {
  const b = findBooking(id);
  if (!b) return;
  document.getElementById('bk-id').value = b.id;
  document.getElementById('bk-name').value = b.full_name;
  document.getElementById('bk-phone').value = b.phone;
  document.getElementById('bk-email').value = b.email;
  document.getElementById('bk-service').value = b.service_id;
  document.getElementById('bk-staff').value = b.staff_id;
  document.getElementById('bk-date').value = b.appointment_date;
  document.getElementById('bk-time').value = b.appointment_time;
  document.getElementById('bk-status').value = b.status;
  document.getElementById('bk-notes').value = b.notes;
  document.getElementById('booking-modal-title').textContent = 'Edit Booking';
  document.getElementById('bk-delete-btn').style.display = '';
  document.getElementById('bk-alert').style.display = 'none';
  openModal('booking-modal');
}

async function submitBookingForm(e) {
  e.preventDefault();
  const payload = {
    id: document.getElementById('bk-id').value,
    full_name: document.getElementById('bk-name').value.trim(),
    phone: document.getElementById('bk-phone').value.trim(),
    email: document.getElementById('bk-email').value.trim(),
    service_id: document.getElementById('bk-service').value,
    staff_id: document.getElementById('bk-staff').value,
    appointment_date: document.getElementById('bk-date').value,
    appointment_time: document.getElementById('bk-time').value,
    status: document.getElementById('bk-status').value,
    notes: document.getElementById('bk-notes').value.trim(),
    csrf_token: CSRF_TOKEN,
  };
  const alertBox = document.getElementById('bk-alert');
  alertBox.style.display = 'none';
  const res = await postJSON('/studio/actions/save-booking.php', payload);
  if (res.ok) {
    showToast('Booking saved.');
    location.reload();
  } else {
    alertBox.textContent = res.error || 'Could not save this appointment.';
    alertBox.style.display = 'block';
  }
}

async function deleteBookingFromModal() {
  const id = document.getElementById('bk-id').value;
  if (!id) return;
  if (!confirm('Delete this appointment? This cannot be undone.')) return;
  const res = await postJSON('/studio/actions/delete-booking.php', { booking_id: id, csrf_token: CSRF_TOKEN });
  if (res.ok) {
    showToast('Booking deleted.');
    location.reload();
  } else {
    showToast(res.error || 'Could not delete.', 'error');
  }
}

/* ---------------- Click empty slot to create a booking ---------------- */
let justDragged = false;

document.querySelectorAll('.daycal-col-body').forEach(col => {
  col.addEventListener('click', (e) => {
    if (justDragged || e.target.closest('.cal-item')) return;
    const rect = col.getBoundingClientRect();
    const offsetY = e.clientY - rect.top;
    const minutes = Math.round(offsetY / PX_PER_MIN);
    const snapped = Math.max(0, Math.round(minutes / SLOT_MINUTES) * SLOT_MINUTES);
    const totalMin = START_HOUR * 60 + snapped;
    const hh = String(Math.floor(totalMin / 60)).padStart(2, '0');
    const mm = String(totalMin % 60).padStart(2, '0');
    const staffId = col.dataset.staffId;
    openCreateModal(staffId && staffId !== '0' ? staffId : null, hh + ':' + mm);
  });
});

document.querySelectorAll('.month-day').forEach(cell => {
  cell.addEventListener('click', (e) => {
    if (justDragged || e.target.closest('.cal-item')) return;
    location.href = '?view=day&date=' + cell.dataset.date;
  });
});

/* ---------------- Pointer-based drag & drop (mouse + touch/iPad) ---------------- */
let ghostEl = null;
let dragCtx = null;

function attachDrag(el) {
  el.addEventListener('pointerdown', (e) => {
    el.setPointerCapture(e.pointerId);
    dragCtx = { el, pointerId: e.pointerId, startX: e.clientX, startY: e.clientY, moved: false };
    el.addEventListener('pointermove', onPointerMove);
    el.addEventListener('pointerup', onPointerUp);
  });
}

function onPointerMove(e) {
  if (!dragCtx || dragCtx.pointerId !== e.pointerId) return;
  const dx = e.clientX - dragCtx.startX;
  const dy = e.clientY - dragCtx.startY;
  if (!dragCtx.moved && Math.hypot(dx, dy) > 8) {
    dragCtx.moved = true;
    startGhost(dragCtx.el);
    dragCtx.el.style.visibility = 'hidden';
  }
  if (dragCtx.moved) {
    e.preventDefault();
    positionGhost(e.clientX, e.clientY);
  }
}

async function onPointerUp(e) {
  if (!dragCtx || dragCtx.pointerId !== e.pointerId) return;
  const { el, moved } = dragCtx;
  el.removeEventListener('pointermove', onPointerMove);
  el.removeEventListener('pointerup', onPointerUp);
  try { el.releasePointerCapture(e.pointerId); } catch (err) {}
  el.style.visibility = '';

  if (moved) {
    justDragged = true;
    removeGhost();
    await finishDrop(el, e.clientX, e.clientY);
    setTimeout(() => { justDragged = false; }, 80);
  } else {
    removeGhost();
    openEditModal(el.dataset.id);
  }
  dragCtx = null;
}

function startGhost(el) {
  ghostEl = el.cloneNode(true);
  ghostEl.classList.add('apt-ghost');
  const rect = el.getBoundingClientRect();
  ghostEl.style.width = rect.width + 'px';
  ghostEl.style.left = rect.left + 'px';
  ghostEl.style.top = rect.top + 'px';
  document.body.appendChild(ghostEl);
}

function positionGhost(x, y) {
  if (!ghostEl) return;
  ghostEl.style.left = (x - ghostEl.offsetWidth / 2) + 'px';
  ghostEl.style.top = (y - 14) + 'px';
}

function removeGhost() {
  if (ghostEl) { ghostEl.remove(); ghostEl = null; }
}

async function finishDrop(el, clientX, clientY) {
  const id = el.dataset.id;
  const booking = findBooking(id);
  if (!booking) return;

  const prevPointerEvents = el.style.pointerEvents;
  el.style.pointerEvents = 'none';
  const underEl = document.elementFromPoint(clientX, clientY);
  el.style.pointerEvents = prevPointerEvents;

  let newDate = booking.appointment_date;
  let newTime = booking.appointment_time;
  let newStaffId = booking.staff_id;

  const staffCol = underEl ? underEl.closest('.cal-drop-staff') : null;
  const dateCol = underEl ? underEl.closest('.cal-drop-date') : null;
  const monthCell = underEl ? underEl.closest('.month-day') : null;

  if (staffCol) {
    const rect = staffCol.getBoundingClientRect();
    newTime = offsetToTime(clientY - rect.top);
    newDate = CURRENT_DATE;
    newStaffId = staffCol.dataset.staffId;
  } else if (dateCol) {
    const rect = dateCol.getBoundingClientRect();
    newTime = offsetToTime(clientY - rect.top);
    newDate = dateCol.dataset.date;
  } else if (monthCell) {
    newDate = monthCell.dataset.date;
  } else {
    return;
  }

  const res = await postJSON('/studio/actions/move-booking.php', {
    booking_id: id,
    appointment_date: newDate,
    appointment_time: newTime,
    staff_id: newStaffId,
    csrf_token: CSRF_TOKEN,
  });
  if (res.ok) {
    showToast('Appointment moved.');
    location.reload();
  } else {
    showToast(res.error || 'Could not move appointment.', 'error');
  }
}

function offsetToTime(offsetY) {
  const minutes = Math.round(offsetY / PX_PER_MIN);
  const snapped = Math.max(0, Math.round(minutes / SLOT_MINUTES) * SLOT_MINUTES);
  const totalMin = START_HOUR * 60 + snapped;
  const hh = String(Math.floor(totalMin / 60)).padStart(2, '0');
  const mm = String(totalMin % 60).padStart(2, '0');
  return hh + ':' + mm;
}

document.querySelectorAll('.cal-item').forEach(attachDrag);
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
