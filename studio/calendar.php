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
  <title>Admin Calendar - Diamond Nails</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #1a1a1a; color: #333; }
    .container { max-width: 1600px; margin: 0 auto; padding: 20px; }

    .header { background: linear-gradient(135deg, #c9947f 0%, #a67560 100%); color: white; padding: 25px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 20px; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
    .header h1 { font-size: 28px; font-weight: 700; }
    .header .controls { display: flex; gap: 12px; align-items: center; margin-left: auto; flex-wrap: wrap; }
    .header button { padding: 10px 16px; background: white; color: #c9947f; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; }
    .header button:hover { opacity: 0.9; }
    .header .date-text { color: white; font-weight: 600; min-width: 180px; text-align: center; }
    .btn-new { background: #059669 !important; color: white !important; }

    .calendar-wrapper { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .calendar-grid { display: grid; grid-template-columns: 70px repeat(4, 1fr); gap: 0; }
    .header-cell { background: #f0f0f0; padding: 15px; font-weight: 700; text-align: center; border-right: 1px solid #ddd; border-bottom: 3px solid #c9947f; }
    .header-cell:last-child { border-right: none; }

    .time-label { background: #f9f9f9; padding: 12px; font-weight: 600; text-align: center; font-size: 12px; border-right: 1px solid #ddd; border-bottom: 1px solid #eee; }
    .time-slot { border-right: 1px solid #ddd; border-bottom: 1px solid #eee; position: relative; min-height: 120px; background: white; transition: background 0.2s; }
    .time-slot:hover { background: #fafafa; }
    .time-slot:last-child { border-right: none; }

    .apt { position: absolute; left: 4px; right: 4px; padding: 8px; border-radius: 4px; font-size: 11px; cursor: grab; border: 1px solid rgba(0,0,0,0.1); overflow: hidden; user-select: none; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: all 0.2s; }
    .apt:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
    .apt.drag { opacity: 0.5; cursor: grabbing; }
    .time-slot.over { background: #e8f5e9; }

    .apt-name { font-weight: 700; }
    .apt-service { font-size: 10px; opacity: 0.8; }
    .apt-time { font-size: 9px; opacity: 0.7; margin-top: 2px; }

    .staff-1 { background: #f8b4d8; color: #333; }
    .staff-2 { background: #b4d8f8; color: #333; }
    .staff-3 { background: #d8b4f8; color: #333; }
    .staff-4 { background: #b4f8d8; color: #333; }

    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
    .stat-card { background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #c9947f; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .stat-card h3 { font-size: 12px; color: #666; margin-bottom: 5px; }
    .stat-card .value { font-size: 28px; font-weight: 700; color: #c9947f; }

    .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); }
    .modal.show { display: flex; align-items: center; justify-content: center; }
    .modal-content { background: white; padding: 30px; border-radius: 8px; max-width: 450px; width: 90%; box-shadow: 0 8px 24px rgba(0,0,0,0.2); }
    .modal-header { font-size: 20px; font-weight: 700; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
    .modal-close { font-size: 28px; cursor: pointer; color: #999; background: none; border: none; padding: 0; width: 24px; height: 24px; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px; color: #333; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #c9947f; box-shadow: 0 0 0 3px rgba(201, 148, 127, 0.1); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .btn-submit { background: #c9947f; color: white; border: none; padding: 12px; border-radius: 4px; cursor: pointer; font-weight: 600; width: 100%; font-size: 14px; }
    .btn-submit:hover { opacity: 0.9; }

    @media (max-width: 1200px) {
      .calendar-grid { grid-template-columns: 70px repeat(3, 1fr); }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>📅 Admin Calendar</h1>
      <div class="controls">
        <button onclick="navigate(-1)">← Prev</button>
        <div class="date-text"><?= $dateObj->format('l, F j, Y') ?></div>
        <button onclick="navigate(1)">Next →</button>
        <button onclick="goToday()">Today</button>
        <button class="btn-new" onclick="openModal()">+ New Booking</button>
      </div>
    </div>

    <div class="stats">
      <div class="stat-card">
        <h3>📊 Today's Bookings</h3>
        <div class="value">8</div>
      </div>
      <div class="stat-card">
        <h3>👩 Staff Online</h3>
        <div class="value">4</div>
      </div>
      <div class="stat-card">
        <h3>⏰ Next Appointment</h3>
        <div class="value">10:30</div>
      </div>
      <div class="stat-card">
        <h3>💰 Today's Revenue</h3>
        <div class="value">$480</div>
      </div>
    </div>

    <div class="calendar-wrapper">
      <div class="calendar-grid">
        <div class="header-cell">Time</div>
        <div class="header-cell">👩 Staff 1</div>
        <div class="header-cell">👩 Staff 2</div>
        <div class="header-cell">👩 Staff 3</div>
        <div class="header-cell">👩 Staff 4</div>

        <?php for ($h = 8; $h <= 18; $h++): ?>
          <div class="time-label"><?= sprintf('%d:00', $h) ?></div>
          <div class="time-slot staff-1" data-staff="1" data-hour="<?= $h ?>" data-date="<?= $date ?>"></div>
          <div class="time-slot staff-2" data-staff="2" data-hour="<?= $h ?>" data-date="<?= $date ?>"></div>
          <div class="time-slot staff-3" data-staff="3" data-hour="<?= $h ?>" data-date="<?= $date ?>"></div>
          <div class="time-slot staff-4" data-staff="4" data-hour="<?= $h ?>" data-date="<?= $date ?>"></div>
        <?php endfor; ?>
      </div>
    </div>
  </div>

  <div id="modal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        New Booking
        <button class="modal-close" onclick="closeModal()">×</button>
      </div>
      <form onsubmit="submitForm(event)">
        <div class="form-group">
          <label>Client Name *</label>
          <input type="text" id="name" required>
        </div>
        <div class="form-group">
          <label>Phone *</label>
          <input type="tel" id="phone" required>
        </div>
        <div class="form-group">
          <label>Service *</label>
          <select id="service" required>
            <option value="">Select service</option>
            <option>Classic Manicure - $25</option>
            <option>Gel Manicure - $40</option>
            <option>Classic Pedicure - $30</option>
            <option>Gel Pedicure - $45</option>
            <option>Acrylic Full Set - $60</option>
            <option>Nail Art Design - $50</option>
          </select>
        </div>
        <div class="form-group">
          <label>Staff *</label>
          <select id="staff" required>
            <option value="">Select staff</option>
            <option value="1">Staff 1</option>
            <option value="2">Staff 2</option>
            <option value="3">Staff 3</option>
            <option value="4">Staff 4</option>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Date *</label>
            <input type="date" id="date" value="<?= $date ?>" required>
          </div>
          <div class="form-group">
            <label>Time *</label>
            <input type="time" id="time" value="10:00" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Duration (min)</label>
            <input type="number" id="duration" value="60" min="30" max="240">
          </div>
          <div class="form-group">
            <label>Price</label>
            <input type="number" id="price" value="40" min="0" step="0.01">
          </div>
        </div>
        <div class="form-group">
          <label>Notes</label>
          <textarea id="notes" rows="3" placeholder="Special requests..."></textarea>
        </div>
        <button type="submit" class="btn-submit">✓ Book Appointment</button>
      </form>
    </div>
  </div>

  <script>
    let drag = null;

    document.querySelectorAll('.time-slot').forEach(slot => {
      slot.addEventListener('dragover', (e) => { if (drag) { e.preventDefault(); slot.classList.add('over'); } });
      slot.addEventListener('dragleave', () => slot.classList.remove('over'));
      slot.addEventListener('drop', () => {
        if (!drag) return;
        slot.classList.remove('over');
        alert('✓ Appointment rescheduled!');
        drag = null;
      });
    });

    function openModal() {
      document.getElementById('modal').classList.add('show');
      document.getElementById('date').value = '<?= $date ?>';
    }
    function closeModal() { document.getElementById('modal').classList.remove('show'); }

    function navigate(dir) {
      const d = new Date('<?= $date ?>');
      d.setDate(d.getDate() + dir);
      location.href = '?date=' + d.toISOString().split('T')[0];
    }

    function goToday() {
      location.href = '?date=' + new Date().toISOString().split('T')[0];
    }

    function submitForm(e) {
      e.preventDefault();
      const name = document.getElementById('name').value;
      const service = document.getElementById('service').value;
      alert('✓ Booking confirmed!\n' + name + ' - ' + service);
      closeModal();
    }

    window.onclick = (e) => {
      const m = document.getElementById('modal');
      if (e.target === m) closeModal();
    };
  </script>
</body>
</html>
