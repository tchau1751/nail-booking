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
            query('UPDATE technicians SET commission_rate=?, pay_type=?, hourly_rate=?, supply_fee_rate=? WHERE id=?', [
                max(0, min(100, (float)$rate)),
                in_array($_POST['pay_type'][$techId] ?? '', ['commission','booth','hourly'], true) ? $_POST['pay_type'][$techId] : 'commission',
                max(0, (float)($_POST['hourly'][$techId] ?? 0)),
                max(0, min(100, (float)($_POST['supply'][$techId] ?? 0))),
                (int)$techId,
            ]);
        }
        $msg = 'Pay rates saved.';
    } catch (Throwable $e) { $err = $e->getMessage(); }
}

$supplyOn = (int)(posSettings()['supply_fee_enabled'] ?? 0) === 1;

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$rng  = [$from, $to];

// Revenue AND tips both follow the line item's technician, so a ticket worked
// by two people splits down the middle instead of landing on whoever happened
// to be named on the ticket header. Reports reads the same way — the two pages
// have to agree or nobody trusts either.
// Anything refunded comes back off the line it was refunded from: no commission
// on work the guest did not end up paying for. Fully refunded tickets are still
// counted here, because their surviving lines (if any) still earned.
// Aggregate first, then join — so a technician with no sales in this range
// still appears (with zeros) instead of dropping off the report.
$rows = fetchAll(
    "SELECT t.id, t.name, t.pay_type, t.commission_rate, t.hourly_rate, t.supply_fee_rate,
            COALESCE(r.service_rev,0) AS service_rev,
            COALESCE(r.product_rev,0) AS product_rev,
            COALESCE(r.tips_card,0)   AS tips_card,
            COALESCE(r.tips_cash,0)   AS tips_cash,
            COALESCE(r.tickets,0)     AS tickets
     FROM technicians t
     LEFT JOIN (
         SELECT i.technician_id,
                SUM(CASE WHEN i.item_type IN ('service','custom')
                         THEN i.line_total - i.tax - COALESCE(rf.amount,0) ELSE 0 END) AS service_rev,
                SUM(CASE WHEN i.item_type='product'
                         THEN i.line_total - i.tax - COALESCE(rf.amount,0) ELSE 0 END) AS product_rev,
                SUM(CASE WHEN s.tip_method='card' THEN i.tip - COALESCE(rf.tip,0) ELSE 0 END) AS tips_card,
                SUM(CASE WHEN s.tip_method='cash' THEN i.tip - COALESCE(rf.tip,0) ELSE 0 END) AS tips_cash,
                COUNT(DISTINCT i.sale_id) AS tickets
         FROM pos_sale_items i
         JOIN pos_sales s ON s.id = i.sale_id
         LEFT JOIN (SELECT sale_item_id, SUM(amount) amount, SUM(tip) tip
                      FROM pos_refund_items GROUP BY sale_item_id) rf
                ON rf.sale_item_id = i.id
         WHERE s.status IN ('completed','refunded') AND DATE(s.created_at) BETWEEN ? AND ?
         GROUP BY i.technician_id
     ) r ON r.technician_id = t.id
     WHERE t.is_active = 1
     ORDER BY t.display_order, t.name", $rng);

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

$tot = ['service' => 0, 'product' => 0, 'tips_card' => 0, 'tips_cash' => 0,
        'supply' => 0, 'pay' => 0, 'hours' => 0];
foreach ($rows as &$r) {
    $id = (int)$r['id'];
    $r['hours'] = $hours[$id] ?? 0;
    $r['turns'] = $turns[$id] ?? 0;
    $r['commission'] = $r['pay_type'] === 'commission'
        ? round((float)$r['service_rev'] * (float)$r['commission_rate'] / 100, 2) : 0.0;
    $r['wage'] = $r['pay_type'] === 'hourly' ? round($r['hours'] * (float)$r['hourly_rate'], 2) : 0.0;

    // Supply fee is a percentage of what the chair took in, not of the
    // technician's share — and it comes out of that share. A booth renter buys
    // their own supplies, so they never pay it.
    $r['supply'] = ($supplyOn && $r['pay_type'] !== 'booth')
        ? round((float)$r['service_rev'] * (float)$r['supply_fee_rate'] / 100, 2) : 0.0;

    // Card tips the shop is holding and owes. Cash tips went hand to hand at
    // the chair hours ago — paying them again would pay them twice.
    $r['payout'] = round($r['commission'] + $r['wage'] - $r['supply'] + (float)$r['tips_card'], 2);

    $tot['service']   += $r['service_rev'];
    $tot['product']   += $r['product_rev'];
    $tot['tips_card'] += $r['tips_card'];
    $tot['tips_cash'] += $r['tips_cash'];
    $tot['supply']    += $r['supply'];
    $tot['pay']       += $r['payout'];
    $tot['hours']     += $r['hours'];
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
  <div class="stat"><div class="v"><?= money($tot['tips_card']) ?></div><div class="k">Card tips to pay out</div></div>
  <?php if ($supplyOn): ?>
    <div class="stat"><div class="v"><?= money($tot['supply']) ?></div><div class="k">Supply fee kept</div></div>
  <?php endif; ?>
  <div class="stat"><div class="v"><?= money($tot['pay']) ?></div><div class="k">Total payout</div></div>
  <div class="stat"><div class="v"><?= number_format($tot['hours'], 1) ?></div><div class="k">Hours clocked</div></div>
</div>

<div class="card">
  <h2>Payout — <?= date('m/d/Y', strtotime($from)) ?> to <?= date('m/d/Y', strtotime($to)) ?></h2>
  <p class="sub">
    Commission is calculated on service revenue net of tax.
    <?php if ($supplyOn): ?>Supply fee is a percentage of that same revenue and comes out of the technician's share.<?php endif; ?>
    Card tips are owed and included in the payout; cash tips were handed over at the chair and are shown for the record only.
  </p>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Technician</th><th>Basis</th><th class="num">Tickets</th><th class="num">Turns</th>
        <th class="num">Services</th><th class="num">Retail</th><th class="num">Commission</th>
        <?php if ($supplyOn): ?><th class="num">Supply fee</th><?php endif; ?>
        <th class="num">Hours</th><th class="num">Wage</th>
        <th class="num">Card tips</th><th class="num">Cash tips</th><th class="num">Payout</th>
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
          <?php if ($supplyOn): ?>
            <td class="num"><?= $r['supply'] > 0 ? '−' . money($r['supply']) : money(0) ?>
              <?php if ((float)$r['supply_fee_rate'] > 0 && $r['pay_type'] !== 'booth'): ?>
                <small style="opacity:.6">(<?= (float)$r['supply_fee_rate'] ?>%)</small>
              <?php endif; ?>
            </td>
          <?php endif; ?>
          <td class="num"><?= number_format($r['hours'], 1) ?></td>
          <td class="num"><?= money($r['wage']) ?></td>
          <td class="num"><?= money($r['tips_card']) ?></td>
          <td class="num" style="opacity:.6"><?= money($r['tips_cash']) ?></td>
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
          <?php if ($supplyOn): ?><td class="num">−<?= money($tot['supply']) ?></td><?php endif; ?>
          <td class="num"><?= number_format($tot['hours'], 1) ?></td>
          <td class="num"></td>
          <td class="num"><?= money($tot['tips_card']) ?></td>
          <td class="num" style="opacity:.6"><?= money($tot['tips_cash']) ?></td>
          <td class="num"><?= money($tot['pay']) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<div class="card no-print">
  <h2>Pay rates</h2>
  <p class="sub">Commission techs earn a share of their own service revenue. Booth renters keep tips only here —
     record their rent as an expense. Hourly uses clocked hours from the queue board.
     <?php if ($supplyOn): ?>The supply fee is negotiated per person, so each has their own rate.
     <?php else: ?>Supply fee is switched off in Settings, so those rates are ignored for now.<?php endif; ?></p>
  <form method="post">
    <input type="hidden" name="action" value="rates">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Technician</th><th>Pay type</th><th>Commission %</th><th>Supply fee %</th><th>Hourly rate</th></tr></thead>
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
            <td><input type="number" step="0.1" min="0" max="100" name="supply[<?= (int)$r['id'] ?>]" value="<?= (float)$r['supply_fee_rate'] ?>" style="width:110px"></td>
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
