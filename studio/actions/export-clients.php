<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!current_admin()) {
    http_response_code(401);
    exit('Not authenticated.');
}

$pdo = get_db();
$clients = $pdo->query("
    SELECT c.full_name, c.phone, c.email, c.date_of_birth, c.notes, c.reward_points,
           (SELECT COUNT(*) FROM bookings b WHERE b.client_id = c.id) AS booking_count,
           (SELECT MAX(appointment_date) FROM bookings b WHERE b.client_id = c.id) AS last_visit
    FROM clients c
    ORDER BY c.full_name ASC
")->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="diamond-nails-clients-' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Full Name', 'Phone', 'Email', 'Date of Birth', 'Notes', 'Reward Points', 'Total Bookings', 'Last Visit']);
foreach ($clients as $c) {
    fputcsv($out, [
        $c['full_name'],
        $c['phone'],
        $c['email'],
        $c['date_of_birth'],
        $c['notes'],
        $c['reward_points'],
        $c['booking_count'],
        $c['last_visit'],
    ]);
}
fclose($out);
