<?php
// 80mm thermal-style receipt. Opens ready to print.
require_once __DIR__ . '/includes/pos.php';
require_once __DIR__ . '/includes/receipt_text.php';
requireTillLogin();

$id   = (int)($_GET['id'] ?? 0);
$sale = fetchOne('SELECT s.*, t.name AS tech_name, u.name AS cashier_name
                  FROM pos_sales s
                  LEFT JOIN technicians t ON t.id = s.technician_id
                  LEFT JOIN admin_users u ON u.id = s.cashier_id
                  WHERE s.id = ?', [$id]);
if (!$sale) { http_response_code(404); exit('Sale not found.'); }
$items    = fetchAll('SELECT i.*, t.name AS item_tech
                     FROM pos_sale_items i
                     LEFT JOIN technicians t ON t.id = i.technician_id
                     WHERE i.sale_id=? ORDER BY i.id', [$id]);
$payments = fetchAll('SELECT * FROM pos_payments WHERE sale_id=? ORDER BY id', [$id]);
$cards    = fetchAll('SELECT * FROM pos_gift_cards WHERE issued_sale_id=? ORDER BY id', [$id]);
$client   = $sale['client_id'] ? fetchOne('SELECT full_name, points FROM pos_clients WHERE id=?', [$sale['client_id']]) : null;
$set      = posSettings();
$biz      = settings();
// Plain text for the Star TSP650II, sent via the tablet's till app.
$starText = receiptText($sale, $items, $payments, $cards, $client, $set, $biz);
$starAuto = ($_GET['star'] ?? '') === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Receipt <?= e($sale['sale_no']) ?></title>
<style>
  body{font:13px/1.45 "Courier New",monospace;background:#e9e5e2;margin:0;padding:20px;}
  .paper{width:200px;margin:0 auto;background:#fff;padding:18px 16px;box-shadow:0 2px 10px rgba(0,0,0,.15);}
  .c{text-align:center;} .r{text-align:right;}
  h1{font-size:16px;margin:0 0 4px;letter-spacing:.05em;}
  .muted{color:#555;font-size:11px;}
  hr{border:none;border-top:1px dashed #999;margin:10px 0;}
  table{width:100%;border-collapse:collapse;}
  td{padding:2px 0;vertical-align:top;}
  .tot td{padding:2px 0;} .grand td{font-size:15px;font-weight:bold;padding-top:6px;}
  .void{color:#b00;text-align:center;font-weight:bold;border:2px solid #b00;padding:4px;margin:8px 0;}
  .actions{width:180px;margin:12px auto 0;display:flex;gap:8px;}
  .actions a,.actions button{flex:1;padding:12px;border:none;border-radius:10px;background:#3a2a24;color:#fff;
    font:600 14px sans-serif;text-align:center;text-decoration:none;cursor:pointer;}
  @media print{body{background:#fff;padding:0;} .paper{box-shadow:none;width:auto;} .actions{display:none;}}
</style>
</head>
<body>
<div class="paper">
  <div class="c">
    <h1><?= e($set['receipt_header'] ?: ($biz['business_name'] ?? 'Nail Salon')) ?></h1>
    <?php if (!empty($biz['business_address'])): ?><div class="muted"><?= e($biz['business_address']) ?></div><?php endif; ?>
    <?php if (!empty($biz['business_phone'])): ?><div class="muted"><?= e($biz['business_phone']) ?></div><?php endif; ?>
  </div>
  <hr>
  <?php if ($sale['status'] !== 'completed'): ?>
    <div class="void">*** <?= strtoupper($sale['status']) ?> ***</div>
  <?php endif; ?>
  <table>
    <tr><td>Sale</td><td class="r"><?= e($sale['sale_no']) ?></td></tr>
    <tr><td>Date</td><td class="r"><?= date('m/d/Y g:i A', strtotime($sale['created_at'])) ?></td></tr>
    <?php if ($sale['customer_name']): ?><tr><td>Guest</td><td class="r"><?= e($sale['customer_name']) ?></td></tr><?php endif; ?>
    <?php if ($sale['cashier_name']): ?><tr><td>Cashier</td><td class="r"><?= e($sale['cashier_name']) ?></td></tr><?php endif; ?>
  </table>
  <hr>
  <table>
    <?php foreach ($items as $it): ?>
      <tr>
        <td><?= e($it['name']) ?><?= $it['qty'] > 1 ? ' ×' . (int)$it['qty'] : '' ?>
          <?php if ($it['item_tech']): ?><div class="muted"><?= e($it['item_tech']) ?></div><?php endif; ?>
          <?php if ($it['discount'] > 0): ?><div class="muted">discount −<?= money($it['discount']) ?></div><?php endif; ?>
        </td>
        <td class="r"><?= money($it['line_total']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <hr>
  <table class="tot">
    <tr><td>Subtotal</td><td class="r"><?= money($sale['subtotal']) ?></td></tr>
    <?php if ($sale['discount_total'] > 0): ?><tr><td>Discount</td><td class="r">−<?= money($sale['discount_total']) ?></td></tr><?php endif; ?>
    <?php if ($sale['tax_total'] > 0): ?><tr><td><?= e($set['tax_label']) ?></td><td class="r"><?= money($sale['tax_total']) ?></td></tr><?php endif; ?>
    <?php if ($sale['tip_total'] > 0): ?><tr><td>Tip</td><td class="r"><?= money($sale['tip_total']) ?></td></tr><?php endif; ?>
    <tr class="grand"><td>TOTAL</td><td class="r"><?= money($sale['grand_total']) ?></td></tr>
  </table>
  <hr>
  <table>
    <?php foreach ($payments as $p): ?>
      <tr><td><?= ucfirst($p['method']) ?><?= $p['reference'] ? ' (' . e($p['reference']) . ')' : '' ?></td>
          <td class="r"><?= money($p['amount']) ?></td></tr>
    <?php endforeach; ?>
    <?php if ($sale['change_due'] > 0): ?>
      <tr><td>Change</td><td class="r"><?= money($sale['change_due']) ?></td></tr>
    <?php endif; ?>
  </table>
  <?php if ($cards): ?>
    <hr>
    <div class="c" style="font-weight:bold">GIFT CARD<?= count($cards) > 1 ? 'S' : '' ?></div>
    <?php foreach ($cards as $gc): ?>
      <div class="c" style="margin:6px 0">
        <div style="font-size:16px;letter-spacing:.08em;font-weight:bold"><?= e($gc['code']) ?></div>
        <div class="muted"><?= money($gc['initial_amount']) ?><?= $gc['recipient'] ? ' for ' . e($gc['recipient']) : '' ?></div>
      </div>
    <?php endforeach; ?>
    <div class="c muted">Keep this receipt - the code is the card.</div>
  <?php endif; ?>

  <?php if ($client && ((int)$sale['points_earned'] > 0 || (int)$sale['points_redeemed'] > 0)): ?>
    <hr>
    <table>
      <?php if ((int)$sale['points_redeemed'] > 0): ?>
        <tr><td>Points redeemed</td><td class="r">-<?= (int)$sale['points_redeemed'] ?></td></tr>
      <?php endif; ?>
      <?php if ((int)$sale['points_earned'] > 0): ?>
        <tr><td>Points earned</td><td class="r">+<?= (int)$sale['points_earned'] ?></td></tr>
      <?php endif; ?>
      <tr><td>Points balance</td><td class="r"><?= (int)$client['points'] ?></td></tr>
    </table>
  <?php endif; ?>

  <hr>
  <div class="c muted"><?= e($set['receipt_footer']) ?></div>
  <?php if ($set['owner_name'] || $set['license_no']): ?>
    <div class="c muted" style="margin-top:6px">
      <?php if ($set['owner_name']): ?><div><?= e($set['owner_name']) ?>, Owner</div><?php endif; ?>
      <?php if ($set['owner_phone']): ?><div><?= e($set['owner_phone']) ?></div><?php endif; ?>
      <?php if ($set['license_no']): ?><div>Licence <?= e($set['license_no']) ?></div><?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<div class="actions">
  <button type="button" onclick="printToStar()">🖨 Star printer</button>
  <button type="button" onclick="window.print()">Print dialog</button>
  <a href="<?= BASE_PATH ?>/pos/">Back to register</a>
</div>

<script>
// A browser cannot open a Bluetooth port, so the receipt is handed to the
// Diamond Nails Till app on this tablet, which owns the link to the TSP650II.
const STAR_TEXT = <?= json_encode($starText, JSON_UNESCAPED_SLASHES) ?>;
function printToStar() {
  window.location.href = 'dnstill://print?drawer=1&text=' + encodeURIComponent(STAR_TEXT);
}
<?php if ($starAuto): ?>
printToStar();   // reached as receipt.php?id=...&star=1
<?php endif; ?>
</script>
</body>
</html>
