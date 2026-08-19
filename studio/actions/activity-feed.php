<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!current_admin()) {
    json_response(['ok' => false, 'error' => 'Not authenticated.'], 401);
}

$since = trim((string) ($_GET['since'] ?? ''));
$sinceObj = $since !== '' ? DateTime::createFromFormat('Y-m-d H:i:s', $since) : null;
if (!$sinceObj) {
    $sinceObj = (new DateTime())->modify('-24 hours');
}
$sinceStr = $sinceObj->format('Y-m-d H:i:s');

$pdo = get_db();

$stmt = $pdo->prepare("
  SELECT b.id, b.full_name, b.created_at AS event_time, s.name AS service_name, 'new_booking' AS event_type
  FROM bookings b JOIN services s ON s.id = b.service_id
  WHERE b.created_at > :since1
  UNION ALL
  SELECT b.id, b.full_name, b.checked_in_at AS event_time, s.name AS service_name, 'check_in' AS event_type
  FROM bookings b JOIN services s ON s.id = b.service_id
  WHERE b.checked_in_at IS NOT NULL AND b.checked_in_at > :since2
  ORDER BY event_time DESC
  LIMIT 30
");
$stmt->execute(['since1' => $sinceStr, 'since2' => $sinceStr]);
$items = $stmt->fetchAll();

$serverTime = $pdo->query('SELECT NOW() AS now')->fetch()['now'];

json_response([
    'ok' => true,
    'server_time' => $serverTime,
    'items' => array_map(function ($row) {
        return [
            'booking_id' => (int) $row['id'],
            'type' => $row['event_type'],
            'full_name' => $row['full_name'],
            'service_name' => $row['service_name'],
            'event_time' => $row['event_time'],
        ];
    }, $items),
]);
