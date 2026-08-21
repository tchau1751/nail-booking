<?php
$pageTitle = 'Payroll';
$activeNav = 'payroll';
$requireRole = 'manager';   // enforced by layout_start before any output
require_once __DIR__ . '/includes/layout_start.php';
require_once __DIR__ . '/includes/salon.php';

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rates') {
    try {
        foreach ($_POST['commission'] ?? [] as $techId => $rate) {
            query('UPDATE technicians SET commission_rate=?, pay_type=?, hourly_rate=? WHERE id=?', [
                max(0, min(100, (float)$rate)),
                in_array($_POST['pay_type'][$techId] ?? '', ['commission','booth','hourly'], true) ? $_POST['pay_type'][$techId] : 'commission',
                max(0, (float)($_POST['hourly'][$techId] ?? 0)),
                (int)$techId,
            ]);
        }
        $msg = 'Pay rates saved.';
    } catch (Throwable $e) { $err = $e->getMessage(); }
}

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$rng  = [$from, $to];

// Service revenue and tips, per technician, over the range. Tips follow the
// ticket's technician; service revenue follows the line item's technician so a
// two-tech ticket splits correctly.
// Aggregate first, then join — so a technician with no sales in this range
// still appears (with zeros) instead of dropping off the report.
$rows = fetchAll(
    "SELECT t.id, t.name, t.pay_type, t.commission_rate, t.hourly_rate,
            COALESCE(r.service_rev,0) AS service_rev,
            COALESCE(r.product_rev,0) AS product_rev,
            COALESCE(r.tickets,0)     AS tickets
     FROM technicians t
     LEFT JOIN (
         SELECT i.technician_id,
                SUM(CASE WHEN i.item_type='service' THEN i.line_total - i.tax ELSE 0 END) AS service_rev,
                SUM(CASE WHEN i.item_type='product' THEN i.line_total - i.tax ELSE 0 END) AS product_rev,
                COUNT(DISTINCT i.sale_id) AS tickets
         FROM pos_sale_items i
         JOIN pos_sales s ON s.id = i.sale_id
         WHERE s.status='completed' AND DATE(s.created_at) BETWEEN ? AND ?
         GROUP BY i.technician_id
     ) r ON r.technician_id = t.id
     WHERE t.is_active = 1
     ORDER BY t.display_order, t.name", $rng);

$tips = [];
foreach (fetchAll("SELECT technician_id, COALESCE(SUM(tip_total),0) v FROM pos_sales
                   WHERE status='completed' AND DATE(created_at) BETWEEN ? AND ?
                   GROUP BY technician_id", $rng) as $t) {
    $tips[(int)$t['technician_id']] = (float)$t['v'];
}
$hours = [];
foreach (fetchAll('SELECT technician_id,
                     COALESCE(SUM(TIMESTAMPDIFF(MINUTE, clock_in, COALESCE(clock_out, NOW()))),0)/60 h
                   FROM pos_tech_shifts WHERE shift_date BETWEEN ? AND ?
                   GROUP BY technician_id', $rng) as $h) {
    $hours[(int)$h['technician_id']] = (float)$h['h'];
}
$turns = [];
foreach (fetchAll("SELECT assigned_tech_id, COALESCE(SUM(turn_value),0) v FROM pos_checkins
                   WHERE status IN ('in_service','done') AND DATE(checked_in_at) BETWEEN ? AND ?
                   GROUP BY assigned_tech_id", $rng) as $t) {
    $turns[(int)$t['assigned_tech_id']] = (float)$t['v'];
}

$tot = ['service' => 0, 'product' => 0, 'tips' => 0, 'pay' => 0, 'hours' => 0];
foreach ($rows as &$r) {
    $id = (int)$r['id'];
    $r['tips']  = $tips[$id]  ?? 0;
    $r['hours'] = $hours[$id] ?? 0;
    $r['turns'] = $turns[$id] ?? 0;
    $r['commission'] = $r['pay_type'] === 'commission'
        ? round((float)$r['service_rev'] * (float)$r['commission_rate'] / 100, 2) : 0.0;
    $r['wage'] = $r['pay_type'] === 'hourly' ? round($r['hours'] * (float)$r['hourly_rate'], 2) : 0.0;
    $r['payout'] = round($r['commission'] + $r['wage'] + $r['tips'], 2);
    $tot['service'] += $r['service_rev'];
    $tot['product'] += $r['product_rev'];
    $tot['tips']    += $r['tips'];
    $tot['pay']     += $r['payout'];
    $tot['hours']   += $r['hours'];
}
unset($r);
?>
<?php if ($msg): ?><div class="alert alert-ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>

<form class="toolbar no-print" method="get">
  <label class="field"><span>From</span><input type="date" name="from" value="<?= e($from) ?>"></label>
  <label class="field"><span>To</span><input type="date" name="to" value="<?= e($to) ?>"></label>
  <button class="btn" type="submit">Apply</button>
  <a class="btn btn-light" href="?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>">Today</a>
  <a class="btn btn-light" href="?from=<?= date('Y-m-d', strtotime('monday this week')) ?>&to=<?= date('Y-m-d') ?>">This week</a>
  <a class="btn btn-light" href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-t') ?>">This month</a>
  <button class="btn btn-blue" type="button" onclick="window.print()">🖨 Print</button>
</form>

<div class="stats">
  <div class="stat"><div class="v"><?= money($tot['service']) ?></div><div class="k">Service revenue</div></div>
  <div class="stat"><div class="v"><?= money($tot['tips']) ?></div><div class="k">Tips to pay out</div></div>
  <div class="stat"><div class="v"><?= money($tot['pay']) ?></div><div class="k">Total payout</div></div>
  <div class="stat"><div class="v"><?= number_format($tot['hours'], 1) ?></div><div class="k">Hours clocked</div></div>
</div>

<div class="card">
  <h2>Payout — <?= date('m/d/Y', strtotime($from)) ?> to <?= date('m/d/Y', strtotime($to)) ?></h2>
  <p class="sub">Commission is calculated on service revenue net of tax. Tips are passed through in full.</p>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Technician</th><th>Basis</th><th class="num">Tickets</th><th class="num">Turns</th>
        <th class="num">Services</th><th class="num">Retail</th><th class="num">Commission</th>
        <th class="num">Hours</th><th class="num">Wage</th><th class="num">Tips</th><th class="num">Payout</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><strong><?= e($r['name']) ?></strong></td>
          <td><?= $r['pay_type'] === 'commission' ? (float)$r['commission_rate'] . '%' : e($r['pay_type']) ?></td>
          <td class="num"><?= (int)$r['tickets'] ?></td>
          <td class="num"><?= rtrim(rtrim(number_format($r['turns'], 2), '0'), '.') ?></td>
          <td class="num"><?= money($r['service_rev']) ?></td>
          <td class="num"><?= money($r['product_rev']) ?></td>
          <td class="num"><?= money($r['commission']) ?></td>
          <td class="num"><?= number_format($r['hours'], 1) ?></td>
          <td class="num"><?= money($r['wage']) ?></td>
          <td class="num"><?= money($r['tips']) ?></td>
          <td class="num"><strong><?= money($r['payout']) ?></strong></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:#faf7f5;font-weight:800">
          <td colspan="4">Total</td>
          <td class="num"><?= money($tot['service']) ?></td>
          <td class="num"><?= money($tot['product']) ?></td>
          <td class="num"></td>
          <td class="num"><?= number_format($tot['hours'], 1) ?></td>
          <td class="num"></td>
          <td class="num"><?= money($tot['tips']) ?></td>
          <td class="num"><?= money($tot['pay']) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<div class="card no-print">
  <h2>Pay rates</h2>
  <p class="sub">Commission techs earn a share of their own service revenue. Booth renters keep tips only here —
     record their rent as an expense. Hourly uses clocked hours from the queue board.</p>
  <form method="post">
    <input type="hidden" name="action" value="rates">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Technician</th><th>Pay type</th><th>Commission %</th><th>Hourly rate</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><strong><?= e($r['name']) ?></strong></td>
            <td>
              <select name="pay_type[<?= (int)$r['id'] ?>]">
                <?php foreach (['commission' => 'Commission', 'booth' => 'Booth rent', 'hourly' => 'Hourly'] as $k => $label): ?>
                  <option value="<?= $k ?>" <?= $r['pay_type'] === $k ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input type="number" step="0.5" name="commission[<?= (int)$r['id'] ?>]" value="<?= (float)$r['commission_rate'] ?>" style="width:110px"></td>
            <td><input type="number" step="0.01" name="hourly[<?= (int)$r['id'] ?>]" value="<?= (float)$r['hourly_rate'] ?>" style="width:110px"></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <button class="btn btn-green" type="submit" style="margin-top:14px">Save rates</button>
  </form>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
