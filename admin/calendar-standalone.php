<?php
// ============================================================
//  Day calendar — one column per technician. Drag a booking to
//  move it, tap it to change the time or status, "+ New" to book.
//  Standalone (no admin chrome), for a tablet at the front desk.
//
//  Everything on it is the signed-in salon's own: its technicians,
//  its menu, its bookings. Moves go through api/reschedule.php and
//  api/updateappointment.php, which check the salon again.
// ============================================================
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

requireRole('front_desk');   // the same people who can open the booking calendar

$asked   = is_string($_GET['date'] ?? null) ? $_GET['date'] : '';
$dateObj = DateTime::createFromFormat('!Y-m-d', $asked);
if (!$dateObj || $dateObj->format('Y-m-d') !== $asked) $dateObj = new DateTime('today');
$date = $dateObj->format('Y-m-d');
$prev = (clone $dateObj)->modify('-1 day')->format('Y-m-d');
$next = (clone $dateObj)->modify('+1 day')->format('Y-m-d');

$tid         = tenantId();
$technicians = fetchAll('SELECT id, name FROM technicians WHERE tenant_id = ? AND is_active = 1 ORDER BY display_order, name', [$tid]);
$services    = fetchAll('SELECT id, name, duration_minutes FROM services WHERE tenant_id = ? AND is_active = 1 ORDER BY display_order, name', [$tid]);

