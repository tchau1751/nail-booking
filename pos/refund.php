<?php
$pageTitle = 'Refund';
$activeNav = 'sales';
$requireRole = 'manager';   // enforced by layout_start before any output
require_once __DIR__ . '/includes/layout_start.php';

$saleId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$msg = ''; $err = ''; $done = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'refund') {
    try {
        $refundId = refundSale(
            $saleId,
            array_map('intval', (array)($_POST['qty'] ?? [])),
            (string)($_POST['method'] ?? 'cash'),
            (string)($_POST['reason'] ?? ''),
            isset($_POST['with_tip'])
        );
        $done = fetchOne('SELECT * FROM pos_refunds WHERE id=? AND tenant_id=?', [$refundId, tenantId()]);
        $msg  = 'Refund ' . $done['refund_no'] . ' recorded — ' . money($done['total']) . ' back to the guest.';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$sale = $saleId ? fetchOne('SELECT * FROM pos_sales WHERE id=? AND tenant_id=?', [$saleId, tenantId()]) : null;
if (!$sale) {
    echo '<div class="alert alert-err">That sale could not be found.</div>';
    echo '<a class="btn" href="' . BASE_PATH . '/pos/sales.php">Back to sales</a>';
    require_once __DIR__ . '/includes/layout_end.php';
    exit;
}

$lines    = refundableLines($saleId);
$previous = fetchAll('SELECT r.*, u.name AS who FROM pos_refunds r
                      LEFT JOIN admin_users u ON u.id = r.admin_id
                      WHERE r.tenant_id=? AND r.sale_id=? ORDER BY r.id DESC', [tenantId(), $saleId]);
$anyLeft  = false;
foreach ($lines as $l) {
    if ((int)$l['left_qty'] > 0 && $l['item_type'] !== 'giftcard') $anyLeft = true;
}
?>
<?php if ($msg): ?><div class="alert alert-ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>

<div class="card">
  <h2>Ticket <?= e($sale['sale_no']) ?></h2>
  <p class="sub">
    <?= date('m/d/Y g:i A', strtotime($sale['created_at'])) ?>
    · <?= e($sale['customer_name'] ?: 'Walk-in') ?>
    · <?= money($sale['grand_total']) ?> taken
    · <span class="pill <?= $sale['status'] === 'completed' ? 'pill-ok' : 'pill-void' ?>"><?= e($sale['status']) ?></span>
  </p>

  <?php if ($sale['status'] === 'voided'): ?>
    <div class="alert alert-err">This sale was voided. There is nothing left to refund.</div>
  <?php elseif (!$anyLeft): ?>
    <div class="alert alert-ok">Every line on this ticket has already been refunded.</div>
  <?php else: ?>
  <form method="post" onsubmit="return confirm('Record this refund? It cannot be undone.')">
    <input type="hidden" name="action" value="refund">
    <input type="hidden" name="id" value="<?= (int)$saleId ?>">
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>Line</th><th>Technician</th><th class="num">Sold</th><th class="num">Already back</th>
          <th class="num">Each</th><th class="num">Give back</th>
        </tr></thead>
        <tbody>
        <?php foreach ($lines as $l):
          $sold = max(1, (int)$l['qty']);
          $each = ((float)$l['line_total'] - (float)$l['tax']) / $sold;
          $left = (int)$l['left_qty'];
          $isGift = $l['item_type'] === 'giftcard'; ?>
          <tr>
            <td><strong><?= e($l['name']) ?></strong>
              <div style="font-size:12px;color:var(--ink-soft)"><?= e($l['item_type']) ?>
                <?php if ((float)$l['tip'] > 0): ?> · tip <?= money($l['tip']) ?><?php endif; ?></div></td>
            <td><?= e($l['tech_name'] ?: '—') ?></td>
            <td class="num"><?= $sold ?></td>
            <td class="num"><?= (int)$l['refunded_qty'] ?></td>
            <td class="num"><?= money($each) ?></td>
            <td class="num">
              <?php if ($isGift): ?>
                <span style="color:var(--ink-soft);font-size:12px">void the sale</span>
              <?php elseif ($left < 1): ?>
                <span style="color:var(--ink-soft);font-size:12px">—</span>
              <?php else: ?>
                <input type="number" name="qty[<?= (int)$l['id'] ?>]" value="0"
                       min="0" max="<?= $left ?>" step="1" style="width:90px">
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-top:16px">
      <label class="field"><span>Money goes back as</span>
        <select name="method">
          <option value="cash">Cash</option>
          <option value="card">Card</option>
          <option value="gift">Gift card</option>
          <option value="other">Other</option>
        </select>
      </label>
      <label class="field"><span>Reason</span>
        <input type="text" name="reason" maxlength="255" placeholder="Polish lifted after two days"></label>
    </div>

    <label style="display:block;font-weight:700;margin:6px 0 4px">
      <input type="checkbox" name="with_tip"> Give the tip back too
    </label>
    <p class="sub" style="margin:0 0 16px">
      Left off, the technician keeps the tip and only the service or product comes back.
      Ticked, the tip on each refunded line goes back with it and comes off that
      technician's payout.
    </p>

    <button class="btn btn-red btn-lg" type="submit">Record refund</button>
    <a class="btn btn-light" href="<?= BASE_PATH ?>/pos/sales.php">Cancel</a>
  </form>
  <?php endif; ?>
</div>

<?php if ($previous): ?>
<div class="card">
  <h2>Already refunded on this ticket</h2>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Refund</th><th>When</th><th>By</th><th>Method</th><th>Reason</th><th class="num">Amount</th></tr></thead>
      <tbody>
      <?php foreach ($previous as $r): ?>
        <tr>
          <td><strong><?= e($r['refund_no']) ?></strong></td>
          <td><?= date('m/d/Y g:i A', strtotime($r['created_at'])) ?></td>
          <td><?= e($r['who'] ?: '—') ?></td>
          <td><?= e($r['method']) ?></td>
          <td><?= e($r['reason'] ?: '—') ?></td>
          <td class="num"><strong><?= money($r['total']) ?></strong>
            <?php if ((float)$r['tip'] > 0): ?>
              <div style="font-size:12px;color:var(--ink-soft)">incl. tip <?= money($r['tip']) ?></div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
