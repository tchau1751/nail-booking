<?php
// ============================================================
//  Stamp cards — the punch card, kept honestly.
//
//  One stamp per visit. Fill a card and a reward is banked, but
//  it stays pending until someone actually hands it over, so an
//  unclaimed reward is never silently spent.
// ============================================================
$pageTitle = 'Stamp Cards';
$activeNav = 'rewards';   // Rewards tab → Stamp cards
$requireRole = 'front_desk';
require_once __DIR__ . '/includes/layout_start.php';
require_once __DIR__ . '/includes/rewards.php';
require_once __DIR__ . '/includes/salon.php';

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = (int)$_POST['client_id'];
        switch ($_POST['action'] ?? '') {
            case 'redeem':
                stampRedeemReward($id);
                $msg = 'Reward handed over. Their card starts again.';
                break;
            case 'give':
                stampAward($id, null, trim($_POST['note']) ?: 'Added by staff');
                $msg = 'Stamp added.';
                break;
            case 'take':
                stampRevoke($id, null, trim($_POST['note']) ?: 'Removed by staff');
                $msg = 'Stamp removed.';
                break;
        }
        header('Location: ' . BASE_PATH . '/pos/stamps.php?m=' . urlencode($msg));
        exit;
    } catch (Throwable $e) { $err = $e->getMessage(); }
}
$msg = $msg ?: ($_GET['m'] ?? '');

$per    = stampsPerCard();
$reward = posSettings()['stamp_reward'] ?? 'Free service';

