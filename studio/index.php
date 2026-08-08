<?php
$pageTitle = 'Bookings';
$pageSubtitle = 'Every reservation, searchable and filterable';
$activeNav = 'bookings';
require_once __DIR__ . '/includes/layout_start.php';

$pdo = get_db();
$csrf = admin_csrf_token();

$status = $_GET['status'] ?? '';
$search = trim((string) ($_GET['q'] ?? ''));
$allowedStatus = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];

$where = [];
$params = [];
if ($status !== '' && in_array($status, $allowedStatus, true)) {
    $where[] = 'b.status = :status';
    $params['status'] = $status;
}
if ($search !== '') {
    $where[] = '(b.full_name LIKE :q OR b.phone LIKE :q OR b.email LIKE :q)';
    $params['q'] = '%' . $search . '%';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("
  SELECT b.*, s.name AS service_name, s.price, st.full_name AS staff_name, st.color_hex AS staff_color
  FROM bookings b
  JOIN services s ON s.id = b.service_id
  LEFT JOIN staff st ON st.id = b.staff_id
  $whereSql
  ORDER BY b.appointment_date DESC, b.appointment_time DESC
  LIMIT 200
");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

function initials4(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $letters = array_map(fn($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2));
    return mb_strtoupper(implode('', $letters)) ?: '?';
}
?>

<div class="panel">
  <div class="panel-body no-pad">
    <table>
      <thead>
        <tr>
          <th>Client</th>
          <th>Service</th>
          <th>Date/Time</th>
          <th>Technician</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bookings as $b): ?>
        <tr>
          <td><strong><?= e($b['full_name']) ?></strong></td>
          <td><?= e($b['service_name'] ?? '—') ?></td>
          <td><?= date('M d, Y H:i', strtotime($b['appointment_date'] . ' ' . $b['appointment_time'])) ?></td>
          <td><?= e($b['staff_name'] ?? '—') ?></td>
          <td><span style="background:#e3f2fd;color:#0066cc;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600;"><?= e($b['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
