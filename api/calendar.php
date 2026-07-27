<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

$from = $_GET['start'] ?? date('Y-m-01');
$to   = $_GET['end']   ?? date('Y-m-t');

$rows = fetchAll(
    "SELECT a.id,a.full_name,a.appointment_date,a.start_time,a.end_time,a.status,
            s.name AS service_name, t.name AS technician_name
     FROM appointments a
     JOIN services s ON s.id=a.service_id
     LEFT JOIN technicians t ON t.id=a.technician_id
     WHERE a.appointment_date BETWEEN ? AND ?
     ORDER BY a.appointment_date,a.start_time",
    [$from, $to]
);

$statusColors = [
    'pending'   => '#C9947F',
    'confirmed' => '#6E4856',
    'cancelled' => '#9CA3AF',
    'completed' => '#059669',
];

$events = array_map(function($r) use ($statusColors) {
    $color  = $statusColors[$r['status']] ?? '#C9947F';
    $title  = "{$r['full_name']} — {$r['service_name']}";
    if ($r['technician_name']) $title .= " ({$r['technician_name']})";
    return [
        'id'              => $r['id'],
        'title'           => $title,
        'start'           => $r['appointment_date'] . 'T' . $r['start_time'],
        'end'             => $r['appointment_date'] . 'T' . $r['end_time'],
        'backgroundColor' => $color,
        'borderColor'     => $color,
        'extendedProps'   => ['status' => $r['status']],
    ];
}, $rows);

// Also add blocked dates as background events
$blocked = fetchAll('SELECT blocked_date,reason FROM blocked_dates WHERE blocked_date BETWEEN ? AND ?', [$from,$to]);
foreach ($blocked as $b) {
    $events[] = [
        'title'    => '🚫 ' . ($b['reason'] ?: 'Closed'),
        'start'    => $b['blocked_date'],
        'allDay'   => true,
        'display'  => 'background',
        'color'    => '#FCA5A5',
    ];
}

echo json_encode($events);
