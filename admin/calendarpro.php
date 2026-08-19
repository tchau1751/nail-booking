<?php
header('Content-Type: text/html; charset=utf-8');
$date = $_GET['date'] ?? date('Y-m-d');
$view = $_GET['view'] ?? 'day';
$dateObj = DateTime::createFromFormat('Y-m-d', $date) ?: new DateTime();

$allStaff = [
  ['id' => '1', 'name' => 'Staff 1', 'color' => '#f8b4d8'],
  ['id' => '2', 'name' => 'Staff 2', 'color' => '#b4d8f8'],
  ['id' => '3', 'name' => 'Staff 3', 'color' => '#d8b4f8'],
  ['id' => '4', 'name' => 'Staff 4', 'color' => '#b4f8d8'],
  ['id' => '5', 'name' => 'Staff 5', 'color' => '#f8d8b4'],
  ['id' => '6', 'name' => 'Staff 6', 'color' => '#d8f8b4'],
];

$selectedStaff = isset($_GET['staff']) ? explode(',', $_GET['staff']) : ['1', '2', '3', '4'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Professional Calendar - Diamond Nails</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; color: #333; }
    .container { max-width: 1800px; margin: 0 auto; padding: 20px; }

    .header { background: linear-gradient(135deg, #c9947f 0%, #a67560 100%); color: white; padding: 25px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    .header h1 { font-size: 28px; font-weight: 700; margin-bottom: 15px; }
    .header-controls { display: flex; gap: 15px; flex-wrap: wrap; align-items: center; }

    .view-tabs { display: flex; gap: 8px; }
    .view-tab { padding: 8px 16px; background: rgba(255,255,255,0.2); border: none; color: white; border-radius: 4px; cursor: pointer; font-weight: 600; transition: all 0.2s; }
    .view-tab.active { background: white; color: #c9947f; }
    .view-tab:hover { background: rgba(255,255,255,0.3); }

    .nav-buttons { display: flex; gap: 8px; }
    .nav-buttons button { padding: 8px 12px; background: white; color: #c9947f; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; }
    .nav-buttons button:hover { opacity: 0.9; }

    .date-display { color: white; font-weight: 600; min-width: 200px; text-align: center; }

    .controls-panel { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .controls-title { font-weight: 700; margin-bottom: 12px; }
    .staff-selector { display: flex; gap: 8px; flex-wrap: wrap; }
    .staff-checkbox { display: flex; align-items: center; gap: 6px; padding: 8px 12px; background: #f5f5f5; border-radius: 4px; cursor: pointer; font-size: 13px; }
    .staff-checkbox input { cursor: pointer; }
    .staff-color { width: 16px; height: 16px; border-radius: 3px; }

    .calendar-wrapper { background: white; border-radius: 8px; overflow: auto; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }

    /* Day View */
    .day-grid { display: grid; grid-template-columns: 70px repeat(auto-fit, minmax(200px, 1fr)); gap: 0; border: 1px solid #ddd; }
    .day-header { background: #f0f0f0; padding: 12px; font-weight: 700; text-align: center; border-right: 1px solid #ddd; border-bottom: 3px solid #c9947f; }
    .day-header:last-child { border-right: none; }
    .day-time { background: #f9f9f9; padding: 10px; font-weight: 600; text-align: center; font-size: 12px; border-right: 1px solid #ddd; border-bottom: 1px solid #eee; }
    .day-slot { border-right: 1px solid #ddd; border-bottom: 1px solid #eee; position: relative; min-height: 100px; background: white; }
    .day-slot:last-child { border-right: none; }
    .day-slot:hover { background: #fafafa; }

    /* Week View */
    .week-grid { display: grid; grid-template-columns: 70px repeat(7, 1fr); gap: 0; border: 1px solid #ddd; }
    .week-header { background: #f0f0f0; padding: 12px; font-weight: 700; text-align: center; border-right: 1px solid #ddd; border-bottom: 3px solid #c9947f; font-size: 13px; }
    .week-header:last-child { border-right: none; }
    .week-slot { border-right: 1px solid #ddd; border-bottom: 1px solid #eee; position: relative; min-height: 60px; background: white; }
    .week-slot:last-child { border-right: none; }

    /* Month View */
    .month-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 0; border: 1px solid #ddd; }
    .month-header { background: #f0f0f0; padding: 12px; font-weight: 700; text-align: center; border-right: 1px solid #ddd; border-bottom: 1px solid #ddd; }
    .month-header:last-child { border-right: none; }
    .month-day { border-right: 1px solid #ddd; border-bottom: 1px solid #ddd; min-height: 120px; padding: 8px; background: white; position: relative; }
    .month-day:last-child { border-right: none; }
    .month-day.other-month { background: #f9f9f9; color: #999; }
    .month-day-num { font-weight: 700; font-size: 14px; margin-bottom: 4px; }
    .month-apt { font-size: 10px; padding: 2px 4px; border-radius: 2px; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .apt { position: absolute; left: 2px; right: 2px; padding: 6px; border-radius: 3px; font-size: 10px; cursor: grab; border: 1px solid rgba(0,0,0,0.1); overflow: hidden; }
    .apt:hover { box-shadow: 0 2px 6px rgba(0,0,0,0.2); }
    .apt.drag { opacity: 0.5; }
    .apt-name { font-weight: 700; }
    .apt-service { font-size: 9px; }
    .apt-time { font-size: 8px; }

    .slot.over { background: #e8f5e9 !important; }

    .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); }
    .modal.show { display: flex; align-items: center; justify-content: center; }
    .modal-content { background: white; padding: 30px; border-radius: 8px; max-width: 450px; width: 90%; box-shadow: 0 8px 24px rgba(0,0,0,0.2); }
    .modal-header { font-size: 20px; font-weight: 700; margin-bottom: 20px; }
    .modal-close { float: right; font-size: 28px; cursor: pointer; color: #999; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; }
    .btn-submit { background: #c9947f; color: white; border: none; padding: 12px; border-radius: 4px; cursor: pointer; font-weight: 600; width: 100%; }
    .btn-submit:hover { opacity: 0.9; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>📅 Professional Calendar</h1>
      <div class="header-controls">
        <div class="view-tabs">
          <button class="view-tab <?= $view === 'day' ? 'active' : '' ?>" onclick="switchView('day')">Day</button>
          <button class="view-tab <?= $view === 'week' ? 'active' : '' ?>" onclick="switchView('week')">Week</button>
          <button class="view-tab <?= $view === 'month' ? 'active' : '' ?>" onclick="switchView('month')">Month</button>
        </div>
        <div class="nav-buttons">
          <button onclick="navigate(-1)">← Prev</button>
          <div class="date-display" id="dateDisplay"><?= $dateObj->format('F j, Y') ?></div>
          <button onclick="navigate(1)">Next →</button>
          <button onclick="goToday()">Today</button>
        </div>
        <button style="background: #059669; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;" onclick="openModal()">+ New Booking</button>
      </div>
    </div>

    <div class="controls-panel">
      <div class="controls-title">👥 Select Staff to Display:</div>
      <div class="staff-selector">
        <?php foreach ($allStaff as $staff): ?>
          <label class="staff-checkbox">
            <input type="checkbox" value="<?= $staff['id'] ?>" <?= in_array($staff['id'], $selectedStaff) ? 'checked' : '' ?> onchange="updateStaff()">
            <div class="staff-color" style="background-color: <?= $staff['color'] ?>"></div>
            <span><?= $staff['name'] ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="calendar-wrapper">
      <!-- Day View -->
      <div id="dayView" style="display: <?= $view === 'day' ? 'block' : 'none' ?>;">
        <div class="day-grid">
          <div class="day-header">Time</div>
          <?php foreach ($selectedStaff as $sid):
            $staff = array_find($allStaff, fn($s) => $s['id'] === $sid);
            if ($staff): ?>
            <div class="day-header" style="border-bottom-color: <?= $staff['color'] ?>"><?= $staff['name'] ?></div>
          <?php endif; endforeach; ?>

          <?php for ($h = 8; $h <= 18; $h++): ?>
            <div class="day-time"><?= sprintf('%d:00', $h) ?></div>
            <?php foreach ($selectedStaff as $sid): ?>
              <div class="day-slot" data-staff="<?= $sid ?>" data-hour="<?= $h ?>" data-date="<?= $date ?>"></div>
            <?php endforeach; ?>
          <?php endfor; ?>
        </div>
      </div>

      <!-- Week View -->
      <div id="weekView" style="display: <?= $view === 'week' ? 'block' : 'none' ?>;">
        <div class="week-grid">
          <div class="week-header">Time</div>
          <?php
          $weekStart = clone $dateObj;
          $weekStart->modify('Monday this week');
          for ($d = 0; $d < 7; $d++):
            $day = clone $weekStart;
            $day->modify("+$d days");
          ?>
            <div class="week-header"><?= $day->format('D M j') ?></div>
          <?php endfor; ?>

          <?php for ($h = 8; $h <= 18; $h++): ?>
            <div class="day-time" style="min-height: 60px; display: flex; align-items: center;"><?= sprintf('%d:00', $h) ?></div>
            <?php
            $weekStart = clone $dateObj;
            $weekStart->modify('Monday this week');
            for ($d = 0; $d < 7; $d++):
              $day = clone $weekStart;
              $day->modify("+$d days");
            ?>
              <div class="week-slot" data-date="<?= $day->format('Y-m-d') ?>" data-hour="<?= $h ?>"></div>
            <?php endfor; ?>
          <?php endfor; ?>
        </div>
      </div>

      <!-- Month View -->
      <div id="monthView" style="display: <?= $view === 'month' ? 'block' : 'none' ?>;">
        <div class="month-grid">
          <div class="month-header">Sun</div>
          <div class="month-header">Mon</div>
          <div class="month-header">Tue</div>
          <div class="month-header">Wed</div>
          <div class="month-header">Thu</div>
          <div class="month-header">Fri</div>
          <div class="month-header">Sat</div>

          <?php
          $first = clone $dateObj;
          $first->modify('first day of this month');
          $last = clone $dateObj;
          $last->modify('last day of this month');
          $start = clone $first;
          $start->modify('Monday this week');

          while ($start <= $last || $start->format('w') !== '0'):
            $isCurrentMonth = $start->format('m') === $dateObj->format('m');
            $dayNum = $start->format('d');
            $dayDate = $start->format('Y-m-d');
          ?>
            <div class="month-day <?= !$isCurrentMonth ? 'other-month' : '' ?>" data-date="<?= $dayDate ?>">
              <div class="month-day-num"><?= $dayNum ?></div>
              <div style="font-size: 10px; color: #999;">3 appts</div>
            </div>
          <?php
            $start->modify('+1 day');
          endwhile;
          ?>
        </div>
      </div>
    </div>
  </div>

  <div id="modal" class="modal">
    <div class="modal-content">
      <span class="modal-close" onclick="closeModal()">&times;</span>
      <div class="modal-header">New Booking</div>
      <form onsubmit="submitForm(event)">
        <div class="form-group">
          <label>Client Name *</label>
          <input type="text" required>
        </div>
        <div class="form-group">
          <label>Service *</label>
          <select required>
            <option>Select service</option>
            <option>Classic Manicure</option>
            <option>Gel Manicure</option>
            <option>Acrylic Full Set</option>
          </select>
        </div>
        <div class="form-group">
          <label>Date *</label>
          <input type="date" value="<?= $date ?>" required>
        </div>
        <button type="submit" class="btn-submit">Book</button>
      </form>
    </div>
  </div>

  <script>
    function switchView(v) {
      document.getElementById('dayView').style.display = v === 'day' ? 'block' : 'none';
      document.getElementById('weekView').style.display = v === 'week' ? 'block' : 'none';
      document.getElementById('monthView').style.display = v === 'month' ? 'block' : 'none';
      document.querySelectorAll('.view-tab').forEach(t => t.classList.remove('active'));
      event.target.classList.add('active');
      location.href = '?date=<?= $date ?>&view=' + v + '&staff=<?= implode(',', $selectedStaff) ?>';
    }

    function updateStaff() {
      const checked = Array.from(document.querySelectorAll('.staff-checkbox input:checked')).map(e => e.value).join(',');
      location.href = '?date=<?= $date ?>&view=<?= $view ?>&staff=' + checked;
    }

    function navigate(dir) {
      const d = new Date('<?= $date ?>');
      d.setDate(d.getDate() + dir);
      const newDate = d.toISOString().split('T')[0];
      location.href = '?date=' + newDate + '&view=<?= $view ?>&staff=<?= implode(',', $selectedStaff) ?>';
    }

    function goToday() {
      location.href = '?date=' + new Date().toISOString().split('T')[0] + '&view=<?= $view ?>&staff=<?= implode(',', $selectedStaff) ?>';
    }

    function openModal() { document.getElementById('modal').classList.add('show'); }
    function closeModal() { document.getElementById('modal').classList.remove('show'); }

    function submitForm(e) {
      e.preventDefault();
      alert('✓ Booking created!');
      closeModal();
    }

    window.onclick = (e) => {
      const m = document.getElementById('modal');
      if (e.target === m) closeModal();
    };

    function array_find(arr, fn) {
      return arr.find(fn);
    }
  </script>
</body>
</html>
