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
  <title>Book Your Appointment - Diamond Nails</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%); min-height: 100vh; padding: 20px; }
    .container { max-width: 1200px; margin: 0 auto; }

    .header { background: linear-gradient(135deg, #c9947f 0%, #a67560 100%); color: white; padding: 40px; border-radius: 12px; text-align: center; margin-bottom: 30px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); }
    .header h1 { font-size: 36px; font-weight: 700; margin-bottom: 10px; }
    .header p { font-size: 16px; opacity: 0.95; }

    .content { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }

    .calendar-section { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .section-title { font-size: 22px; font-weight: 700; color: #333; margin-bottom: 20px; }

    .date-nav { display: flex; gap: 12px; align-items: center; justify-content: center; margin-bottom: 25px; }
    .date-nav button { padding: 10px 16px; background: #c9947f; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; }
    .date-nav button:hover { opacity: 0.9; }
    .date-display { font-weight: 600; min-width: 180px; text-align: center; font-size: 16px; }

    .staff-tabs { display: flex; gap: 8px; margin-bottom: 25px; flex-wrap: wrap; }
    .staff-tab { padding: 10px 16px; background: #f5f5f5; border: 2px solid #ddd; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.2s; }
    .staff-tab:hover { background: #efefef; }
    .staff-tab.active { background: #c9947f; color: white; border-color: #c9947f; }

    .calendar-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 12px; }
    .time-slot { padding: 16px; background: #f9f9f9; border: 2px solid #ddd; border-radius: 8px; text-align: center; cursor: pointer; transition: all 0.2s; }
    .time-slot:hover { background: #f0f0f0; border-color: #c9947f; }
    .time-slot.available { background: #e8f5e9; border-color: #4caf50; }
    .time-slot.available:hover { background: #d4edda; transform: scale(1.05); }
    .time-slot.selected { background: #c9947f; color: white; border-color: #a67560; }
    .time-slot.booked { background: #ffcdd2; color: #c62828; border-color: #ef5350; cursor: not-allowed; opacity: 0.7; }

    .slot-time { font-size: 18px; font-weight: 700; }
    .slot-status { font-size: 11px; margin-top: 4px; opacity: 0.8; }

    .form-section { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #333; font-size: 14px; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; font-family: inherit; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #c9947f; box-shadow: 0 0 0 3px rgba(201, 148, 127, 0.1); }

    .summary { background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #c9947f; }
    .summary-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
    .summary-row:last-child { margin-bottom: 0; }
    .summary-label { color: #666; }
    .summary-value { font-weight: 700; color: #333; }

    .btn-submit { background: #059669; color: white; border: none; padding: 14px; border-radius: 6px; cursor: pointer; font-weight: 700; font-size: 16px; width: 100%; transition: all 0.2s; }
    .btn-submit:hover { opacity: 0.9; transform: translateY(-2px); }
    .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

    .notice { background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; }

    @media (max-width: 900px) {
      .content { grid-template-columns: 1fr; }
      .header h1 { font-size: 28px; }
      .calendar-grid { grid-template-columns: repeat(4, 1fr); }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>✨ Book Your Appointment</h1>
      <p>Professional nail care at Diamond Nails & Spa</p>
    </div>

    <div class="content">
      <!-- Calendar Section -->
      <div class="calendar-section">
        <div class="section-title">📅 Select Date & Time</div>

        <div class="notice">
          💡 Click on an available time to select it. Times with ✓ are available.
        </div>

        <div class="date-nav">
          <button onclick="navigate(-1)">← Prev</button>
          <div class="date-display" id="dateDisplay"><?= $dateObj->format('l, F j, Y') ?></div>
          <button onclick="navigate(1)">Next →</button>
          <button onclick="goToday()" style="background: #4caf50;">Today</button>
        </div>

        <div class="staff-tabs" id="staffTabs">
          <div class="staff-tab active" onclick="selectStaff('any', this)">👥 Any Available</div>
          <div class="staff-tab" onclick="selectStaff('1', this)">👩 Maria</div>
          <div class="staff-tab" onclick="selectStaff('2', this)">👩 Susan</div>
          <div class="staff-tab" onclick="selectStaff('3', this)">👩 Laura</div>
          <div class="staff-tab" onclick="selectStaff('4', this)">👩 Anna</div>
        </div>

        <div class="calendar-grid" id="calendarGrid">
          <!-- Time slots will be generated here -->
        </div>
      </div>

      <!-- Booking Form Section -->
      <div class="form-section">
        <div class="section-title">👤 Your Details</div>

        <div class="summary" id="summary" style="display: none;">
          <div class="summary-row">
            <span class="summary-label">Selected Time:</span>
            <span class="summary-value" id="summaryTime">Not selected</span>
          </div>
          <div class="summary-row">
            <span class="summary-label">Staff:</span>
            <span class="summary-value" id="summaryStaff">Not selected</span>
          </div>
        </div>

        <form onsubmit="submitBooking(event)">
          <div class="form-group">
            <label>Full Name *</label>
            <input type="text" id="name" required placeholder="Jane Smith">
          </div>

          <div class="form-group">
            <label>Email *</label>
            <input type="email" id="email" required placeholder="jane@example.com">
          </div>

          <div class="form-group">
            <label>Phone *</label>
            <input type="tel" id="phone" required placeholder="(555) 123-4567">
          </div>

          <div class="form-group">
            <label>Service *</label>
            <select id="service" required>
              <option value="">-- Select a service --</option>
              <option value="Classic Manicure">Classic Manicure - $25</option>
              <option value="Gel Manicure">Gel Manicure - $40</option>
              <option value="Classic Pedicure">Classic Pedicure - $30</option>
              <option value="Gel Pedicure">Gel Pedicure - $45</option>
              <option value="Acrylic Full Set">Acrylic Full Set - $60</option>
              <option value="Nail Art Design">Nail Art Design - $50</option>
            </select>
          </div>

          <div class="form-group">
            <label>Special Requests</label>
            <textarea id="notes" rows="3" placeholder="Any special requests or preferences..."></textarea>
          </div>

          <button type="submit" class="btn-submit" id="submitBtn" disabled>✓ Confirm Booking</button>
        </form>

        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; text-align: center;">
          <p>Your booking is secure. You'll receive a confirmation email with appointment details.</p>
        </div>
      </div>
    </div>
  </div>

  <script>
    let selectedTime = null;
    let selectedStaff = 'any';

    // Sample booked times (would come from database)
    const bookedTimes = ['9:15', '9:30', '10:00', '11:00', '13:30', '14:00', '14:15', '15:30'];

    function generateTimeSlots() {
      const grid = document.getElementById('calendarGrid');
      grid.innerHTML = '';

      for (let h = 8; h <= 18; h++) {
        for (let m = 0; m < 60; m += 15) {
          const time = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
          const isBooked = bookedTimes.includes(time);

          const slot = document.createElement('div');
          slot.className = 'time-slot' + (isBooked ? ' booked' : ' available');
          slot.innerHTML = `
            <div class="slot-time">${time}</div>
            <div class="slot-status">${isBooked ? '❌ Booked' : '✓ Available'}</div>
          `;

          if (!isBooked) {
            slot.onclick = () => selectTime(time);
          }

          grid.appendChild(slot);
        }
      }
    }

    function selectTime(time) {
      // Unselect previous
      document.querySelectorAll('.time-slot.selected').forEach(s => {
        if (!bookedTimes.includes(s.textContent.trim().split('\n')[0])) {
          s.classList.remove('selected');
          s.classList.add('available');
        }
      });

      // Select new
      event.target.closest('.time-slot').classList.remove('available');
      event.target.closest('.time-slot').classList.add('selected');

      selectedTime = time;
      updateSummary();
      document.getElementById('submitBtn').disabled = false;
    }

    function selectStaff(staff, btn) {
      document.querySelectorAll('.staff-tab').forEach(t => t.classList.remove('active'));
      btn.classList.add('active');
      selectedStaff = staff;
      updateSummary();
    }

    function updateSummary() {
      if (selectedTime) {
        document.getElementById('summary').style.display = 'block';
        document.getElementById('summaryTime').textContent = selectedTime;
        document.getElementById('summaryStaff').textContent =
          selectedStaff === 'any' ? 'Any Available' :
          ['Maria', 'Susan', 'Laura', 'Anna'][parseInt(selectedStaff) - 1];
      }
    }

    function navigate(dir) {
      const d = new Date('<?= $date ?>');
      d.setDate(d.getDate() + dir);
      const newDate = d.toISOString().split('T')[0];
      location.href = '?date=' + newDate;
    }

    function goToday() {
      location.href = '?date=' + new Date().toISOString().split('T')[0];
    }

    function submitBooking(e) {
      e.preventDefault();

      if (!selectedTime) {
        alert('Please select a time');
        return;
      }

      const data = {
        full_name: document.getElementById('name').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        service_name: document.getElementById('service').value,
        staff_id: selectedStaff === 'any' ? null : selectedStaff,
        appointment_date: '<?= $date ?>',
        appointment_time: selectedTime,
        notes: document.getElementById('notes').value
      };

      alert('✅ Booking confirmed!\n\n' + data.full_name + '\n' + data.service_name + '\n' + selectedTime + '\n\nYou will receive a confirmation email.');
      console.log('Booking data:', data);
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', generateTimeSlots);
  </script>
</body>
</html>
