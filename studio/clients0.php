<?php
$pageTitle = 'Clients';
$pageSubtitle = 'Everyone who has booked or visited the studio';
$activeNav = 'clients';
require_once __DIR__ . '/includes/layout_start.php';

$pdo = get_db();
$search = trim((string) ($_GET['q'] ?? ''));

$where = '';
$params = [];
if ($search !== '') {
    $where = 'WHERE c.full_name LIKE :q OR c.phone LIKE :q OR c.email LIKE :q';
    $params['q'] = '%' . $search . '%';
}

$stmt = $pdo->prepare("
  SELECT c.*,
         COUNT(b.id) as total_bookings,
         COALESCE(c.stamp_count, 0) as stamps,
         MAX(b.appointment_date) as last_visit
  FROM clients c
  LEFT JOIN bookings b ON c.id = b.client_id
  $where
  GROUP BY c.id
  ORDER BY c.full_name ASC
  LIMIT 200
");
$stmt->execute($params);
$clients = $stmt->fetchAll();
?>

<div class="panel">
  <div class="section-header">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
      <div>
        <h2 class="section-title">Clients</h2>
        <p style="font-size:13px;color:rgba(58,42,36,.6);margin:0;">Everyone who has booked or visited the studio</p>
      </div>
      <a href="<?= BASE_PATH ?>/studio/stamp-cards.php" style="padding:8px 16px;background:#c9a87d;color:white;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600;">🎫 Stamp Cards</a>
    </div>
  </div>

  <div class="panel-body no-pad">
    <div style="padding:16px;border-bottom:1px solid #eee;">
      <input type="text" placeholder="Search name, phone, email..." style="padding:8px 12px;border:1px solid #ddd;border-radius:6px;width:100%;max-width:400px;font-size:13px;">
    </div>

    <table>
      <thead>
        <tr>
          <th>CLIENT</th>
          <th>CONTACT</th>
          <th>BOOKINGS</th>
          <th>STAMPS</th>
          <th>LAST VISIT</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($clients as $c): ?>
        <tr>
          <td><strong><?= e($c['full_name']) ?></strong></td>
          <td style="font-size:12px;color:#666;">
            <?php if ($c['phone']): ?>
              <div><?= e($c['phone']) ?></div>
            <?php endif; ?>
            <?php if ($c['email']): ?>
              <div style="color:#999;"><?= e($c['email']) ?></div>
            <?php endif; ?>
          </td>
          <td style="text-align:center;"><?= (int)$c['total_bookings'] ?></td>
          <td style="text-align:center;">
            <span style="display:inline-block;padding:4px 8px;background:#e3f2fd;color:#0066cc;border-radius:4px;font-size:12px;font-weight:600;">
              <?= (int)$c['stamps'] ?>/10
            </span>
          </td>
          <td style="font-size:12px;color:#666;">
            <?= $c['last_visit'] ? date('M d, Y', strtotime($c['last_visit'])) : '—' ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
