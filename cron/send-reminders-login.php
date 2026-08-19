<?php
/**
 * Sends a reminder SMS for every booking happening ~24 hours from now that
 * hasn't already been reminded. Designed to run every hour via cPanel Cron
 * Jobs:
 *
 *   php /home/YOURCPANELUSER/public_html/cron/send-reminders.php
 *
 * Safe to run repeatedly — reminder_sent_at is set immediately after each
 * attempt so a booking is only ever reminded once.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notify.php';

if (PHP_SAPI !== 'cli' && (!defined('CRON_SECRET') || ($_GET['key'] ?? '') !== CRON_SECRET)) {
    http_response_code(403);
    exit('Forbidden');
}

date_default_timezone_set(defined('SITE_TIMEZONE') ? SITE_TIMEZONE : 'America/Denver');

$pdo = get_db();
$business = get_business_settings();

// Window: appointments between 23 and 25 hours from now that haven't been reminded.
$stmt = $pdo->query("
  SELECT b.*, s.name AS service_name, s.duration_minutes, s.price
  FROM bookings b
  JOIN services s ON s.id = b.service_id
  WHERE b.reminder_sent_at IS NULL
    AND b.status IN ('pending', 'confirmed')
    AND TIMESTAMP(b.appointment_date, b.appointment_time) BETWEEN
        DATE_ADD(NOW(), INTERVAL 23 HOUR) AND DATE_ADD(NOW(), INTERVAL 25 HOUR)
");
$due = $stmt->fetchAll();

$sent = 0;
foreach ($due as $booking) {
    $service = ['name' => $booking['service_name'], 'duration_minutes' => $booking['duration_minutes'], 'price' => $booking['price']];
    $attempted = send_reminder_sms_for_booking($booking, $service, $business);

    // Mark as reminded regardless of delivery success/skip so we never spam retries;
    // failures are still visible in notification_log for follow-up.
    $pdo->prepare('UPDATE bookings SET reminder_sent_at = NOW() WHERE id = :id')->execute(['id' => $booking['id']]);

    if ($attempted) {
        $sent++;
    }
}

echo date('Y-m-d H:i:s') . " — checked " . count($due) . " booking(s) due for reminder, attempted {$sent}.\n";
