<?php
/**
 * Public Booking Page — Multi-tenant SaaS
 *
 * Salon customers share this link with their clients
 * Usage: https://your-domain.com/nail-booking/pos/book/?salon_id=1
 */

$salon_id = (int)($_GET['salon_id'] ?? 0);
if (!$salon_id) {
    http_response_code(400);
    die('Missing salon_id parameter');
}

require_once __DIR__ . '/../../includes/db.php';

$pdo = db();

// Get salon info
$salon = $pdo->query(
    "SELECT name FROM business_settings WHERE id = ? LIMIT 1",
    [$salon_id]
)->fetch();

if (!$salon) {
    http_response_code(404);
    die('Salon not found');
}

// Get services for this salon
$services = $pdo->query(
    "SELECT id, name, duration FROM services WHERE tenant_id = ? AND active = 1 ORDER BY name",
    [$salon_id]
)->fetchAll();

// Get technicians
$technicians = $pdo->query(
    "SELECT id, name FROM technicians WHERE tenant_id = ? AND active = 1 ORDER BY name",
    [$salon_id]
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment — <?= htmlspecialchars($salon['name']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            font-size: 28px;
            margin-bottom: 10px;
            color: #333;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            font-size: 14px;
        }
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .form-row.full {
            grid-template-columns: 1fr;
        }
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        button:active {
            transform: translateY(0);
        }
        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: none;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }
        .loading {
            display: none;
            text-align: center;
            color: #666;
            padding: 20px;
        }
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Book Appointment</h1>
        <p class="subtitle">at <?= htmlspecialchars($salon['name']) ?></p>

        <div id="message" class="message"></div>

        <form id="bookingForm">
            <input type="hidden" name="salon_id" value="<?= $salon_id ?>">

            <!-- Step 1: Your Details -->
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="name" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Phone *</label>
                    <input type="tel" name="phone" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required>
                </div>
            </div>

            <div class="form-group">
                <label>Date of Birth (optional)</label>
                <input type="date" name="dob">
            </div>

            <!-- Step 2: Service & Time -->
            <div class="form-group">
                <label>Service *</label>
                <select name="service" required>
                    <option value="">— Select a service —</option>
                    <?php foreach ($services as $svc): ?>
                        <option value="<?= htmlspecialchars($svc['name']) ?>">
                            <?= htmlspecialchars($svc['name']) ?> (<?= $svc['duration'] ?> min)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="date" required>
                </div>
                <div class="form-group">
                    <label>Time *</label>
                    <input type="time" name="time" required>
                </div>
            </div>

            <?php if ($technicians): ?>
                <div class="form-group">
                    <label>Preferred Technician (optional)</label>
                    <select name="technician_name">
                        <option value="">— Any technician —</option>
                        <?php foreach ($technicians as $tech): ?>
                            <option value="<?= htmlspecialchars($tech['name']) ?>">
                                <?= htmlspecialchars($tech['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label>Notes (optional)</label>
                <textarea name="notes" placeholder="Anything we should know before your visit?"></textarea>
            </div>

            <button type="submit">Confirm Reservation</button>
        </form>

        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>Creating your appointment...</p>
        </div>
    </div>

    <script>
        document.getElementById('bookingForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const form = document.getElementById('bookingForm');
            const loading = document.getElementById('loading');
            const message = document.getElementById('message');

            loading.style.display = 'block';
            message.style.display = 'none';
            form.style.display = 'none';

            const formData = new FormData(form);
            const data = Object.fromEntries(formData);

            // Add API key if needed (for now, use salon_id only)
            data.api_key = 'default'; // TODO: implement proper API key

            try {
                const response = await fetch('<?= dirname($_SERVER['REQUEST_URI']) ?>/../api/booking_webhook.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                loading.style.display = 'none';

                if (result.success) {
                    message.className = 'message success';
                    message.innerHTML = `
                        <strong>✓ Appointment Confirmed!</strong><br>
                        Booking ID: ${result.booking_id}<br>
                        You'll receive an SMS confirmation shortly.
                    `;
                    form.style.display = 'none';
                } else {
                    message.className = 'message error';
                    message.innerHTML = `<strong>Error:</strong> ${result.error}`;
                    form.style.display = 'block';
                }
            } catch (error) {
                loading.style.display = 'none';
                message.className = 'message error';
                message.innerHTML = `<strong>Error:</strong> ${error.message}`;
                form.style.display = 'block';
            }
        });

        // Set min date to today
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('input[name="date"]').min = today;
    </script>
</body>
</html>
