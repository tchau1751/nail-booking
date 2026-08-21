<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/auth.php';
if (!function_exists('get_db')) require_once __DIR__ . '/../includes/functions.php';
$pdo = get_db();
echo "<pre style='font:14px monospace'>";
foreach (['clients', 'bookings', 'appointments'] as $t) {
    echo "\n===== $t =====\n";
    try {
        foreach ($pdo->query("SHOW COLUMNS FROM `$t`") as $r) echo $r['Field'] . "\n";
        echo "-- rows: " . $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn() . "\n";
    } catch (Throwable $e) {
        echo "NOT USABLE: " . $e->getMessage() . "\n";
    }
}
echo "</pre>";
