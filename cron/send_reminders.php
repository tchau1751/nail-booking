<?php
/**
 * CRON JOB — SMS Appointment Reminders
 * Schedule: run every hour via crontab
 *   0 * * * * php /var/www/html/cron/send_reminders.php >> /var/log/nail_reminders.log 2>&1
 *
 * Walks every salon that is trading, one at a time — each with its own
 * reminder window, its own name on the text and its own Twilio account.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/sms.php';

$salons = fetchAll("SELECT id, name FROM tenants WHERE status IN ('trial','active','past_due') ORDER BY id");

foreach ($salons as $salon) {
    tenantUse((int)$salon['id']);
    $tid = tenantId();

    $s            = settings();
    $bizName      = $s['business_name']      ?? $salon['name'];
    $reminderHrs  = (int)($s['reminder_hours_before'] ?? 24);

    $windowStart  = date('Y-m-d H:i:s', strtotime("+{$reminderHrs} hours"));
    $windowEnd    = date('Y-m-d H:i:s', strtotime("+{$reminderHrs} hours +59 minutes"));

    $appointments = fetchAll(
        "SELECT a.*, s.name AS service_name
         FROM appointments a
         JOIN services s ON s.id = a.service_id
         WHERE a.tenant_id = ?
           AND a.status IN ('pending','confirmed')
           AND a.sms_reminder_sent = 0
           AND CONCAT(a.appointment_date,' ',a.start_time) BETWEEN ? AND ?",
        [$tid, $windowStart, $windowEnd]
    );

    echo date('Y-m-d H:i:s') . " — {$bizName}: checking reminders (window: {$windowStart} to {$windowEnd})\n";
    echo "Found " . count($appointments) . " appointment(s) to remind.\n";

    foreach ($appointments as $appt) {
        $msg    = smsReminder($appt, ['name' => $appt['service_name']], $bizName);
        $result = sendSMS($appt['phone'], $msg, $appt['id'], 'reminder');
        if ($result['success']) {
            query('UPDATE appointments SET sms_reminder_sent=1 WHERE id=? AND tenant_id=?', [$appt['id'], $tid]);
            echo "  ✓ Reminder sent to {$appt['full_name']} ({$appt['phone']})\n";
        } else {
            echo "  ✗ Failed for {$appt['full_name']}: {$result['error']}\n";
        }
    }
}
echo "Done.\n\n";
