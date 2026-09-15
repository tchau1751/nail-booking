<?php
// ============================================================
//  End of day — what a salon closes the day on. Every line rung up,
//  under the technician who did the work, with what they earned and
//  their tips; then what came in by each way of paying, and the cash
//  that should be in the drawer.
//
//  Earnings follow Payroll's rules to the cent: commission and supply
//  fee on service revenue net of tax and refunds, card tips owed, cash
//  tips already handed over. The tenders and the drawer follow Reports.
// ============================================================
$pageTitle = 'End of day';
$activeNav = 'endofday';
$requireRole = 'manager';   // enforced by layout_start before any output
require_once __DIR__ . '/includes/layout_start.php';

$asked  = is_string($_GET['date'] ?? null) ? $_GET['date'] : '';
$dayObj = DateTime::createFromFormat('!Y-m-d', $asked);
if (!$dayObj || $dayObj->format('Y-m-d') !== $asked) $dayObj = new DateTime('today');
$day  = $dayObj->format('Y-m-d');
$prev = (clone $dayObj)->modify('-1 day')->format('Y-m-d');
$next = (clone $dayObj)->modify('+1 day')->format('Y-m-d');

$tid      = tenantId();
$supplyOn = (int)(posSettings()['supply_fee_enabled'] ?? 0) === 1;
$salon    = settings()['business_name'] ?? '';

