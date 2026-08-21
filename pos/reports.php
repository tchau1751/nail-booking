<?php
$pageTitle = 'Reports';
$activeNav = 'reports';
$requireRole = 'manager';   // enforced by layout_start before any output
require_once __DIR__ . '/includes/layout_start.php';

$from = $_GET['from'] ?? date('Y-m-d');
$to   = $_GET['to']   ?? date('Y-m-d');
$rng  = [$from, $to];
$done = "status='completed' AND DATE(created_at) BETWEEN ? AND ?";

$head = fetchOne("SELECT COUNT(*) c, COALESCE(SUM(subtotal),0) sub, COALESCE(SUM(discount_total),0) disc,
                         COALESCE(SUM(tax_total),0) tax, COALESCE(SUM(tip_total),0) tip,
                         COALESCE(SUM(grand_total),0) tot
                  FROM pos_sales WHERE $done", $rng) ?: [];

$byMethod = fetchAll("SELECT p.method, COUNT(*) c, SUM(p.amount) amt
                      FROM pos_payments p JOIN pos_sales s ON s.id=p.sale_id
                      WHERE s.status='completed' AND DATE(s.created_at) BETWEEN ? AND ?
                      GROUP BY p.method ORDER BY amt DESC", $rng);

// Attribution is line-level here exactly as it is in Payroll. The two pages
// used to disagree — Reports fell back to the ticket's technician, Payroll did
// not — and a payout report nobody can reconcile is worse than none.
$byTech = fetchAll("SELECT COALESCE(t.name,'Unassigned') tech, COUNT(DISTINCT i.sale_id) tickets,
                           SUM(i.line_total) revenue
                    FROM pos_sale_items i
                    JOIN pos_sales s ON s.id=i.sale_id
                    LEFT JOIN technicians t ON t.id = i.technician_id
                    WHERE s.status='completed' AND DATE(s.created_at) BETWEEN ? AND ?
                    GROUP BY tech ORDER BY revenue DESC", $rng);

$tipsByTech = fetchAll("SELECT COALESCE(t.name,'Unassigned') tech,
                               COUNT(DISTINCT i.sale_id) tickets,
                               SUM(i.tip) tips,
                               SUM(CASE WHEN s.tip_method='cash' THEN i.tip ELSE 0 END) tips_cash,
                               AVG(CASE WHEN i.line_total - i.tax > 0
                                        THEN i.tip / (i.line_total - i.tax) * 100 END) pct
                        FROM pos_sale_items i
                        JOIN pos_sales s ON s.id = i.sale_id
                        LEFT JOIN technicians t ON t.id = i.technician_id
                        WHERE s.status='completed' AND i.tip > 0
                          AND DATE(s.created_at) BETWEEN ? AND ?
                        GROUP BY tech ORDER BY tips DESC", $rng);

$byItem = fetchAll("SELECT i.name, i.item_type, SUM(i.qty) qty, SUM(i.line_total) revenue
                    FROM pos_sale_items i JOIN pos_sales s ON s.id=i.sale_id
                    WHERE s.status='completed' AND DATE(s.created_at) BETWEEN ? AND ?
                    GROUP BY i.name, i.item_type ORDER BY revenue DESC LIMIT 25", $rng);

$byDay = fetchAll("SELECT DATE(created_at) d, COUNT(*) c, SUM(grand_total) tot
                   FROM pos_sales WHERE $done GROUP BY d ORDER BY d DESC", $rng);

$cashSales = 0;
foreach ($byMethod as $m) if ($m['method'] === 'cash') $cashSales = (float)$m['amt'];
$changeGiven = (float)(fetchOne("SELECT COALESCE(SUM(change_due),0) v FROM pos_sales WHERE $done", $rng)['v'] ?? 0);
$drawer = fetchOne("SELECT COALESCE(SUM(CASE WHEN kind IN ('open','pay_in') THEN amount ELSE -amount END),0) v
                    FROM pos_cash_movements WHERE DATE(created_at) BETWEEN ? AND ?", $rng);
$expectedCash = $cashSales - $changeGiven + (float)($drawer['v'] ?? 0);
$lowStock = fetchAll('SELECT * FROM pos_products WHERE is_active=1 AND stock_qty <= low_stock_at ORDER BY stock_qty');

// ── Profit & loss ────────────────────────────────────────────
// Revenue excludes tax (that money is the state's) and tips (that money is
// the tech's). Selling a gift card isn't revenue either — it's a liability
// until it gets spent, and the spend already shows up as a service or retail
// line on some later ticket.
$giftSold = (float)(fetchOne("SELECT COALESCE(SUM(i.line_total),0) v FROM pos_sale_items i
                              JOIN pos_sales s ON s.id=i.sale_id
                              WHERE i.item_type='giftcard' AND s.status='completed'
                                AND DATE(s.created_at) BETWEEN ? AND ?", $rng)['v'] ?? 0);
$netRevenue = (float)($head['tot'] ?? 0) - (float)($head['tax'] ?? 0) - (float)($head['tip'] ?? 0) - $giftSold;

$cogs = (float)(fetchOne("SELECT COALESCE(SUM(p.cost * i.qty),0) v
                          FROM pos_sale_items i
                          JOIN pos_products p ON p.id = i.ref_id
                          JOIN pos_sales s ON s.id = i.sale_id
                          WHERE i.item_type='product' AND s.status='completed'
                            AND DATE(s.created_at) BETWEEN ? AND ?", $rng)['v'] ?? 0);

$supplyOn   = (int)(posSettings()['supply_fee_enabled'] ?? 0) === 1;
$commission = 0.0;
$supplyKept = 0.0;
foreach (fetchAll("SELECT t.commission_rate, t.pay_type, t.supply_fee_rate,
                          COALESCE(SUM(i.line_total - i.tax),0) rev
                   FROM pos_sale_items i
                   JOIN pos_sales s ON s.id=i.sale_id
                   JOIN technicians t ON t.id = i.technician_id
                   WHERE i.item_type='service' AND s.status='completed'
                     AND DATE(s.created_at) BETWEEN ? AND ?
                   GROUP BY t.id, t.commission_rate, t.pay_type, t.supply_fee_rate", $rng) as $c) {
    if ($c['pay_type'] === 'commission') $commission += (float)$c['rev'] * (float)$c['commission_rate'] / 100;
    // Charged on the chair's takings and withheld from the payout, so it comes
    // straight back off the wage bill. Booth renters buy their own.
    if ($supplyOn && $c['pay_type'] !== 'booth') {
        $supplyKept += (float)$c['rev'] * (float)$c['supply_fee_rate'] / 100;
    }
}
$commission = round($commission, 2);
$supplyKept = round($supplyKept, 2);
$commission = round($commission - $supplyKept, 2);

$expenses   = fetchAll('SELECT category, SUM(amount) v FROM pos_expenses
                        WHERE expense_date BETWEEN ? AND ? GROUP BY category ORDER BY v DESC', $rng);
$expenseTot = array_sum(array_column($expenses, 'v'));
$netProfit  = round($netRevenue - $cogs - $commission - $expenseTot, 2);
?>
<form class="toolbar no-print" method="get">
  <label class="field"><span>From</span><input type="date" name="from" value="<?= e($from) ?>"></label>
  <label class="field"><span>To</span><input type="date" name="to" value="<?= e($to) ?>"></label>
  <button class="btn" type="submit">Apply</button>
  <a class="btn btn-light" href="?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>">Today</a>
  <a class="btn btn-light" href="?from=<?= date('Y-m-d', strtotime('monday this week')) ?>&to=<?= date('Y-m-d') ?>">This week</a>
  <a class="btn btn-light" href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>">This month</a>
  <button class="btn btn-blue" type="button" onclick="window.print()">🖨 Print Z-report</button>
</form>

<div class="stats">
  <div class="stat"><div class="v"><?= (int)($head['c'] ?? 0) ?></div><div class="k">Tickets</div></div>
  <div class="stat"><div class="v"><?= money($head['tot'] ?? 0) ?></div><div class="k">Gross collected</div></div>
  <div class="stat"><div class="v"><?= money($head['tip'] ?? 0) ?></div><div class="k">Tips</div></div>
  <div class="stat"><div class="v"><?= money(($head['c'] ?? 0) ? $head['tot'] / $head['c'] : 0) ?></div><div class="k">Average ticket</div></div>
  <div class="stat"><div class="v"><?= money($expectedCash) ?></div><div class="k">Cash expected in drawer</div></div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px">
  <div class="card">
    <h2>Totals</h2>
    <table>
      <tr><td>Subtotal</td><td class="num"><?= money($head['sub'] ?? 0) ?></td></tr>
      <tr><td>Discounts</td><td class="num">−<?= money($head['disc'] ?? 0) ?></td></tr>
      <tr><td>Tax</td><td class="num"><?= money($head['tax'] ?? 0) ?></td></tr>
      <tr><td>Tips</td><td class="num"><?= money($head['tip'] ?? 0) ?></td></tr>
      <tr><td><strong>Grand total</strong></td><td class="num"><strong><?= money($head['tot'] ?? 0) ?></strong></td></tr>
    </table>
  </div>

  <div class="card">
    <h2>Payment mix</h2>
    <table>
      <?php foreach ($byMethod as $m): ?>
        <tr><td><?= ucfirst($m['method']) ?> <span style="color:var(--ink-soft)">×<?= (int)$m['c'] ?></span></td>
            <td class="num"><?= money($m['amt']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$byMethod): ?><tr><td colspan="2" style="color:var(--ink-soft)">No payments yet.</td></tr><?php endif; ?>
      <?php if ($changeGiven > 0): ?><tr><td>Change given</td><td class="num">−<?= money($changeGiven) ?></td></tr><?php endif; ?>
    </table>
  </div>

  <div class="card">
    <h2>By technician</h2>
    <table>
      <?php foreach ($byTech as $t): ?>
        <tr><td><?= e($t['tech']) ?> <span style="color:var(--ink-soft)"><?= (int)$t['tickets'] ?> tickets</span></td>
            <td class="num"><?= money($t['revenue']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$byTech): ?><tr><td style="color:var(--ink-soft)">No sales yet.</td></tr><?php endif; ?>
    </table>
  </div>

  <div class="card">
    <h2>Tips by technician</h2>
    <table>
      <?php foreach ($tipsByTech as $t): ?>
        <tr><td><?= e($t['tech']) ?>
              <span style="color:var(--ink-soft)"><?= (int)$t['tickets'] ?> tickets · avg <?= number_format((float)$t['pct'], 1) ?>%
              <?php if ((float)$t['tips_cash'] > 0): ?> · <?= money($t['tips_cash']) ?> in cash<?php endif; ?></span></td>
            <td class="num"><?= money($t['tips']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$tipsByTech): ?><tr><td style="color:var(--ink-soft)">No tips in this range.</td></tr><?php endif; ?>
    </table>
  </div>

  <div class="card">
    <h2>By day</h2>
    <table>
      <?php foreach ($byDay as $d): ?>
        <tr><td><?= date('D m/d', strtotime($d['d'])) ?> <span style="color:var(--ink-soft)"><?= (int)$d['c'] ?> tickets</span></td>
            <td class="num"><?= money($d['tot']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$byDay): ?><tr><td style="color:var(--ink-soft)">No sales yet.</td></tr><?php endif; ?>
    </table>
  </div>
</div>

<div class="card">
  <h2>💰 Profit &amp; loss</h2>
  <p class="sub">
    Revenue is what the salon actually earned — tax and tips are stripped out because that money is never yours,
    and gift card sales are held back until the card is spent.
    <a href="<?= BASE_PATH ?>/pos/expenses.php" class="no-print">Record expenses →</a>
  </p>
  <div class="table-wrap">
    <table>
      <tbody>
        <tr><td>Gross collected</td><td class="num"><?= money($head['tot'] ?? 0) ?></td></tr>
        <tr><td style="padding-left:28px;color:var(--ink-soft)">less tax collected</td><td class="num">−<?= money($head['tax'] ?? 0) ?></td></tr>
        <tr><td style="padding-left:28px;color:var(--ink-soft)">less tips (paid to techs)</td><td class="num">−<?= money($head['tip'] ?? 0) ?></td></tr>
        <tr><td style="padding-left:28px;color:var(--ink-soft)">less gift cards sold (deferred)</td><td class="num">−<?= money($giftSold) ?></td></tr>
        <tr style="font-weight:800;background:#faf7f5"><td>Net revenue</td><td class="num"><?= money($netRevenue) ?></td></tr>
        <tr><td>Cost of retail goods sold</td><td class="num">−<?= money($cogs) ?></td></tr>
        <tr><td>Technician commission</td><td class="num">−<?= money($commission) ?></td></tr>
        <?php foreach ($expenses as $x): ?>
          <tr><td><?= e($x['category']) ?></td><td class="num">−<?= money($x['v']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$expenses): ?>
          <tr><td style="color:var(--ink-soft)">No expenses recorded in this range</td><td class="num">−<?= money(0) ?></td></tr>
        <?php endif; ?>
        <tr style="font-size:19px;font-weight:800;background:<?= $netProfit >= 0 ? '#e8f6ee' : '#fdecec' ?>">
          <td><?= $netProfit >= 0 ? 'Net profit' : 'Net loss' ?></td>
          <td class="num"><?= money(abs($netProfit)) ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <h2>Top sellers</h2>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Item</th><th>Type</th><th class="num">Qty</th><th class="num">Revenue</th></tr></thead>
      <tbody>
      <?php foreach ($byItem as $i): ?>
        <tr><td><?= e($i['name']) ?></td><td><?= e($i['item_type']) ?></td>
            <td class="num"><?= (int)$i['qty'] ?></td><td class="num"><?= money($i['revenue']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$byItem): ?><tr><td colspan="4" style="color:var(--ink-soft)">Nothing sold in this range.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($lowStock): ?>
<div class="card">
  <h2>⚠️ Low stock</h2>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Product</th><th class="num">On hand</th><th class="num">Alert at</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($lowStock as $p): ?>
        <tr><td><?= e($p['name']) ?></td><td class="num"><?= (int)$p['stock_qty'] ?></td>
            <td class="num"><?= (int)$p['low_stock_at'] ?></td>
            <td class="no-print"><a class="btn btn-light btn-sm" href="<?= BASE_PATH ?>/pos/products.php?edit=<?= (int)$p['id'] ?>">Restock</a></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
