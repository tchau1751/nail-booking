<?php
$pageTitle = 'Sales';
$activeNav = 'sales';
$requireRole = 'manager';   // enforced by layout_start before any output
require_once __DIR__ . '/includes/layout_start.php';

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'void') {
    try { voidSale((int)$_POST['id']); $msg = 'Sale voided and stock returned.'; }
    catch (Throwable $e) { $err = $e->getMessage(); }
}

$from = $_GET['from'] ?? date('Y-m-d');
$to   = $_GET['to']   ?? date('Y-m-d');
$q    = trim($_GET['q'] ?? '');

$where = ['DATE(s.created_at) BETWEEN ? AND ?'];
$args  = [$from, $to];
if ($q !== '') {
    $where[] = '(s.sale_no LIKE ? OR s.customer_name LIKE ? OR s.customer_phone LIKE ?)';
    array_push($args, "%$q%", "%$q%", "%$q%");
}
$sql = 'SELECT s.*,
               COALESCE((SELECT GROUP_CONCAT(DISTINCT t2.name ORDER BY t2.name SEPARATOR ", ")
                 FROM pos_sale_items i2 JOIN technicians t2 ON t2.id = i2.technician_id
                 WHERE i2.sale_id = s.id), t.name) AS tech_name,
               (SELECT GROUP_CONCAT(CONCAT(p.method) SEPARATOR ", ") FROM pos_payments p WHERE p.sale_id=s.id) AS methods
        FROM pos_sales s LEFT JOIN technicians t ON t.id=s.technician_id
        WHERE ' . implode(' AND ', $where) . ' ORDER BY s.id DESC LIMIT 300';
$sales = fetchAll($sql, $args);

$sum = ['count' => 0, 'total' => 0, 'tips' => 0];
foreach ($sales as $s) {
    if ($s['status'] !== 'completed') continue;
    $sum['count']++; $sum['total'] += $s['grand_total']; $sum['tips'] += $s['tip_total'];
}
?>
<?php if ($msg): ?><div class="alert alert-ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>

<form class="toolbar" method="get">
  <label class="field"><span>From</span><input type="date" name="from" value="<?= e($from) ?>"></label>
  <label class="field"><span>To</span><input type="date" name="to" value="<?= e($to) ?>"></label>
  <label class="field" style="flex:1;min-width:200px"><span>Search</span>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Sale no, name or phone"></label>
  <button class="btn" type="submit">Apply</button>
  <a class="btn btn-light" href="<?= BASE_PATH ?>/pos/sales.php">Today</a>
</form>

<div class="stats">
  <div class="stat"><div class="v"><?= $sum['count'] ?></div><div class="k">Completed sales</div></div>
  <div class="stat"><div class="v"><?= money($sum['total']) ?></div><div class="k">Collected</div></div>
  <div class="stat"><div class="v"><?= money($sum['tips']) ?></div><div class="k">Tips</div></div>
</div>

<div class="table-wrap">
  <table>
    <thead><tr>
      <th>Sale</th><th>Time</th><th>Guest</th><th>Tech</th><th>Payment</th>
      <th class="num">Tip</th><th class="num">Total</th><th>Status</th><th></th>
    </tr></thead>
    <tbody>
    <?php if (!$sales): ?>
      <tr><td colspan="9" style="text-align:center;color:var(--ink-soft);padding:40px">No sales in this range.</td></tr>
    <?php endif; ?>
    <?php foreach ($sales as $s): ?>
      <tr>
        <td><strong><?= e($s['sale_no']) ?></strong></td>
        <td><?= date('m/d g:i A', strtotime($s['created_at'])) ?></td>
        <td><?= e($s['customer_name'] ?: 'Walk-in') ?><?php if ($s['customer_phone']): ?><div style="font-size:12px;color:var(--ink-soft)"><?= e($s['customer_phone']) ?></div><?php endif; ?></td>
        <td><?= e($s['tech_name'] ?: '—') ?></td>
        <td><?= e($s['methods'] ?: '—') ?></td>
        <td class="num"><?= money($s['tip_total']) ?></td>
        <td class="num"><strong><?= money($s['grand_total']) ?></strong></td>
        <td><span class="pill <?= $s['status'] === 'completed' ? 'pill-ok' : 'pill-void' ?>"><?= e($s['status']) ?></span></td>
        <td style="white-space:nowrap">
          <a class="btn btn-light btn-sm" href="<?= BASE_PATH ?>/pos/receipt.php?id=<?= (int)$s['id'] ?>" target="_blank" rel="noopener">Receipt</a>
          <?php if ($s['status'] === 'completed'): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Void sale <?= e($s['sale_no']) ?>? Stock will be returned.')">
              <input type="hidden" name="action" value="void">
              <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <button class="btn btn-red btn-sm" type="submit">Void</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
