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

.calendar { display: grid; grid-template-columns: 50px repeat(3, 1fr); gap: 0; border: 1px solid #ddd; background: white; }
.header { display: grid; grid-template-columns: 50px repeat(3, 1fr); background: #f5f5f5; border-bottom: 1px solid #ddd; height: 60px; }
.header-cell { padding: 8px; text-align: center; font-weight: 600; font-size: 12px; border-right: 1px solid #eee; display: flex; align-items: center; justify-content: center; }
.header-cell:last-child { border-right: none; }

.time-cell { padding: 4px; font-size: 11px; color: #999; text-align: center; border-right: 1px solid #eee; background: #fafafa; min-height: 90px; display: flex; align-items: flex-start; justify-content: center; }
.slot { border-right: 1px solid #ddd; border-bottom: 1px solid #eee; min-height: 90px; position: relative; }
.slot:last-child { border-right: none; }

.apt { position: absolute; left: 4px; right: 4px; background: #c9947f; color: white; padding: 6px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: grab; border: 1px solid #b88470; user-select: none; }
.apt:hover { box-shadow: 0 2px 8px rgba(201,148,127,0.3); transform: scale(1.02); }
.apt.drag { opacity: 0.7; z-index: 1000; }
.slot.over { background: rgba(201,148,127,0.1); }
</style>

<div class="controls">
  <div class="nav-buttons">
    <a href="?view=day&date=<?= $date ?>" class="btn <?= $view === 'day' ? 'active' : '' ?>">Day</a>
    <a href="?view=week&date=<?= $date ?>" class="btn <?= $view === 'week' ? 'active' : '' ?>">Week</a>
    <a href="?view=month&date=<?= $date ?>" class="btn <?= $view === 'month' ? 'active' : '' ?>">Month</a>
  </div>
  <div class="date-nav">
    <button onclick="location='?view=<?= $view ?>&date=<?= (clone $dateObj)->modify('-1 day')->format('Y-m-d') ?>'">←</button>
    <div class="date-text"><?= $dateObj->format('l, F j') ?></div>
    <button onclick="location='?view=<?= $view ?>&date=<?= (clone $dateObj)->modify('+1 day')->format('Y-m-d') ?>'">→</button>
  </div>
  <button class="btn btn-today" onclick="location='?view=<?= $view ?>&date=<?= date('Y-m-d') ?>'">Today</button>
  <button class="btn btn-new" onclick="location='<?= BASE_PATH ?>/#booking'">+ New Booking</button>
</div>

<div class="header">
  <div class="header-cell"></div>
  <div class="header-cell" style="border-bottom: 3px solid #4CAF50;">T1</div>
  <div class="header-cell" style="border-bottom: 3px solid #2196F3;">T2</div>
  <div class="header-cell" style="border-bottom: 3px solid #999;">U</div>
</div>

<div class="calendar">
  <?php for ($h = 8; $h <= 18; $h++): ?>
    <div class="time-cell"><?= sprintf('%d:00', $h) ?></div>
    <div class="slot" data-staff="1" data-date="<?= $date ?>"></div>
    <div class="slot" data-staff="2" data-date="<?= $date ?>"></div>
    <div class="slot" data-staff="unassigned" data-date="<?= $date ?>"></div>
  <?php endfor; ?>
</div>

<script>
let drag = null;
const CALENDAR_HOURS = 11;
const CALENDAR_MINUTES = 660;

document.addEventListener('DOMContentLoaded', function() {
  const date = '<?= $date ?>';

  fetch('<?= BASE_PATH ?>/api/calendar.php?date=' + date)
    .then(r => r.ok ? r.json() : Promise.resolve([]))
    .then(data => {
      if (!Array.isArray(data)) return;

      data.forEach(apt => {
        if (!apt.appointment_time) return;
        const slot = document.querySelector(`[data-staff="${apt.staff_id || 'unassigned'}"][data-date="${date}"]`);
        if (!slot) return;

        const [h, m] = apt.appointment_time.split(':').map(Number);
        const top = ((h - 8) * 60 + m) * (100 / CALENDAR_MINUTES) + '%';
        const height = (Math.max(30, apt.duration || 60) / CALENDAR_MINUTES) * 100 + '%';

        const el = document.createElement('div');
        el.className = 'apt';
        el.draggable = true;
        el.dataset.id = apt.id;
        el.dataset.date = date;
        el.dataset.time = apt.appointment_time;
        el.style.top = top;
        el.style.height = height;
        el.innerHTML = `<div style="font-weight:700;">${apt.full_name}</div><div style="font-size:10px;">${apt.service_name}</div>`;

        el.addEventListener('dragstart', () => { drag = el; el.classList.add('drag'); });
        el.addEventListener('dragend', () => { el.classList.remove('drag'); document.querySelectorAll('.slot').forEach(s => s.classList.remove('over')); });
        el.addEventListener('click', () => alert('Appointment: ' + apt.full_name + '\nService: ' + apt.service_name + '\nTime: ' + apt.appointment_time));

        slot.appendChild(el);
      });

      document.querySelectorAll('.slot').forEach(slot => {
        slot.addEventListener('dragover', (e) => { if (drag) { e.preventDefault(); slot.classList.add('over'); } });
        slot.addEventListener('dragleave', () => slot.classList.remove('over'));
        slot.addEventListener('drop', (e) => {
          if (!drag) return;
          e.preventDefault();
          slot.classList.remove('over');

          const rect = slot.getBoundingClientRect();
          const percent = (e.clientY - rect.top) / rect.height;
          const hour = Math.floor(8 + percent * CALENDAR_HOURS);
          const minute = Math.round((percent * CALENDAR_HOURS % 1) * 60);
          const time = String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0');

          fetch('<?= BASE_PATH ?>/api/reschedule_appointment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: drag.dataset.id, appointment_date: slot.dataset.date, appointment_time: time, staff_id: slot.dataset.staff === 'unassigned' ? null : slot.dataset.staff })
          })
          .then(r => r.json())
          .then(d => { if (d.success) location.reload(); else alert('Error'); })
          .catch(() => alert('Error'));

          drag = null;
        });
      });
    })
    .catch(e => console.error('Calendar load error:', e));
});
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
