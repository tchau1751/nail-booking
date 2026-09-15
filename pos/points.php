<?php
// ============================================================
//  Points — what guests have banked and what it is worth.
//
//  Earned per dollar of services and retail at checkout, spent as
//  tender on the register (🎁 Rewards → Points). A balance is money
//  the salon owes in kind, so it is shown beside its cash value.
// ============================================================
$pageTitle = 'Points';
$activeNav = 'rewards';
$requireRole = 'front_desk';
require_once __DIR__ . '/includes/layout_start.php';
require_once __DIR__ . '/includes/rewards.php';
require_once __DIR__ . '/includes/salon.php';

$tid       = tenantId();
$set       = posSettings();
$minRedeem = (int)($set['points_min_redeem'] ?? 100);
$num       = fn($v) => rtrim(rtrim(sprintf('%.2f', (float)$v), '0'), '.');

$held = fetchOne('SELECT COUNT(*) holders, COALESCE(SUM(points), 0) points
                  FROM pos_clients WHERE tenant_id=? AND is_active=1 AND points > 0', [$tid]);
$month = fetchOne("SELECT COALESCE(SUM(CASE WHEN type='earn' THEN points END), 0) earned,
                          COALESCE(-SUM(CASE WHEN type='redeem' THEN points END), 0) redeemed
                   FROM pos_loyalty_txns
                   WHERE tenant_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [$tid]);
$top = fetchAll('SELECT id, full_name, points FROM pos_clients
                 WHERE tenant_id=? AND is_active=1 AND points > 0
                 ORDER BY points DESC LIMIT 20', [$tid]);
$recent = fetchAll('SELECT t.*, c.full_name FROM pos_loyalty_txns t
                    JOIN pos_clients c ON c.id = t.client_id AND c.tenant_id = t.tenant_id
                    WHERE t.tenant_id=?
                    ORDER BY t.id DESC LIMIT 30', [$tid]);
?>
<?php if (!loyaltyEnabled()): ?>
  <div class="alert alert-err">Points are switched off in
    <a href="<?= BASE_PATH ?>/pos/settings.php#rewards">Settings</a> — no new points are being given.</div>
<?php endif; ?>

<div class="stats">
  <div class="stat"><div class="v"><?= number_format((int)$held['points']) ?></div><div class="k">Points guests hold</div></div>
  <div class="stat"><div class="v"><?= money(pointsToMoney((int)$held['points'])) ?></div><div class="k">What they are worth</div></div>
  <div class="stat"><div class="v"><?= (int)$held['holders'] ?></div><div class="k">Guests with points</div></div>
  <div class="stat"><div class="v" style="color:var(--green)"><?= number_format((int)$month['earned']) ?></div><div class="k">Earned, last 30 days</div></div>
  <div class="stat"><div class="v"><?= number_format((int)$month['redeemed']) ?></div><div class="k">Redeemed, last 30 days</div></div>
</div>

<div class="card">
  <p class="sub" style="margin:0">
    <strong><?= $num($set['points_per_dollar'] ?? 1) ?></strong> per dollar of services and retail, never on tax or tips ·
    each point is worth <strong><?= $num($set['point_value_cents'] ?? 5) ?>¢</strong> ·
    guests redeem from <strong><?= $minRedeem ?></strong> points on the register.
    Change them in <a href="<?= BASE_PATH ?>/pos/settings.php#rewards">Settings</a>.
  </p>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px">
  <div class="card">
    <h2>⭐ Most points</h2>
    <?php if (!$top): ?>
      <div class="empty" style="padding:26px">Nobody has points yet. They are given at checkout to guests with a client record.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Client</th><th class="num">Points</th><th class="num">Worth</th></tr></thead>
          <tbody>
          <?php foreach ($top as $c): ?>
            <tr>
              <td><a href="<?= BASE_PATH ?>/pos/client.php?id=<?= (int)$c['id'] ?>"><?= e($c['full_name']) ?></a>
                <?php if ((int)$c['points'] >= $minRedeem): ?><span class="pill pill-ok">can redeem</span><?php endif; ?></td>
              <td class="num"><?= number_format((int)$c['points']) ?></td>
              <td class="num"><?= money(pointsToMoney((int)$c['points'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>📝 Recent points activity</h2>
    <?php if (!$recent): ?>
      <div class="empty" style="padding:26px">No points have moved yet.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>When</th><th>Client</th><th>What</th><th class="num">Change</th><th class="num">Balance</th><th>Sale</th></tr></thead>
          <tbody>
          <?php foreach ($recent as $t): ?>
            <tr>
              <td><?= date('m/d/y g:i A', strtotime($t['created_at'])) ?></td>
              <td><a href="<?= BASE_PATH ?>/pos/client.php?id=<?= (int)$t['client_id'] ?>"><?= e($t['full_name']) ?></a></td>
              <td><?= e($t['note'] ?: $t['type']) ?></td>
              <td class="num"><?= $t['points'] > 0 ? '+' : '' ?><?= number_format((int)$t['points']) ?></td>
              <td class="num"><?= number_format((int)$t['balance_after']) ?></td>
              <td><?php if ($t['sale_id']): ?><a href="<?= BASE_PATH ?>/pos/receipt.php?id=<?= (int)$t['sale_id'] ?>" target="_blank" rel="noopener">#<?= (int)$t['sale_id'] ?></a><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
