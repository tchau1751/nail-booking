<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$pageTitle = 'Calendar';
$pageSubtitle = 'Every appointment, at a glance';
$activeNav = 'calendar';

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/layout_start.php';

$view = $_GET['view'] ?? 'day';
$date = $_GET['date'] ?? date('Y-m-d');
$dateObj = DateTime::createFromFormat('Y-m-d', $date) ?: new DateTime();
?>

<style>
* { box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
.page-header { padding: 12px 28px !important; margin-bottom: 0 !important; }
.page-title { font-size: 18px !important; }
.page-subtitle { display: none; }
.main-content { padding: 0 !important; }

.controls { display: flex; gap: 12px; padding: 12px 16px; background: #f9f9f9; border-bottom: 1px solid #ddd; flex-wrap: wrap; align-items: center; }
.btn { padding: 6px 12px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 12px; }
.btn.active { background: #c9947f; color: white; border-color: #c9947f; }
.btn:hover { background: #f5f5f5; }
.btn-today { background: #c9947f; color: white; border: none; }
.btn-new { background: #059669; color: white; border: none; }
.btn-today:hover, .btn-new:hover { opacity: 0.9; }

.nav-buttons { display: flex; gap: 4px; }
.date-nav { display: flex; gap: 6px; align-items: center; }
.date-nav button { width: 28px; height: 28px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer; }
.date-text { font-weight: 600; min-width: 120px; text-align: center; font-size: 12px; }

.calendar-container { overflow-x: auto; }
.calendar { display: grid; grid-template-columns: 80px repeat(auto-fit, minmax(200px, 1fr)); gap: 0; border: 1px solid #ddd; background: white; margin: 0; }
.header { display: grid; grid-template-columns: 80px repeat(auto-fit, minmax(200px, 1fr)); background: #f5f5f5; border-bottom: 1px solid #ddd; height: 60px; position: sticky; top: 0; z-index: 10; }
.header-cell { padding: 8px; text-align: center; font-weight: 600; font-size: 12px; border-right: 1px solid #eee; display: flex; align-items: center; justify-content: center; }
.header-cell:last-child { border-right: none; }

.time-cell { padding: 8px; font-size: 11px; color: #666; text-align: center; border-right: 1px solid #eee; background: #fafafa; min-height: 100px; display: flex; align-items: flex-start; justify-content: center; font-weight: 600; }
.slot { border-right: 1px solid #ddd; border-bottom: 1px solid #eee; min-height: 100px; position: relative; background: white; }
.slot:last-child { border-right: none; }

.apt { position: absolute; left: 4px; right: 4px; background: #c9947f; color: white; padding: 8px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: grab; border: 1px solid #b88470; user-select: none; overflow: hidden; text-overflow: ellipsis; }
.apt:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.2); transform: scale(1.02); z-index: 100; }
.apt.drag { opacity: 0.6; z-index: 1000; }
.slot.over { background: rgba(201,148,127,0.1); }

.apt-name { font-weight: 700; white-space: nowrap; }
.apt-service { font-size: 10px; white-space: nowrap; }
.apt-time { font-size: 9px; opacity: 0.9; }

.staff-maria { background: #f8b4d8 !important; color: #333 !important; }
.staff-susan { background: #b4d8f8 !important; color: #333 !important; }
.staff-laura { background: #d8b4f8 !important; color: #333 !important; }
.staff-anna { background: #b4f8d8 !important; color: #333 !important; }
.staff-unknown { background: #f0f0f0 !important; color: #666 !important; }

.modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
.modal.show { display: flex; align-items: center; justify-content: center; }
.modal-content { background-color: white; padding: 20px; border-radius: 8px; max-width: 500px; width: 90%; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
.modal-header { font-size: 18px; font-weight: 700; margin-bottom: 15px; }
.modal-close { float: right; font-size: 24px; cursor: pointer; color: #999; }
.form-group { margin-bottom: 12px; }
.form-group label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 12px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px; }
.form-group textarea { resize: vertical; }
.btn-submit { background: #059669; color: white; border: none; padding: 10px 16px; border-radius: 4px; cursor: pointer; font-weight: 600; }
.btn-submit:hover { opacity: 0.9; }
</style>

<div class="controls">
  <div class="nav-buttons">
    <a href="?view=day&date=<?= $date ?>" class="btn <?= $view === 'day' ? 'active' : '' ?>">Day</a>
    <a href="?view=week&date=<?= $date ?>" class="btn <?= $view === 'week' ? 'active' : '' ?>">Week</a>
    <a href="?view=month&date=<?= $date ?>" class="btn <?= $view === 'month' ? 'active' : '' ?>">Month</a>
  </div>
  <div class="date-nav">
    <button onclick="location='?view=<?= $view ?>&date=<?= (clone $dateObj)->modify('-1 day')->format('Y-m-d') ?>'">←</button>
    <div class="date-text"><?= $dateObj->format('M d, Y') ?></div>
    <button onclick="location='?view=<?= $view ?>&date=<?= (clone $dateObj)->modify('+1 day')->format('Y-m-d') ?>'">→</button>
  </div>
  <button class="btn btn-today" onclick="location='?view=<?= $view ?>&date=<?= date('Y-m-d') ?>'">Today</button>
  <button class="btn btn-new" onclick="openNewBookingModal()">+ New Client</button>
</div>

<div class="header">
  <div class="header-cell" style="background: white;">Time</div>
  <div class="header-cell" style="border-bottom: 3px solid #f8b4d8;">👩 Maria</div>
  <div class="header-cell" style="border-bottom: 3px solid #b4d8f8;">👩 Susan</div>
  <div class="header-cell" style="border-bottom: 3px solid #d8b4f8;">👩 Laura</div>
  <div class="header-cell" style="border-bottom: 3px solid #b4f8d8;">👩 Anna</div>
</div>

<div class="calendar-container">
<div class="calendar">
  <?php for ($h = 8; $h <= 18; $h++): ?>
    <div class="time-cell"><?= sprintf('%d:00', $h) ?></div>
    <div class="slot" data-staff="maria" data-date="<?= $date ?>" data-hour="<?= $h ?>"></div>
    <div class="slot" data-staff="susan" data-date="<?= $date ?>" data-hour="<?= $h ?>"></div>
    <div class="slot" data-staff="laura" data-date="<?= $date ?>" data-hour="<?= $h ?>"></div>
    <div class="slot" data-staff="anna" data-date="<?= $date ?>" data-hour="<?= $h ?>"></div>
  <?php endfor; ?>
</div>
</div>

<div id="bookingModal" class="modal">
  <div class="modal-content">
    <span class="modal-close" onclick="closeModal()">&times;</span>
    <div class="modal-header">New Appointment</div>
    <form onsubmit="submitBooking(event)">
      <div class="form-group">
        <label>Client Name *</label>
        <input type="text" id="clientName" required>
      </div>
      <div class="form-group">
        <label>Service *</label>
        <select id="service" required>
          <option value="">Select a service</option>
          <option value="Classic Manicure">Classic Manicure</option>
          <option value="Gel Manicure">Gel Manicure</option>
          <option value="Classic Pedicure">Classic Pedicure</option>
          <option value="Gel Pedicure">Gel Pedicure</option>
          <option value="Acrylic Full Set">Acrylic Full Set</option>
          <option value="Nail Art Design">Nail Art Design</option>
        </select>
      </div>
      <div class="form-group">
        <label>Staff *</label>
        <select id="staffName" required>
          <option value="">Select staff</option>
          <option value="maria">Maria</option>
          <option value="susan">Susan</option>
          <option value="laura">Laura</option>
          <option value="anna">Anna</option>
        </select>
      </div>
      <div class="form-group">
        <label>Date *</label>
        <input type="date" id="appointmentDate" value="<?= $date ?>" required>
      </div>
      <div class="form-group">
        <label>Time *</label>
        <input type="time" id="appointmentTime" value="09:00" required>
      </div>
      <div class="form-group">
        <label>Duration (minutes)</label>
        <input type="number" id="duration" value="60" min="30" max="240">
      </div>
      <div class="form-group">
        <label>Phone</label>
        <input type="tel" id="phone">
      </div>
      <div class="form-group">
        <label>Notes</label>
        <textarea id="notes" rows="3"></textarea>
      </div>
      <button type="submit" class="btn-submit">Add Appointment</button>
    </form>
  </div>
</div>

<script>
let drag = null;
const STAFF_COLORS = {
  maria: 'staff-maria',
  susan: 'staff-susan',
  laura: 'staff-laura',
  anna: 'staff-anna'
};

document.addEventListener('DOMContentLoaded', function() {
  loadAppointments();
  setupSlotDragDrop();
});

function loadAppointments() {
  const date = '<?= $date ?>';
  fetch('<?= BASE_PATH ?>/api/calendar.php?date=' + date)
    .then(r => r.ok ? r.json() : Promise.resolve([]))
    .then(data => {
      if (!Array.isArray(data)) return;
      data.forEach(apt => {
        if (!apt.appointment_time) return;
        const slots = document.querySelectorAll(`[data-staff="${(apt.staff_id || 'maria').toLowerCase()}"][data-date="${date}"]`);
        if (slots.length === 0) return;

        const [h, m] = apt.appointment_time.split(':').map(Number);
        const slot = Array.from(slots).find(s => parseInt(s.dataset.hour) === h);
        if (!slot) return;

        const el = document.createElement('div');
        el.className = `apt ${STAFF_COLORS[apt.staff_id] || 'staff-unknown'}`;
        el.draggable = true;
        el.dataset.id = apt.id;
        el.dataset.staff = apt.staff_id || 'maria';
        el.dataset.time = apt.appointment_time;
        el.style.top = ((m / 60) * 100) + '%';
        el.style.height = Math.max(30, (apt.duration || 60) / 60 * 100) + '%';
        el.innerHTML = `
          <div class="apt-name">${apt.full_name}</div>
          <div class="apt-service">${apt.service_name}</div>
          <div class="apt-time">${apt.appointment_time}</div>
        `;

        el.addEventListener('dragstart', () => { drag = el; el.classList.add('drag'); });
        el.addEventListener('dragend', () => { el.classList.remove('drag'); document.querySelectorAll('.slot').forEach(s => s.classList.remove('over')); });
        el.addEventListener('click', () => alert('📅 ' + apt.full_name + '\n💅 ' + apt.service_name + '\n⏰ ' + apt.appointment_time));

        slot.appendChild(el);
      });
    })
    .catch(e => console.error('Calendar load error:', e));
}

function setupSlotDragDrop() {
  document.querySelectorAll('.slot').forEach(slot => {
    slot.addEventListener('dragover', (e) => { if (drag) { e.preventDefault(); slot.classList.add('over'); } });
    slot.addEventListener('dragleave', () => slot.classList.remove('over'));
    slot.addEventListener('drop', (e) => {
      if (!drag) return;
      e.preventDefault();
      slot.classList.remove('over');

      const rect = slot.getBoundingClientRect();
      const parentRect = slot.parentElement.getBoundingClientRect();
      const percent = (e.clientY - rect.top) / rect.height;
      const hour = parseInt(slot.dataset.hour);
      const minute = Math.round(percent * 60);
      const time = String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0');

      fetch('<?= BASE_PATH ?>/api/reschedule_appointment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          id: drag.dataset.id,
          appointment_date: slot.dataset.date,
          appointment_time: time,
          staff_id: slot.dataset.staff
        })
      })
      .then(r => r.json())
      .then(d => { if (d.success) location.reload(); else alert('Reschedule failed'); })
      .catch(() => alert('Error'));

      drag = null;
    });
  });
}

function openNewBookingModal() {
  document.getElementById('bookingModal').classList.add('show');
}

function closeModal() {
  document.getElementById('bookingModal').classList.remove('show');
}

function submitBooking(e) {
  e.preventDefault();
  const data = {
    full_name: document.getElementById('clientName').value,
    service_name: document.getElementById('service').value,
    staff_id: document.getElementById('staffName').value,
    appointment_date: document.getElementById('appointmentDate').value,
    appointment_time: document.getElementById('appointmentTime').value,
    duration: document.getElementById('duration').value,
    phone: document.getElementById('phone').value,
    notes: document.getElementById('notes').value
  };

  fetch('<?= BASE_PATH ?>/api/book.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  })
  .then(r => r.json())
  .then(d => {
    if (d.success) {
      alert('✅ Appointment added!');
      closeModal();
      location.reload();
    } else {
      alert('❌ ' + (d.error || 'Booking failed'));
    }
  })
  .catch(() => alert('Error'));
}

window.addEventListener('click', (e) => {
  const modal = document.getElementById('bookingModal');
  if (e.target === modal) closeModal();
});
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