$palette = ['#f8b4d8', '#b4d8f8', '#d8b4f8', '#b4f8d8', '#f8d8ae', '#aee0f8'];
$staffColors = [];
foreach ($technicians as $i => $t) {
    $staffColors[$t['id']] = $palette[$i % count($palette)];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Calendar - <?= e(settings()['business_name'] ?? 'Diamond Nails') ?></title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; padding: 10px; }
    .container { max-width: 100%; margin: 0 auto; }

    .header { background: linear-gradient(135deg, #c9947f 0%, #a67560 100%); color: white; padding: 12px; border-radius: 6px; margin-bottom: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.12); }
    .header h1 { font-size: 18px; margin-bottom: 8px; margin: 0 0 8px 0; }
    .controls { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .btn { padding: 6px 12px; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; border-radius: 3px; cursor: pointer; font-weight: 600; font-size: 12px; }
    .btn:hover { background: rgba(255,255,255,0.3); }
    .btn.active { background: white; color: #c9947f; }
    .date-text { font-weight: 600; min-width: 110px; text-align: center; font-size: 13px; }

    .calendar { background: white; border-radius: 6px; overflow: auto; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
    .calendar-grid { display: grid; grid-template-columns: 50px repeat(<?= count($technicians) ?>, 1fr); border: 1px solid #ddd; }
    .cell { border: 1px solid #eee; padding: 8px; min-height: 60px; position: relative; font-size: 12px; }
    .cell.header { background: #f5f5f5; font-weight: 600; border-bottom: 2px solid #c9947f; padding: 6px 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cell.time { background: #fafafa; text-align: center; font-weight: 600; font-size: 11px; padding: 4px; }
    .name-short { display: none; }
    @media (max-width: 600px) {
      .name-full { display: none; }
      .name-short { display: inline; }
    }

    .apt { position: absolute; left: 2px; right: 2px; padding: 4px; border-radius: 3px; cursor: grab; font-size: 10px; border: 1px solid rgba(0,0,0,0.1); color: #2a2a2a; }
    .apt:hover { box-shadow: 0 1px 4px rgba(0,0,0,0.2); z-index: 10; }
    .apt.drag { opacity: 0.5; }
    .apt-name { font-weight: 700; }
    .apt-time { font-size: 9px; opacity: 0.8; }

    .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
    .modal.show { display: flex; }
    .modal-content { background: white; padding: 20px; border-radius: 6px; width: 95%; max-width: 450px; box-shadow: 0 6px 20px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; }
    .modal-header { font-size: 16px; font-weight: 700; margin-bottom: 15px; }
    .modal-close { float: right; font-size: 24px; cursor: pointer; color: #999; line-height: 1; }
    .form-group { margin-bottom: 12px; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 12px; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; font-size: 14px; font-family: inherit; }
    .form-group input:focus, .form-group select:focus { outline: none; border-color: #c9947f; }
    .btn-submit { background: #059669; color: white; border: none; padding: 10px; border-radius: 3px; cursor: pointer; font-weight: 600; width: 100%; font-size: 13px; }
    .btn-submit:hover { opacity: 0.9; }

    @media (max-width: 768px) {
      body { padding: 8px; }
      .header { padding: 10px; margin-bottom: 10px; }
      .header h1 { font-size: 16px; margin-bottom: 6px; }
      .btn { padding: 5px 10px; font-size: 11px; }
      .date-text { font-size: 12px; min-width: 100px; }
      .calendar-grid { grid-template-columns: 45px repeat(<?= count($technicians) ?>, 1fr); }
      .cell { padding: 6px; min-height: 50px; font-size: 11px; }
      .cell.time { font-size: 10px; }
      .apt { font-size: 9px; padding: 3px; }
      .apt-time { font-size: 8px; }
      .modal-content { width: 98%; padding: 16px; }
      .form-group { margin-bottom: 10px; }
      .form-group label { font-size: 11px; }
      .form-group input, .form-group select { font-size: 13px; padding: 7px; }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="controls">
        <button class="btn" onclick="navigate(-1)">❮</button>
        <div class="date-text"><?= $dateObj->format('M d') ?></div>
        <button class="btn" onclick="navigate(1)">❯</button>
        <button class="btn" onclick="goToday()">Today</button>
        <button class="btn" onclick="openNewModal()" style="background: #059669; border-color: #059669; margin-left: auto;">+ New</button>
      </div>
    </div>

    <div id="statusBar" style="display:none; padding:8px 12px; border-radius:4px; margin-bottom:10px; font-size:12px; font-weight:600;"></div>

    <div class="calendar">
      <div class="calendar-grid">
        <div class="cell header">Time</div>
        <?php foreach ($technicians as $t): ?>
          <div class="cell header" title="<?= e($t['name']) ?>">
            <span class="name-full"><?= e($t['name']) ?></span>
            <span class="name-short"><?= e(mb_substr($t['name'], 0, 1)) ?></span>
          </div>
        <?php endforeach; ?>

        <?php for ($h = 8; $h <= 18; $h++): ?>
          <div class="cell time"><?= date('g A', mktime($h, 0)) ?></div>
          <?php foreach ($technicians as $t): ?>
            <div class="cell" data-staff="<?= (int)$t['id'] ?>" data-date="<?= e($date) ?>" data-hour="<?= $h ?>"></div>
          <?php endforeach; ?>
        <?php endfor; ?>
      </div>
    </div>
  </div>

  <div id="editModal" class="modal">
    <div class="modal-content">
      <span class="modal-close" onclick="closeModal()">&times;</span>
      <div class="modal-header">Edit Appointment</div>
      <form onsubmit="submitEdit(event)">
        <div class="form-group">
          <label>Client Name</label>
          <input type="text" id="editName" readonly style="background: #f5f5f5;">
        </div>
        <div class="form-group">
          <label>Service</label>
          <input type="text" id="editService" readonly style="background: #f5f5f5;">
        </div>
        <div class="form-group">
          <label>Time</label>
          <input type="time" id="editTime" required>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select id="editStatus">
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <button type="submit" class="btn-submit">Save</button>
      </form>
    </div>
  </div>

  <div id="newModal" class="modal">
    <div class="modal-content">
      <span class="modal-close" onclick="closeModal()">&times;</span>
      <div class="modal-header">New Appointment</div>
      <form onsubmit="submitNew(event)">
        <div class="form-group">
          <label>Client Name *</label>
          <input type="text" id="newName" required>
        </div>
        <div class="form-group">
          <label>Email *</label>
          <input type="email" id="newEmail" required>
        </div>
        <div class="form-group">
          <label>Phone *</label>
          <input type="tel" id="newPhone" required>
        </div>
        <div class="form-group">
          <label>Service *</label>
          <select id="newService" required>
            <option value="">Select service</option>
            <?php foreach ($services as $s): ?>
              <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Staff</label>
          <select id="newStaff">
            <option value="">Any</option>
            <?php foreach ($technicians as $t): ?>
              <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Date *</label>
          <input type="date" id="newDate" value="<?= e($date) ?>" required>
        </div>
        <div class="form-group">
          <label>Time *</label>
          <input type="time" id="newTime" required>
        </div>
        <button type="submit" class="btn-submit">Create</button>
      </form>
    </div>
  </div>

  <script>
    // Paths come from the server, so the page works in a subfolder on the shop
    // PC and at the web root online. updateappointment.php has no hyphen,
    // matching the file on the server.
    const API_BASE = <?= json_encode(BASE_PATH . '/api') ?>;
    const API = {
      calendar:   API_BASE + '/calendar.php',
      reschedule: API_BASE + '/reschedule.php',
      update:     API_BASE + '/updateappointment.php',
      book:       API_BASE + '/book.php'
    };
    const DAY   = <?= json_encode($date) ?>;
    const PREV  = <?= json_encode($prev) ?>;
    const NEXT  = <?= json_encode($next) ?>;
    const SALON = <?= json_encode(currentTenant()['slug']) ?>;

    let drag = null;
    let currentEditId = null;
    const COLORS = <?= json_encode($staffColors) ?>;

    document.addEventListener('DOMContentLoaded', loadAppointments);

    function status(msg, isError) {
      const bar = document.getElementById('statusBar');
      if (!msg) { bar.style.display = 'none'; return; }
      bar.style.display = 'block';
      bar.style.background = isError ? '#fee2e2' : '#dcfce7';
      bar.style.color = isError ? '#991b1b' : '#166534';
      bar.textContent = msg;
    }

    function loadAppointments() {
      const url = API.calendar + '?start=' + DAY + '&end=' + DAY;
      fetch(url)
        .then(r => {
          if (!r.ok) throw new Error('HTTP ' + r.status + ' from ' + url);
          return r.json();
        })
        .then(data => {
          if (!Array.isArray(data)) {
            status('API did not return a list. Response: ' + JSON.stringify(data), true);
            return;
          }
          const real = data.filter(a => a.start && a.id);
          status(real.length ? real.length + ' appointment(s) loaded' : 'No appointments on this date', false);
          data.forEach(apt => {
            if (!apt.start) return;
            const [aptDate, aptTime] = apt.start.split('T');
            if (aptDate !== DAY) return;

            const staffId = apt.extendedProps?.technician_id;
            const [h, m] = aptTime.split(':').map(Number);
            const cells = document.querySelectorAll(`[data-staff="${staffId}"][data-date="${DAY}"][data-hour="${h}"]`);
            if (cells.length === 0) return;

            const cell = cells[0];
            const el = document.createElement('div');
            el.className = 'apt';
            el.draggable = true;
            el.dataset.id = apt.id;
            el.dataset.aptData = JSON.stringify(apt);
            el.style.background = COLORS[staffId] || '#e5e5e5';
            el.style.top = ((m / 60) * 100) + '%';

            // The name was typed by a guest on the booking page: text, never markup.
            const nameEl = document.createElement('div');
            nameEl.className = 'apt-name';
            nameEl.textContent = apt.title.split(' — ')[0];
            const timeEl = document.createElement('div');
            timeEl.className = 'apt-time';
            timeEl.textContent = aptTime;
            el.append(nameEl, timeEl);

            el.addEventListener('dragstart', () => { drag = el; el.classList.add('drag'); });
            el.addEventListener('dragend', () => { drag = null; el.classList.remove('drag'); });
            el.addEventListener('click', (e) => { e.stopPropagation(); openEditModal(JSON.parse(el.dataset.aptData)); });

            cell.appendChild(el);
          });
        })
        .catch(e => {
          status('Could not load appointments — ' + e.message, true);
          console.error(e);
        });
    }

    document.querySelectorAll('[data-staff]').forEach(cell => {
      cell.addEventListener('dragover', (e) => { if (drag) { e.preventDefault(); cell.style.background = '#f0f0f0'; } });
      cell.addEventListener('dragleave', () => cell.style.background = '');
      cell.addEventListener('drop', (e) => {
        if (!drag) return;
        e.preventDefault();
        cell.style.background = '';

        const hour = parseInt(cell.dataset.hour);
        const staffId = parseInt(cell.dataset.staff);
        const time = String(hour).padStart(2, '0') + ':00';

        fetch(API.reschedule, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            appointment_id: parseInt(drag.dataset.id),
            new_date: cell.dataset.date,
            new_time: time,
            technician_id: staffId
          })
        })
        .then(r => r.json())
        .then(d => {
          if (d.success) {
            alert('✅ Rescheduled');
            location.reload();
          } else {
            alert('❌ ' + (d.error || 'Failed'));
          }
        })
        .catch(e => alert('❌ Error'));
      });
    });

    function navigate(dir) {
      location.href = '?date=' + (dir < 0 ? PREV : NEXT);
    }

    function goToday() {
      location.href = location.pathname;   // the server knows the salon's today
    }

    function openEditModal(apt) {
      document.getElementById('editName').value = apt.title.split(' — ')[0];
      document.getElementById('editService').value = apt.title.split(' — ')[1] || '';
      const [, time] = apt.start.split('T');
      document.getElementById('editTime').value = time;
      document.getElementById('editStatus').value = apt.extendedProps?.status || 'pending';
      currentEditId = apt.id;
      document.getElementById('editModal').classList.add('show');
    }

    function openNewModal() {
      document.getElementById('newModal').classList.add('show');
    }

    function closeModal() {
      document.getElementById('editModal').classList.remove('show');
      document.getElementById('newModal').classList.remove('show');
    }

    function submitEdit(e) {
      e.preventDefault();
      fetch(API.update, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          appointment_id: currentEditId,
          new_time: document.getElementById('editTime').value,
          status: document.getElementById('editStatus').value
        })
      })
      .then(r => r.json())
      .then(d => {
        if (d.success) {
          closeModal();
          location.reload();
        } else {
          alert('❌ ' + (d.error || 'Failed'));
        }
      });
    }

    function submitNew(e) {
      e.preventDefault();
      fetch(API.book, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          salon: SALON,
          full_name: document.getElementById('newName').value,
          email: document.getElementById('newEmail').value,
          phone: document.getElementById('newPhone').value,
          service_id: parseInt(document.getElementById('newService').value),
          technician_id: document.getElementById('newStaff').value ? parseInt(document.getElementById('newStaff').value) : null,
          appointment_date: document.getElementById('newDate').value,
          start_time: document.getElementById('newTime').value
        })
      })
      .then(r => r.json())
      .then(d => {
        if (d.success) {
          closeModal();
          location.reload();
        } else {
          alert('❌ ' + (d.error || 'Failed'));
        }
      });
    }

    window.addEventListener('click', (e) => {
      if (e.target.id === 'editModal' || e.target.id === 'newModal') closeModal();
    });
  </script>
</body>
</html>
