<?php
require_once __DIR__ . '/includes/layout_start.php';
$pdo = get_db();

echo "<pre>";
echo "=== DATABASE CHECK ===\n\n";

// Check clients
$clients_count = $pdo->query('SELECT COUNT(*) as c FROM clients')->fetch();
echo "Total clients: " . $clients_count['c'] . "\n";

// Check stamps
$with_stamps = $pdo->query('SELECT COUNT(*) as c FROM clients WHERE stamp_count > 0')->fetch();
echo "Clients with stamps: " . $with_stamps['c'] . "\n";

// Show sample clients
echo "\nFirst 10 clients:\n";
$sample = $pdo->query('SELECT id, full_name, phone, stamp_count FROM clients LIMIT 10')->fetchAll();
foreach ($sample as $c) {
    echo "  {$c['full_name']}: {$c['stamp_count']}/10 stamps\n";
}

// Check bookings
$bookings_total = $pdo->query('SELECT COUNT(*) as c FROM bookings')->fetch();
echo "\n\nTotal bookings: " . $bookings_total['c'] . "\n";

$completed = $pdo->query('SELECT COUNT(*) as c FROM bookings WHERE status = "completed"')->fetch();
echo "Completed bookings: " . $completed['c'] . "\n";

// Show recent completed bookings
echo "\nRecent completed bookings:\n";
$recent = $pdo->query('
    SELECT b.id, b.full_name, b.appointment_date, b.status, c.full_name as client_name
    FROM bookings b
    LEFT JOIN clients c ON b.client_id = c.id
    ORDER BY b.appointment_date DESC
    LIMIT 10
')->fetchAll();
foreach ($recent as $b) {
    $name = $b['client_name'] ?: $b['full_name'];
    echo "  {$name} - {$b['status']} - " . date('M d Y', strtotime($b['appointment_date'])) . "\n";
}

echo "</pre>";
?>