$tid = tenantId();
$stats = fetchOne('SELECT COUNT(*) clients,
                          COALESCE(SUM(stamps),0) stamps_total,
                          COALESCE(AVG(stamps),0) avg_stamps,
                          COALESCE(SUM(rewards_earned - rewards_redeemed),0) pending,
                          COALESCE(SUM(rewards_redeemed),0) given
                   FROM pos_clients WHERE tenant_id=? AND is_active=1', [$tid]);

// "Close" means within two stamps of a full card.
$close = fetchAll('SELECT * FROM pos_clients
                   WHERE tenant_id=? AND is_active=1 AND stamps > 0 AND (stamps % ?) >= ?
                   ORDER BY (stamps % ?) DESC, last_visit DESC LIMIT 25',
                  [$tid, $per, max(1, $per - 2), $per]);

$ready = fetchAll('SELECT * FROM pos_clients
                   WHERE tenant_id=? AND is_active=1 AND rewards_earned > rewards_redeemed
                   ORDER BY last_visit DESC LIMIT 25', [$tid]);

$top = fetchAll('SELECT * FROM pos_clients WHERE tenant_id=? AND is_active=1 AND stamps > 0
                 ORDER BY stamps DESC LIMIT 15', [$tid]);

$recent = fetchAll('SELECT t.*, c.full_name FROM pos_stamp_txns t
                    JOIN pos_clients c ON c.id = t.client_id
                    WHERE t.tenant_id=?
                    ORDER BY t.id DESC LIMIT 30', [$tid]);

// How far round the current card everyone is.
$dist = array_fill(0, $per + 1, 0);
foreach (fetchAll('SELECT stamps FROM pos_clients WHERE tenant_id=? AND is_active=1', [$tid]) as $r) {
    $dist[(int)$r['stamps'] % $per]++;
}

function card_dots(int $on, int $per): string {
    $h = '<div class="stampdots">';
    for ($i = 0; $i < $per; $i++) {
        $h .= '<span class="sdot' . ($i < $on ? ' on' : '') . '"></span>';
    }
    return $h . '</div>';
}
?>
<style>
  .stampdots{display:flex;gap:5px;flex-wrap:wrap;margin-top:6px;}
  .sdot{width:18px;height:18px;border-radius:50%;border:2px solid var(--gold);display:inline-block;}
  .sdot.on{background:var(--gold);}
  .cardrow{display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid var(--line);}
  .cardrow:last-child{border-bottom:none;}
  .cardrow .who{flex:1;min-width:0;}
  .barwrap{height:10px;background:#efe9e5;border-radius:5px;overflow:hidden;width:120px;}
  .barwrap i{display:block;height:100%;background:var(--green);}
</style>

<?php if ($msg): ?><div class="alert alert-ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>

<?php if (!stampsEnabled()): ?>
  <div class="alert alert-err">Stamp cards are switched off in
    <a href="<?= BASE_PATH ?>/pos/settings.php">Settings</a> — no new stamps are being given.</div>
<?php endif; ?>

<div class="stats">
  <div class="stat"><div class="v"><?= (int)$stats['clients'] ?></div><div class="k">Clients</div></div>
  <div class="stat"><div class="v" style="color:var(--green)"><?= (int)$stats['pending'] ?></div>
       <div class="k">Rewards waiting to be claimed</div></div>
  <div class="stat"><div class="v"><?= count($close) ?></div><div class="k">Within 2 stamps of a card</div></div>
  <div class="stat"><div class="v"><?= number_format((float)$stats['avg_stamps'], 1) ?></div><div class="k">Average stamps</div></div>
  <div class="stat"><div class="v"><?= (int)$stats['given'] ?></div><div class="k">Rewards given all time</div></div>
</div>

<div class="card">
  <p class="sub" style="margin:0">
    One stamp per visit. <strong><?= $per ?> stamps</strong> earns <strong><?= e($reward) ?></strong>.
    Change both in <a href="<?= BASE_PATH ?>/pos/settings.php">Settings</a>.
  </p>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px">

  <div class="card">
    <h2>🎉 Ready to claim</h2>
    <p class="sub">Full cards. Hand over the reward, then press Redeem so the card starts again.</p>
    <?php if (!$ready): ?><div class="empty" style="padding:26px">Nobody has a full card yet.</div><?php endif; ?>
    <?php foreach ($ready as $c): $card = stampCard($c); ?>
      <div class="cardrow">
        <div class="who">
          <a href="<?= BASE_PATH ?>/pos/client.php?id=<?= (int)$c['id'] ?>"><strong><?= e($c['full_name']) ?></strong></a>
          <div style="font-size:12px;color:var(--ink-soft)"><?= e(formatPhone($c['phone'])) ?>
            · <?= (int)$card['pending'] ?> reward<?= $card['pending'] === 1 ? '' : 's' ?> waiting</div>
        </div>
        <form method="post" onsubmit="return confirm('Give <?= e($c['full_name']) ?> their <?= e($reward) ?>?')">
          <input type="hidden" name="action" value="redeem">
          <input type="hidden" name="client_id" value="<?= (int)$c['id'] ?>">
          <button class="btn btn-green btn-sm" type="submit">Redeem</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h2>⏳ Nearly there</h2>
    <p class="sub">Within two stamps of a full card — worth a mention at the desk.</p>
    <?php if (!$close): ?><div class="empty" style="padding:26px">Nobody is close yet.</div><?php endif; ?>
    <?php foreach ($close as $c): $card = stampCard($c); ?>
      <div class="cardrow">
        <div class="who">
          <a href="<?= BASE_PATH ?>/pos/client.php?id=<?= (int)$c['id'] ?>"><strong><?= e($c['full_name']) ?></strong></a>
          <div style="font-size:12px;color:var(--ink-soft)"><?= $card['to_next'] ?> more to go</div>
          <?= card_dots($card['on_card'], $per) ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h2>⭐ Most stamps</h2>
    <?php if (!$top): ?><div class="empty" style="padding:26px">No stamps given yet.</div><?php endif; ?>
    <?php foreach ($top as $c): $card = stampCard($c); ?>
      <div class="cardrow">
        <div class="who">
          <a href="<?= BASE_PATH ?>/pos/client.php?id=<?= (int)$c['id'] ?>"><strong><?= e($c['full_name']) ?></strong></a>
          <div style="font-size:12px;color:var(--ink-soft)"><?= (int)$c['stamps'] ?> all time
            · <?= (int)$c['total_visits'] ?> visits</div>
        </div>
        <div class="barwrap"><i style="width:<?= (int)round($card['on_card'] / $per * 100) ?>%"></i></div>
        <strong style="width:52px;text-align:right"><?= $card['on_card'] ?>/<?= $per ?></strong>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h2>📊 Where everyone is</h2>
    <p class="sub">How far round their current card.</p>
    <table>
      <?php foreach ($dist as $n => $count): if (!$count) continue; ?>
        <tr>
          <td style="width:70px"><?= $n ?> stamp<?= $n === 1 ? '' : 's' ?></td>
          <td><div class="barwrap" style="width:100%"><i style="width:<?= (int)round($count / max(1, (int)$stats['clients']) * 100) ?>%"></i></div></td>
          <td class="num" style="width:46px"><?= $count ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!array_sum($dist)): ?><tr><td style="color:var(--ink-soft)">No clients yet.</td></tr><?php endif; ?>
    </table>
  </div>
</div>

<div class="card">
  <h2>📝 Recent stamp activity</h2>
  <div class="table-wrap">
    <table>
      <thead><tr><th>When</th><th>Client</th><th>What</th><th class="num">Change</th><th class="num">Balance</th><th>Sale</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $t): ?>
        <tr>
          <td><?= date('m/d/y g:i A', strtotime($t['created_at'])) ?></td>
          <td><a href="<?= BASE_PATH ?>/pos/client.php?id=<?= (int)$t['client_id'] ?>"><?= e($t['full_name']) ?></a></td>
          <td><?= e($t['note'] ?: $t['type']) ?></td>
          <td class="num"><?= $t['stamps'] > 0 ? '+' : '' ?><?= (int)$t['stamps'] ?></td>
          <td class="num"><?= (int)$t['balance_after'] ?></td>
          <td><?php if ($t['sale_id']): ?><a href="<?= BASE_PATH ?>/pos/receipt.php?id=<?= (int)$t['sale_id'] ?>" target="_blank" rel="noopener">#<?= (int)$t['sale_id'] ?></a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$recent): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--ink-soft);padding:36px">
          No stamps yet. They're given automatically when a guest with a client record checks out.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
