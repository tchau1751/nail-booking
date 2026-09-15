<?php
// ============================================================
//  Birthday texts — one per guest, once a year, salon by salon.
//
//  Run once a day from Windows Task Scheduler:
//    D:\xampp\php\php.exe D:\xampp\htdocs\nail-booking\cron\send_birthday_sms.php
//  See who would get one, sending nothing:
//    D:\xampp\php\php.exe D:\xampp\htdocs\nail-booking\cron\send_birthday_sms.php --dry-run
//
//  Every salon that is trading has its own switch, wording and guests
//  (POS -> Settings, where a manager can also preview today's list).
//  Safe to run repeatedly: birthday_sms_year records who has already
//  been messaged this year, so a double-run sends nothing twice.
// ============================================================
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }   // guests' numbers; never from a browser
require_once __DIR__ . '/../pos/includes/rewards.php';
require_once __DIR__ . '/../includes/sms.php';

$dry = in_array('--dry-run', $argv, true);

$salons = fetchAll("SELECT id, name FROM tenants WHERE status IN ('trial','active','past_due') ORDER BY id");

foreach ($salons as $salon) {
    tenantUse((int)$salon['id']);
    $name = settings()['business_name'] ?? $salon['name'];

    if (!birthdayTextsOn()) {
        echo "{$name}: birthday texts are switched off (POS -> Settings).\n";
        continue;
    }

    $due  = birthdayTextsDue();
    $sent = 0; $failed = 0;

    foreach ($due as $c) {
        if ($dry) {
            echo "  [dry run] {$c['phone']}: {$c['body']}\n";
            continue;
        }
        $r = sendSMS($c['phone'], $c['body'], null, 'custom');
        if (!empty($r['success'])) {
            // Stamp the year only on success, so a failure retries tomorrow
            // rather than silently skipping this guest for a whole year.
            birthdayTextSent((int)$c['id']);
            $sent++;
        } else {
            $failed++;
            echo "  failed for {$c['full_name']}: " . ($r['error'] ?? 'unknown error') . "\n";
        }
    }

    echo date('Y-m-d H:i') . " {$name}: birthdays today " . count($due)
       . ($dry ? " (dry run, nothing sent)" : ", sent $sent, failed $failed") . "\n";
}
