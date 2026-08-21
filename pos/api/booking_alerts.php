<?php
// ============================================================
//  Tiny polling endpoint so the Queue screen can chime and show
//  a banner the moment a client books online. The online booking
//  system (appointments table) and the walk-in queue
//  (pos_checkins) are otherwise two separate things, so staff
//  watching the Queue screen would have no other way to notice a
//  new online booking in real time.
// ============================================================
require_once __DIR__ . '/../includes/salon.php';
if (!isLoggedIn()) jsonOut(['error' => 'Not signed in.'], 401);

$since = (int)($_GET['since'] ?? 0);

$rows = fetchAll(
    "SELECT a.id, a.full_name, a.appointment_date, a.start_time, s.name AS service_name
       FROM appointments a
       LEFT JOIN services s ON s.id = a.service_id
      WHERE a.id > ? AND a.status = 'pending'
      ORDER BY a.id ASC
      LIMIT 20",
    [$since]
);

$latest = (int)(fetchOne('SELECT MAX(id) m FROM appointments')['m'] ?? $since);

jsonOut([
    'ok'        => true,
    'bookings'  => array_map(function ($r) {
        return [
            'id'      => (int)$r['id'],
            'name'    => $r['full_name'],
            'service' => $r['service_name'] ?: 'a service',
            'date'    => date('D, M j', strtotime($r['appointment_date'])),
            'time'    => date('g:i A', strtotime($r['start_time'])),
        ];
    }, $rows),
    'latest_id' => max($since, $latest),
]);
