<?php
header('Content-Type: text/html; charset=utf-8');
$date = $_GET['date'] ?? date('Y-m-d');
$dateObj = DateTime::createFromFormat('Y-m-d', $date) ?: new DateTime();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Calendar - Diamond Nails</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; }
    .container { max-width: 1400px; margin: 0 auto; padding: 20px; }

    .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 15px; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .header h1 { font-size: 24px; color: #333; }
    .header .date-nav { display: flex; gap: 10px; align-items: center; }
    .header button { padding: 8px 16px; background: #c9947f; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; }
    .header button:hover { opacity: 0.9; }
    .header .date-text { font-weight: 600; min-width: 150px; text-align: center; }
    .btn-new { background: #059669 !important; }

    .calendar { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .calendar-grid { display: grid; grid-template-columns: 80px repeat(4, 1fr); border-collapse: collapse; }
    .calendar-header { display: contents; }
    .cell-header { background: #f5f5f5; padding: 12px; border: 1px solid #eee; font-weight: 600; text-align: center; border-right: 1px solid #ddd; }
    .cell-header:last-child { border-right: none; }

    .time-row { display: contents; }
    .time-label { background: #fafafa; padding: 12px; border: 1px solid #eee; font-weight: 600; text-align: center; font-size: 12px; border-right: 1px solid #ddd; }
    .time-slot { border: 1px solid #eee; position: relative; min-height: 100px; background: white; }
    .time-slot:last-child { border-right: none; }

    .apt { position: absolute; left: 2px; right: 2px; background: #c9947f; color: white; padding: 6px; border-radius: 3px; font-size: 10px; cursor: grab; border: 1px solid #b88470; overflow: hidden; }
    .apt:hover { box-shadow: 0 2px 6px rgba(0,0,0,0.2); }
    .apt.drag { opacity: 0.6; }
    .time-slot.over { background: #f0f0f0; }

    .apt-name { font-weight: 700; }
    .apt-service { font-size: 9px; }

    .staff-maria { background: #f8b4d8 !important; color: #333 !important; }
    .staff-susan { background: #b4d8f8 !important; color: #333 !important; }
    .staff-laura { background: #d8b4f8 !important; color: #333 !important; }
    .staff-anna { background: #b4f8d8 !important; color: #333 !important; }

    .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
    .modal.show { display: flex; align-items: center; justify-content: center; }
    .modal-content { background: white; padding: 30px; border-radius: 8px; max-width: 400px; width: 90%; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    .modal-header { font-size: 18px; font-weight: 700; margin-bottom: 20px; }
    .modal-close { float: right; font-size: 24px; cursor: pointer; color: #999; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 12px; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px; }
    .form-group textarea { resize: vertical; }
    .btn-submit { background: #059669; color: white; border: none; padding: 10px 16px; border-radius: 4px; cursor: pointer; font-weight: 600; width: 100%; }
    .btn-submit:hover { opacity: 0.9; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>📅 Calendar</h1>
      <div class="date-nav">
        <button onclick="navigate(-1)">←</button>
        <div class="date-text"><?= $dateObj->format('M d, Y') ?></div>
        <button onclick="navigate(1)">→</button>
      </div>
      <button onclick="goToday()">Today</button>
      <button class="btn-new" onclick="openModal()">+ New Appointment</button>
    </div>

    <div class="calendar">
      <div class="calendar-grid">
        <div class="cell-header">Time</div>
        <div class="cell-header">👩 Maria</div>
        <div class="cell-header">👩 Susan</div>
        <div class="cell-header">👩 Laura</div>
        <div class="cell-header">👩 Anna</div>

        <?php for ($h = 8; $h <= 18; $h++): ?>
          <div class="time-row">
            <div class="time-label"><?= sprintf('%d:00', $h) ?></div>
            <div class="time-slot" data-staff="maria" data-hour="<?= $h ?>" data-date="<?= $date ?>"></div>
            <div class="time-slot" data-staff="susan" data-hour="<?= $h ?>" data-date="<?= $date ?>"></div>
            <div class="time-slot" data-staff="laura" data-hour="<?= $h ?>" data-date="<?= $date ?>"></div>
            <div class="time-slot" data-staff="anna" data-hour="<?= $h ?>" data-date="<?= $date ?>"></div>
          </div>
        <?php endfor; ?>
      </div>
    </div>
  </div>

  <div id="modal" class="modal">
    <div class="modal-content">
      <span class="modal-close" onclick="closeModal()">&times;</span>
      <div class="modal-header">New Appointment</div>
      <form onsubmit="submitForm(event)">
        <div class="form-group">
          <label>Client Name *</label>
          <input type="text" id="name" required>
        </div>
        <div class="form-group">
          <label>Service *</label>
          <select id="service" required>
            <option value="">Select service</option>
            <option>Classic Manicure</option>
            <option>Gel Manicure</option>
            <option>Classic Pedicure</option>
            <option>Gel Pedicure</option>
            <option>Acrylic Full Set</option>
          </select>
        </div>
        <div class="form-group">
          <label>Staff *</label>
          <select id="staff" required>
            <option value="">Select staff</option>
            <option value="maria">Maria</option>
            <option value="susan">Susan</option>
            <option value="laura">Laura</option>
            <option value="anna">Anna</option>
          </select>
        </div>
        <div class="form-group">
          <label>Date *</label>
          <input type="date" id="date" value="<?= $date ?>" required>
        </div>
        <div class="form-group">
          <label>Time *</label>
          <input type="time" id="time" value="09:00" required>
        </div>
        <div class="form-group">
          <label>Duration (min)</label>
          <input type="number" id="duration" value="60" min="30">
        </div>
        <button type="submit" class="btn-submit">Add Appointment</button>
      </form>
    </div>
  </div>

  <script>
    let drag = null;
    const STAFF_CLASS = { maria: 'staff-maria', susan: 'staff-susan', laura: 'staff-laura', anna: 'staff-anna' };

    document.querySelectorAll('.time-slot').forEach(slot => {
      slot.addEventListener('dragover', (e) => { if (drag) { e.preventDefault(); slot.classList.add('over'); } });
      slot.addEventListener('dragleave', () => slot.classList.remove('over'));
      slot.addEventListener('drop', () => {
        if (!drag) return;
        slot.classList.remove('over');
        const [h, m] = drag.dataset.time.split(':');
        const newTime = String(parseInt(slot.dataset.hour)).padStart(2, '0') + ':' + m;
        alert('Rescheduled to ' + newTime + ' ✅');
        drag = null;
      });
    });

    function openModal() { document.getElementById('modal').classList.add('show'); }
    function closeModal() { document.getElementById('modal').classList.remove('show'); }
    function navigate(dir) {
      const d = new Date('<?= $date ?>');
      d.setDate(d.getDate() + dir);
      location.href = '?date=' + d.toISOString().split('T')[0];
    }
    function goToday() { location.href = '?date=' + new Date().toISOString().split('T')[0]; }
    function submitForm(e) {
      e.preventDefault();
      alert('✅ Appointment added!\n' + document.getElementById('name').value + ' - ' + document.getElementById('service').value);
      closeModal();
    }

    window.onclick = (e) => {
      const m = document.getElementById('modal');
      if (e.target === m) closeModal();
    };
  </script>
</body>
</html>
