<?php
header('Content-Type: text/html; charset=utf-8');
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Check-In Kiosk - Diamond Nails</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #c9947f 0%, #a67560 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .container { width: 100%; max-width: 1200px; padding: 20px; }

    .header { background: white; padding: 30px; border-radius: 12px; text-align: center; margin-bottom: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
    .header h1 { font-size: 48px; color: #c9947f; margin-bottom: 10px; }
    .header .time { font-size: 32px; color: #666; font-weight: 600; }
    .header .date { font-size: 20px; color: #999; }

    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }

    .section { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); }
    .section h2 { font-size: 24px; color: #333; margin-bottom: 20px; border-bottom: 3px solid #c9947f; padding-bottom: 10px; }

    .appointment { background: linear-gradient(135deg, #f8b4d8 0%, #f5a0d0 100%); padding: 20px; border-radius: 8px; margin-bottom: 15px; color: #333; box-shadow: 0 4px 12px rgba(0,0,0,0.1); transition: transform 0.2s; }
    .appointment:hover { transform: translateY(-2px); }
    .appointment.next { background: linear-gradient(135deg, #b4d8f8 0%, #a0cff5 100%); border-left: 4px solid #059669; }

    .apt-time { font-size: 28px; font-weight: 700; margin-bottom: 5px; }
    .apt-name { font-size: 18px; font-weight: 600; }
    .apt-service { font-size: 14px; opacity: 0.8; margin-top: 5px; }
    .apt-staff { font-size: 12px; opacity: 0.7; margin-top: 3px; }

    .checkin-btn { background: #059669; color: white; border: none; padding: 12px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; width: 100%; margin-top: 10px; transition: all 0.2s; }
    .checkin-btn:hover { background: #047857; transform: scale(1.02); }

    .empty { text-align: center; color: #999; padding: 30px; font-size: 16px; }

    .staff-section { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
    .staff-card { background: linear-gradient(135deg, #f5f5f5 0%, #eee 100%); padding: 15px; border-radius: 8px; text-align: center; border-top: 4px solid #c9947f; }
    .staff-name { font-size: 18px; font-weight: 700; color: #333; margin-bottom: 5px; }
    .staff-status { font-size: 14px; color: #666; }
    .staff-status.online { color: #059669; font-weight: 600; }

    .summary { background: linear-gradient(135deg, #fff5f3 0%, #fff0ed 100%); padding: 20px; border-radius: 8px; border-left: 4px solid #c9947f; }
    .summary h3 { color: #c9947f; margin-bottom: 10px; }
    .summary-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
    .summary-item:last-child { border-bottom: none; }
    .summary-label { color: #666; }
    .summary-value { font-weight: 700; color: #333; }

    @media (max-width: 768px) {
      .header h1 { font-size: 36px; }
      .header .time { font-size: 24px; }
      .appointment { padding: 15px; }
      .apt-time { font-size: 22px; }
      .apt-name { font-size: 16px; }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>✨ Welcome to Diamond Nails</h1>
      <div class="time" id="time">00:00</div>
      <div class="date"><?= date('l, F j, Y') ?></div>
    </div>

    <div class="grid">
      <!-- Next Appointment -->
      <div class="section">
        <h2>📅 Next Appointment</h2>
        <div class="appointment next">
          <div class="apt-time">10:30</div>
          <div class="apt-name">Sarah Johnson</div>
          <div class="apt-service">Gel Manicure</div>
          <div class="apt-staff">👩 Maria</div>
          <button class="checkin-btn">✓ Check In</button>
        </div>
        <div class="appointment">
          <div class="apt-time">11:15</div>
          <div class="apt-name">Emily Davis</div>
          <div class="apt-service">Classic Pedicure</div>
          <div class="apt-staff">👩 Susan</div>
          <button class="checkin-btn">Check In</button>
        </div>
        <div class="appointment">
          <div class="apt-time">12:00</div>
          <div class="apt-name">Jessica Lee</div>
          <div class="apt-service">Acrylic Full Set</div>
          <div class="apt-staff">👩 Laura</div>
          <button class="checkin-btn">Check In</button>
        </div>
      </div>

      <!-- Staff Status -->
      <div class="section">
        <h2>👥 Staff Status</h2>
        <div class="staff-section">
          <div class="staff-card">
            <div class="staff-name">Maria</div>
            <div class="staff-status online">● Online</div>
            <div style="font-size: 12px; color: #999; margin-top: 5px;">2 bookings today</div>
          </div>
          <div class="staff-card">
            <div class="staff-name">Susan</div>
            <div class="staff-status online">● Online</div>
            <div style="font-size: 12px; color: #999; margin-top: 5px;">3 bookings today</div>
          </div>
          <div class="staff-card">
            <div class="staff-name">Laura</div>
            <div class="staff-status online">● Online</div>
            <div style="font-size: 12px; color: #999; margin-top: 5px;">1 booking today</div>
          </div>
          <div class="staff-card">
            <div class="staff-name">Anna</div>
            <div class="staff-status online">● Online</div>
            <div style="font-size: 12px; color: #999; margin-top: 5px;">4 bookings today</div>
          </div>
        </div>
      </div>

      <!-- Today's Summary -->
      <div class="section">
        <h2>📊 Today's Summary</h2>
        <div class="summary">
          <h3>Quick Stats</h3>
          <div class="summary-item">
            <span class="summary-label">Total Bookings</span>
            <span class="summary-value">12</span>
          </div>
          <div class="summary-item">
            <span class="summary-label">Completed</span>
            <span class="summary-value">7</span>
          </div>
          <div class="summary-item">
            <span class="summary-label">Checked In</span>
            <span class="summary-value">3</span>
          </div>
          <div class="summary-item">
            <span class="summary-label">Pending</span>
            <span class="summary-value">2</span>
          </div>
          <div class="summary-item">
            <span class="summary-label">Today's Revenue</span>
            <span class="summary-value">$580</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Update time every second
    function updateTime() {
      const now = new Date();
      const time = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
      document.getElementById('time').textContent = time;
    }

    updateTime();
    setInterval(updateTime, 1000);

    // Check-in button handlers
    document.querySelectorAll('.checkin-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const appointment = this.closest('.appointment');
        const name = appointment.querySelector('.apt-name').textContent;
        alert('✓ ' + name + ' checked in successfully!');
        this.textContent = '✓ Checked In';
        this.disabled = true;
        this.style.opacity = '0.6';
      });
    });
  </script>
</body>
</html>
