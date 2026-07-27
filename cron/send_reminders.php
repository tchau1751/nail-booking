<?php
/**
 * CRON JOB — SMS Appointment Reminders
 * Schedule: run every hour via crontab
 *   0 * * * * php /var/www/html/cron/send_reminders.php >> /var/log/nail_reminders.log 2>&1
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/sms.php';

$s            = settings();
$bizName      = $s['business_name']      ?? 'Diamond Nail & Spa';
$reminderHrs  = (int)($s['reminder_hours_before'] ?? 24);

$windowStart  = date('Y-m-d H:i:s', strtotime("+{$reminderHrs} hours"));
$windowEnd    = date('Y-m-d H:i:s', strtotime("+{$reminderHrs} hours +59 minutes"));

$appointments = fetchAll(
    "SELECT a.*, s.name AS service_name
     FROM appointments a
     JOIN services s ON s.id = a.service_id
     WHERE a.status IN ('pending','confirmed')
       AND a.sms_reminder_sent = 0
       AND CONCAT(a.appointment_date,' ',a.start_time) BETWEEN ? AND ?",
    [$windowStart, $windowEnd]
);

echo date('Y-m-d H:i:s') . " — Checking reminders (window: {$windowStart} to {$windowEnd})\n";
echo "Found " . count($appointments) . " appointment(s) to remind.\n";

foreach ($appointments as $appt) {
    $msg    = smsReminder($appt, ['name' => $appt['service_name']], $bizName);
    $result = sendSMS($appt['phone'], $msg, $appt['id'], 'reminder');
    if ($result['success']) {
        query('UPDATE appointments SET sms_reminder_sent=1 WHERE id=?', [$appt['id']]);
        echo "  ✓ Reminder sent to {$appt['full_name']} ({$appt['phone']})\n";
    } else {
        echo "  ✗ Failed for {$appt['full_name']}: {$result['error']}\n";
    }
}
echo "Done.\n\n";
