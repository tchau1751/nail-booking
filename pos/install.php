<?php
// One-click installer: creates the POS tables from schema_pos.sql.
$pageTitle = 'Install POS';
$activeNav = '';
$requireRole = 'owner';   // it changes the database itself
require_once __DIR__ . '/includes/layout_start.php';

require_once __DIR__ . '/includes/migrate.php';

$done = false; $error = ''; $log = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try { $log = migratePos(); $done = true; }
    catch (Throwable $e) { $error = $e->getMessage(); }
}
$installed = posInstalled();
?>
<div class="card" style="max-width:680px;margin:0 auto">
  <h2>💎 POS setup</h2>
  <p class="sub">Creates the point-of-sale tables inside your existing <code>nail_booking</code> database. Safe to run more than once — nothing existing is dropped.</p>

  <?php if ($error): ?><div class="alert alert-err"><?= e($error) ?></div><?php endif; ?>
  <?php if ($done): ?>
    <div class="alert alert-ok">Database is up to date. You're ready to sell.</div>
    <?php if ($log): ?>
      <details style="margin-bottom:16px"><summary style="cursor:pointer;font-weight:700">What changed (<?= count($log) ?>)</summary>
        <ul style="margin:10px 0 0 20px;line-height:1.7;font-size:14px;color:var(--ink-soft)">
          <?php foreach ($log as $l): ?><li><?= e($l) ?></li><?php endforeach; ?>
        </ul>
      </details>
    <?php endif; ?>
  <?php endif; ?>

  <ul style="margin:0 0 18px 20px;line-height:1.9">
    <li>pos_products — retail stock with barcodes</li>
    <li>pos_sales / pos_sale_items / pos_payments — the ticket history</li>
    <li>pos_clients / notes / consents — customer records</li>
    <li>pos_checkins / pos_tech_shifts — the walk-in queue and turns</li>
    <li>pos_gift_cards / pos_loyalty_txns — gift cards and award points</li>
    <li>pos_expenses / pos_campaigns / pos_feedback — P&amp;L, SMS, reviews</li>
    <li>pos_cash_movements, pos_settings — drawer and register config</li>
  </ul>

  <?php if ($installed): ?>
    <a class="btn btn-green btn-lg" href="<?= BASE_PATH ?>/pos/">Open the register →</a>
    <form method="post" style="display:inline-block;margin-left:10px">
      <button class="btn btn-light btn-lg" type="submit">Re-run setup</button>
    </form>
  <?php else: ?>
    <form method="post"><button class="btn btn-green btn-lg" type="submit">Create POS tables</button></form>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