$techById = [];
foreach (fetchAll('SELECT id, name, pay_type, commission_rate, hourly_rate, supply_fee_rate
                   FROM technicians WHERE tenant_id = ? ORDER BY display_order, name', [$tid]) as $t) {
    $techById[(int)$t['id']] = $t;
}
$hours = [];
foreach (fetchAll('SELECT technician_id,
                     COALESCE(SUM(TIMESTAMPDIFF(MINUTE, clock_in, COALESCE(clock_out, NOW()))),0)/60 h
                   FROM pos_tech_shifts WHERE tenant_id = ? AND shift_date = ?
                   GROUP BY technician_id', [$tid, $day]) as $h) {
    $hours[(int)$h['technician_id']] = (float)$h['h'];
}

// Every line rung up that day, less whatever has been refunded off it since.
$lines = fetchAll("SELECT i.id, s.id sale_id, s.sale_no, s.tip_method, i.item_type, i.name, i.unit_price, i.qty,
                          i.discount, i.tax, i.line_total, i.tip, i.technician_id,
                          COALESCE(rf.amount, 0) refunded, COALESCE(rf.tip, 0) refunded_tip
                   FROM pos_sale_items i
                   JOIN pos_sales s ON s.id = i.sale_id AND s.tenant_id = i.tenant_id
                   LEFT JOIN (SELECT sale_item_id, SUM(amount) amount, SUM(tip) tip
                                FROM pos_refund_items WHERE tenant_id = ? GROUP BY sale_item_id) rf
                          ON rf.sale_item_id = i.id
                   WHERE i.tenant_id = ? AND s.status IN ('completed','refunded') AND DATE(s.created_at) = ?
                   ORDER BY s.id, i.id", [$tid, $tid, $day]);

// Group by technician. Money is summed in cents, so a day's total turns into
// exactly the figure Payroll gets from the database, rounding and all.
$blank  = ['lines' => [], 'service_c' => 0, 'product_c' => 0, 'gift_c' => 0, 'price_c' => 0,
           'discount_c' => 0, 'refunded_c' => 0, 'tips_card_c' => 0, 'tips_cash_c' => 0];
$groups = [];
$anyRefund = false;
foreach ($lines as $l) {
    $tech = $techById[(int)$l['technician_id']] ?? null;
    $key  = $tech ? (int)$tech['id'] : 0;
    if (!isset($groups[$key])) $groups[$key] = $blank + ['tech' => $tech];

    $isService = in_array($l['item_type'], ['service', 'custom'], true);
    $netC   = (int)round(((float)$l['line_total'] - (float)$l['tax'] - (float)$l['refunded']) * 100);
    $rate   = ($tech && $isService && $tech['pay_type'] === 'commission') ? (float)$tech['commission_rate'] : 0.0;
    $srate  = ($tech && $isService && $supplyOn && $tech['pay_type'] !== 'booth') ? (float)$tech['supply_fee_rate'] : 0.0;
    $tipC   = (int)round(((float)$l['tip'] - (float)$l['refunded_tip']) * 100);

    $l['price']  = round((float)$l['unit_price'] * (int)$l['qty'], 2);
    $l['supply'] = round($netC / 100 * $srate / 100, 2);
    $l['earns']  = round($netC / 100 * $rate / 100, 2) - $l['supply'];
    $l['tip']    = $tipC / 100;
    $anyRefund   = $anyRefund || (float)$l['refunded'] > 0;

    $g = &$groups[$key];
    $g['lines'][]     = $l;
    $g['price_c']    += (int)round($l['price'] * 100);
    $g['discount_c'] += (int)round((float)$l['discount'] * 100);
    $g['refunded_c'] += (int)round((float)$l['refunded'] * 100);
    if ($isService)                         $g['service_c'] += $netC;
    elseif ($l['item_type'] === 'product')  $g['product_c'] += $netC;
    else                                    $g['gift_c']    += $netC;
    if ($l['tip_method'] === 'cash') $g['tips_cash_c'] += $tipC; else $g['tips_card_c'] += $tipC;
    unset($g);
}
// Someone paid by the hour who clocked in but rang nothing up is still owed a wage.
foreach ($hours as $id => $h) {
    if ($h > 0 && isset($techById[$id]) && !isset($groups[$id])) $groups[$id] = $blank + ['tech' => $techById[$id]];
}

// Payroll's payout, for this one day.
$sum = ['service' => 0.0, 'product' => 0.0, 'gift' => 0.0, 'refunded' => 0.0, 'payout' => 0.0];
foreach ($groups as $key => &$g) {
    $t = $g['tech'];
    foreach (['service', 'product', 'gift', 'price', 'discount', 'refunded', 'tips_card', 'tips_cash'] as $f) {
        $g[$f] = $g[$f . '_c'] / 100;
    }
    $g['hours']      = $t ? ($hours[$key] ?? 0.0) : 0.0;
    $g['commission'] = ($t && $t['pay_type'] === 'commission') ? round($g['service'] * (float)$t['commission_rate'] / 100, 2) : 0.0;
    $g['supply']     = ($t && $supplyOn && $t['pay_type'] !== 'booth') ? round($g['service'] * (float)$t['supply_fee_rate'] / 100, 2) : 0.0;
    $g['wage']       = ($t && $t['pay_type'] === 'hourly') ? round($g['hours'] * (float)$t['hourly_rate'], 2) : 0.0;
    $g['payout']     = $t ? round($g['commission'] + $g['wage'] - $g['supply'] + $g['tips_card'], 2) : 0.0;
    foreach (['service', 'product', 'gift', 'refunded', 'payout'] as $f) $sum[$f] += $g[$f];
}
unset($g);
// Technicians in the salon's own order; lines nobody was assigned to come last.
$order = array_flip(array_keys($techById));
uksort($groups, function ($a, $b) use ($order) {
    if ($a === 0 || $b === 0) return ($a === 0) <=> ($b === 0);
    return ($order[$a] ?? 0) <=> ($order[$b] ?? 0);
});

// ── The till: tickets, each way of paying, and the drawer ──────
$head = fetchOne("SELECT COUNT(*) tickets, COALESCE(SUM(discount_total),0) disc, COALESCE(SUM(tax_total),0) tax,
                         COALESCE(SUM(tip_total),0) tip, COALESCE(SUM(grand_total),0) total,
                         COALESCE(SUM(change_due),0) change_given
                  FROM pos_sales WHERE tenant_id = ? AND status IN ('completed','refunded') AND DATE(created_at) = ?",
                 [$tid, $day]) ?: [];
// Points are stored as an "other" payment; here they get a row of their own.
$tender = [];
foreach (fetchAll("SELECT CASE WHEN p.method = 'other' AND p.reference LIKE '% points' THEN 'points' ELSE p.method END m,
                          COUNT(*) c, COALESCE(SUM(p.amount),0) amt
                   FROM pos_payments p JOIN pos_sales s ON s.id = p.sale_id AND s.tenant_id = p.tenant_id
                   WHERE p.tenant_id = ? AND s.status IN ('completed','refunded') AND DATE(s.created_at) = ?
                   GROUP BY m", [$tid, $day]) as $p) {
    $tender[$p['m']] = ['c' => (int)$p['c'], 'amt' => (float)$p['amt']];
}
// The salon's own methods always show, even at $0; anything else that came in follows.
$methodKeys = array_values(array_unique(array_merge(array_keys(paymentMethodsOn()), array_keys($tender))));

$refunds = fetchOne("SELECT COUNT(*) c, COALESCE(SUM(total),0) total,
                            COALESCE(SUM(CASE WHEN method = 'cash' THEN total ELSE 0 END),0) cash
                     FROM pos_refunds WHERE tenant_id = ? AND DATE(created_at) = ?", [$tid, $day]) ?: [];
$drawer  = (float)(fetchOne("SELECT COALESCE(SUM(CASE WHEN kind IN ('open','pay_in') THEN amount ELSE -amount END),0) v
                             FROM pos_cash_movements WHERE tenant_id = ? AND DATE(created_at) = ?", [$tid, $day])['v'] ?? 0);
$voided  = fetchOne("SELECT COUNT(*) c, COALESCE(SUM(grand_total),0) total
                     FROM pos_sales WHERE tenant_id = ? AND status = 'voided' AND DATE(created_at) = ?", [$tid, $day]) ?: [];

$changeGiven  = (float)($head['change_given'] ?? 0);
$expectedCash = round(($tender['cash']['amt'] ?? 0) - $changeGiven - (float)($refunds['cash'] ?? 0) + $drawer, 2);
// Everything taken, less the change handed back, is what the tickets came to.
$paymentsIn = array_sum(array_column($tender, 'amt'));
$balances   = abs(round($paymentsIn - $changeGiven - (float)($head['total'] ?? 0), 2)) < 0.01;

// Numbers only, no currency symbol — a spreadsheet cannot add up "$74.50".
if (($_GET['export'] ?? '') === 'csv') {
    $csv = [['End of day', $day, $salon], []];
    $csv[] = ['Summary'];
    $csv[] = ['Tickets', (int)($head['tickets'] ?? 0)];
    $csv[] = ['Service sales', round($sum['service'], 2)];
    $csv[] = ['Product sales', round($sum['product'], 2)];
    $csv[] = ['Gift card sales', round($sum['gift'], 2)];
    $csv[] = ['Discounts', round((float)($head['disc'] ?? 0), 2)];
    $csv[] = ['Tax', round((float)($head['tax'] ?? 0), 2)];
    $csv[] = ['Tips', round((float)($head['tip'] ?? 0), 2)];
    $csv[] = ['Total collected', round((float)($head['total'] ?? 0), 2)];
    $csv[] = ['Change given', round($changeGiven, 2)];
    $csv[] = ['Refunds issued', round((float)($refunds['total'] ?? 0), 2)];
    $csv[] = ['Cash expected in drawer', $expectedCash];
    $csv[] = [];
    $csv[] = ['By payment method', 'Count', 'Amount'];
    foreach ($methodKeys as $m) $csv[] = [paymentLabel($m), $tender[$m]['c'] ?? 0, round($tender[$m]['amt'] ?? 0, 2)];
    $csv[] = [];
    $csv[] = ['Payout by technician', 'Services', 'Retail', 'Commission', 'Supply fee', 'Hours', 'Wage',
              'Card tips', 'Cash tips', 'Payout'];
    foreach ($groups as $g) {
        if (!$g['tech']) continue;
        $csv[] = [$g['tech']['name'], round($g['service'], 2), round($g['product'], 2), $g['commission'], $g['supply'],
                  round($g['hours'], 2), $g['wage'], round($g['tips_card'], 2), round($g['tips_cash'], 2), $g['payout']];
    }
    $csv[] = [];
    $csv[] = ['Line', 'Technician', 'Ticket', 'Service or product', 'Price', 'Discount', 'Refunded', 'Supply fee',
              'Tech earns', 'Tip', 'Tip paid in'];
    foreach ($groups as $g) {
        foreach ($g['lines'] as $l) {
            $csv[] = ['line', $g['tech']['name'] ?? 'Not assigned', $l['sale_no'], $l['name'] . ((int)$l['qty'] > 1 ? ' x' . (int)$l['qty'] : ''),
                      $l['price'], round((float)$l['discount'], 2), round((float)$l['refunded'], 2), $l['supply'],
                      round($l['earns'], 2), $l['tip'], $l['tip_method']];
        }
    }
    posCsvOut('end-of-day-' . $day . '.csv', $csv);
}
?>
<style>
  .eod-title{font-size:20px;font-weight:700;margin:0 0 4px;}
  .eod-sub{font-size:13px;color:var(--ink-soft);margin:0 0 14px;}
  .eod-bar{display:grid;grid-template-columns:repeat(auto-fit,minmax(118px,1fr));background:var(--card);
    border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:12px;}
  .eod-bar > div{padding:10px 12px;border-right:1px solid var(--line);border-bottom:1px solid var(--line);}
  .eod-bar .k{font-size:12px;color:var(--ink-soft);font-weight:600;}
  .eod-bar .v{font-size:17px;font-weight:700;white-space:nowrap;}
  .eod-bar .total{background:var(--ink);color:#fff;}
  .eod-bar .total .k{color:rgba(255,255,255,.75);}
  .eod-tech h2 small{font-weight:500;color:var(--ink-soft);font-size:13px;margin-left:8px;}
  .eod-payout{margin:10px 0 0;font-size:14px;}
  @media print{
    .eod-bar{box-shadow:none;border:1px solid #ccc;}
    .eod-bar .total{background:#eee;color:#000;}
    .eod-bar .total .k{color:#555;}
    .card{break-inside:avoid;}
  }
</style>

<form class="toolbar no-print" method="get">
  <a class="btn btn-light" href="?date=<?= $prev ?>">‹ <?= date('m/d', strtotime($prev)) ?></a>
  <label class="field"><span>Day</span><input type="date" name="date" value="<?= e($day) ?>"></label>
  <button class="btn" type="submit">Show</button>
  <a class="btn btn-light" href="?date=<?= $next ?>"><?= date('m/d', strtotime($next)) ?> ›</a>
  <a class="btn btn-light" href="<?= BASE_PATH ?>/pos/endofday.php">Today</a>
  <a class="btn btn-light" href="?date=<?= e($day) ?>&export=csv">⬇ CSV</a>
  <button class="btn btn-blue" type="button" onclick="window.print()">🖨 Print</button>
</form>

<h2 class="eod-title">End of the day · <?= e($dayObj->format('l, m/d/Y')) ?></h2>
<p class="eod-sub"><?= e($salon) ?> · <?= (int)($head['tickets'] ?? 0) ?> tickets<?php if ((int)($voided['c'] ?? 0) > 0): ?>
  · <?= (int)$voided['c'] ?> voided (<?= money($voided['total']) ?>, not counted)<?php endif; ?></p>

<div class="eod-bar">
  <div><div class="k">Service sales</div><div class="v"><?= money($sum['service']) ?></div></div>
  <div><div class="k">Product sales</div><div class="v"><?= money($sum['product']) ?></div></div>
  <div><div class="k">Gift card sales</div><div class="v"><?= money($sum['gift']) ?></div></div>
  <div><div class="k">Discounts</div><div class="v"><?= money($head['disc'] ?? 0) ?></div></div>
  <div><div class="k">Tax</div><div class="v"><?= money($head['tax'] ?? 0) ?></div></div>
  <div><div class="k">Tips</div><div class="v"><?= money($head['tip'] ?? 0) ?></div></div>
  <div class="total"><div class="k">Total collected</div><div class="v" id="eodTotal"><?= money($head['total'] ?? 0) ?></div></div>
</div>

<div class="eod-bar">
  <?php foreach ($methodKeys as $m): ?>
    <div><div class="k"><?= e(paymentLabel($m)) ?><?= !empty($tender[$m]['c']) ? ' ×' . (int)$tender[$m]['c'] : '' ?></div>
         <div class="v"><?= money($tender[$m]['amt'] ?? 0) ?></div></div>
  <?php endforeach; ?>
  <div><div class="k">Change given</div><div class="v">−<?= money($changeGiven) ?></div></div>
  <?php if ((float)($refunds['total'] ?? 0) > 0): ?>
    <div><div class="k">Refunds issued ×<?= (int)$refunds['c'] ?></div><div class="v">−<?= money($refunds['total']) ?></div></div>
  <?php endif; ?>
  <div class="total"><div class="k">Cash in the drawer</div><div class="v" id="eodCash"><?= money($expectedCash) ?></div></div>
</div>

<?php if (!$balances): ?>
  <div class="alert alert-err">The payments (<?= money($paymentsIn) ?>, less <?= money($changeGiven) ?> change) do not add up to
    the <?= money($head['total'] ?? 0) ?> the tickets came to. Check the day's sales before closing.</div>
<?php endif; ?>

<?php if (!$groups): ?>
  <div class="card"><div class="empty" style="padding:30px">No sales on this day.</div></div>
<?php else: ?>
  <p class="eod-sub">Tech earns is worked out per line and rounded; each payout is worked out on the day's total, the same way as
     <a href="<?= BASE_PATH ?>/pos/payroll.php?from=<?= e($day) ?>&to=<?= e($day) ?>">Payroll</a>.</p>
<?php endif; ?>

<?php foreach ($groups as $key => $g): $t = $g['tech']; ?>
  <div class="card eod-tech">
    <h2><?= $t ? e($t['name']) : 'Not assigned to a technician' ?>
      <?php if ($t): ?><small><?php
        if ($t['pay_type'] === 'commission') echo (float)$t['commission_rate'] . '% commission';
        elseif ($t['pay_type'] === 'hourly') echo 'hourly ' . money($t['hourly_rate']);
        else echo 'booth rent';
        if ($supplyOn && $t['pay_type'] !== 'booth' && (float)$t['supply_fee_rate'] > 0) echo ' · ' . (float)$t['supply_fee_rate'] . '% supply fee';
      ?></small><?php endif; ?></h2>

    <?php if ($g['lines']): ?>
      <div class="table-wrap">
        <table>
          <thead><tr>
            <th>Ticket</th><th>Service or product</th><th class="num">Price</th><th class="num">Discount</th>
            <?php if ($anyRefund): ?><th class="num">Refunded</th><?php endif; ?>
            <?php if ($supplyOn): ?><th class="num">Supply fee</th><?php endif; ?>
            <th class="num">Tech earns</th><th class="num">Tip</th>
          </tr></thead>
          <tbody>
          <?php foreach ($g['lines'] as $l): ?>
            <tr>
              <td><a href="<?= BASE_PATH ?>/pos/receipt.php?id=<?= (int)$l['sale_id'] ?>" target="_blank" rel="noopener"><?= e($l['sale_no']) ?></a></td>
              <td><?= e($l['name']) ?><?= (int)$l['qty'] > 1 ? ' × ' . (int)$l['qty'] : '' ?></td>
              <td class="num"><?= money($l['price']) ?></td>
              <td class="num"><?= (float)$l['discount'] > 0 ? '−' . money($l['discount']) : '' ?></td>
              <?php if ($anyRefund): ?><td class="num"><?= (float)$l['refunded'] > 0 ? '−' . money($l['refunded']) : '' ?></td><?php endif; ?>
              <?php if ($supplyOn): ?><td class="num"><?= $l['supply'] > 0 ? '−' . money($l['supply']) : '' ?></td><?php endif; ?>
              <td class="num"><?= money($l['earns']) ?></td>
              <td class="num"><?= $l['tip'] > 0 ? money($l['tip']) . ($l['tip_method'] === 'cash' ? ' <small>cash</small>' : '') : '' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:#faf7f5;font-weight:800">
              <td colspan="2">Total</td>
              <td class="num"><?= money($g['price']) ?></td>
              <td class="num"><?= $g['discount'] > 0 ? '−' . money($g['discount']) : '' ?></td>
              <?php if ($anyRefund): ?><td class="num"><?= $g['refunded'] > 0 ? '−' . money($g['refunded']) : '' ?></td><?php endif; ?>
              <?php if ($supplyOn): ?><td class="num"><?= $g['supply'] > 0 ? '−' . money($g['supply']) : '' ?></td><?php endif; ?>
              <td class="num"><?= money($g['commission'] - $g['supply']) ?></td>
              <td class="num"><?= money($g['tips_card'] + $g['tips_cash']) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>

    <?php if ($t): ?>
      <p class="eod-payout" data-tech="<?= (int)$t['id'] ?>" data-payout="<?= number_format($g['payout'], 2, '.', '') ?>">
        Commission <?= money($g['commission']) ?>
        <?php if ($supplyOn): ?> − supply fee <?= money($g['supply']) ?><?php endif; ?>
        <?php if ($g['wage'] > 0): ?> + wage <?= money($g['wage']) ?> (<?= number_format($g['hours'], 1) ?> h)<?php endif; ?>
        + card tips <?= money($g['tips_card']) ?>
        = <strong>payout <?= money($g['payout']) ?></strong>
        <?php if ($g['tips_cash'] > 0): ?> · cash tips handed over <?= money($g['tips_cash']) ?><?php endif; ?>
      </p>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<?php if ($groups): ?>
  <div class="card">
    <h2>Payouts for the day</h2>
    <div class="table-wrap">
      <table>
        <tbody>
        <?php foreach ($groups as $g): if (!$g['tech']) continue; ?>
          <tr><td><?= e($g['tech']['name']) ?></td><td class="num"><?= money($g['payout']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:#faf7f5;font-weight:800"><td>Total to pay out</td><td class="num"><?= money($sum['payout']) ?></td></tr>
        </tfoot>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
