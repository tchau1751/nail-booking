<?php
/**
 * Sends a birthday SMS to every client whose date_of_birth is today, once
 * per year. Meant to run once daily via the same scheduling mechanism as
 * send-reminders.php.
 *
 * Safe to run more than once on the same day — a client is skipped if
 * they've already gotten a birthday message in the last 300 days.
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

$stmt = $pdo->query("
  SELECT c.id, c.full_name, c.phone
  FROM clients c
  WHERE c.phone IS NOT NULL AND c.phone <> ''
    AND c.date_of_birth IS NOT NULL
    AND DATE_FORMAT(c.date_of_birth, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')
    AND NOT EXISTS (
      SELECT 1 FROM client_message_log m
      WHERE m.client_id = c.id
        AND m.message_type = 'birthday'
        AND m.created_at > DATE_SUB(NOW(), INTERVAL 300 DAY)
    )
");
$due = $stmt->fetchAll();

$sent = 0;
foreach ($due as $client) {
    $firstName = trim(explode(' ', $client['full_name'])[0]);
    $message = sprintf(
        "Happy Birthday, %s! From all of us at %s — enjoy 15%% off any service this month as our gift to you. Call %s to book.",
        $firstName,
        $business['business_name'],
        $business['business_phone']
    );
    $status = send_client_sms((int) $client['id'], $client['phone'], $message, 'birthday');
    if ($status === 'sent') {
        $sent++;
    }
}

echo date('Y-m-d H:i:s') . " — checked " . count($due) . " birthday(s) due today, sent {$sent}.\n";
