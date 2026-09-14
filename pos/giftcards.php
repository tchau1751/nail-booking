<?php
$pageTitle = 'Gift Cards';
$activeNav = 'giftcards';
$requireRole = 'manager';   // enforced by layout_start before any output
require_once __DIR__ . '/includes/layout_start.php';
require_once __DIR__ . '/includes/rewards.php';
require_once __DIR__ . '/includes/salon.php';

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['action'] ?? '') {
            case 'issue':
                $clientId = null;
                if (trim($_POST['client_phone'] ?? '') !== '') {
                    $cl = clientUpsert(trim($_POST['recipient']), trim($_POST['client_phone']));
                    $clientId = (int)$cl['id'];
                }
                $card = giftCardIssue((float)$_POST['amount'], $clientId, trim($_POST['recipient']),
                                      null, $_POST['expires_on'] ?: null);
                $msg = 'Card ' . $card['code'] . ' issued for ' . money($card['balance']) .
                       '. Sell it on the register to take the money.';
                break;
            case 'reload':
                $bal = giftCardReload((int)$_POST['id'], (float)$_POST['amount']);
                $msg = 'Reloaded — new balance ' . money($bal) . '.';
                break;
            case 'void':
                $card = fetchOne('SELECT * FROM pos_gift_cards WHERE id=? AND tenant_id=?', [(int)$_POST['id'], tenantId()]);
                if (!$card) throw new RuntimeException('Gift card not found.');
                query("UPDATE pos_gift_cards SET status='void', balance=0 WHERE id=? AND tenant_id=?", [(int)$card['id'], tenantId()]);
                giftCardLog((int)$card['id'], 'void', -(float)$card['balance'], 0);
                $msg = 'Card voided.';
                break;
        }
        header('Location: ' . BASE_PATH . '/pos/giftcards.php?m=' . urlencode($msg));
        exit;
    } catch (Throwable $e) { $err = $e->getMessage(); }
}
$msg = $msg ?: ($_GET['m'] ?? '');

$q = trim($_GET['q'] ?? '');
$args = [tenantId()]; $where = 'g.tenant_id=?';
if ($q !== '') { $where .= ' AND (g.code LIKE ? OR g.recipient LIKE ?)'; array_push($args, "%$q%", "%$q%"); }

$cards = fetchAll("SELECT g.*, c.full_name AS client_name FROM pos_gift_cards g
                   LEFT JOIN pos_clients c ON c.id=g.client_id
                   WHERE $where ORDER BY g.id DESC LIMIT 200", $args);
$sum = fetchOne("SELECT COUNT(*) n, COALESCE(SUM(balance),0) bal,
                        COALESCE(SUM(CASE WHEN status='active' THEN 1 END),0) active
                 FROM pos_gift_cards WHERE tenant_id=?", [tenantId()]);
$sold = fetchOne("SELECT COALESCE(SUM(amount),0) v FROM pos_gift_card_txns WHERE tenant_id=? AND type IN ('issue','reload')", [tenantId()]);
$used = fetchOne("SELECT COALESCE(SUM(amount),0) v FROM pos_gift_card_txns WHERE tenant_id=? AND type='redeem'", [tenantId()]);
$detail = fetchOne('SELECT * FROM pos_gift_cards WHERE id=? AND tenant_id=?', [(int)($_GET['card'] ?? 0), tenantId()]);
$txns = $detail ? fetchAll('SELECT * FROM pos_gift_card_txns WHERE tenant_id=? AND gift_card_id=? ORDER BY id DESC', [tenantId(), $detail['id']]) : [];
?>
<?php if ($msg): ?><div class="alert alert-ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>

<div class="stats">
  <div class="stat"><div class="v"><?= (int)$sum['active'] ?></div><div class="k">Active cards</div></div>
  <div class="stat"><div class="v"><?= money(giftCardLiability()) ?></div><div class="k">Outstanding balance (liability)</div></div>
  <div class="stat"><div class="v"><?= money($sold['v']) ?></div><div class="k">Loaded all time</div></div>
  <div class="stat"><div class="v"><?= money($used['v']) ?></div><div class="k">Redeemed all time</div></div>
</div>

<div class="card">
  <h2>🎁 Issue a card</h2>
  <p class="sub">Use this for comps and replacements. For a card the guest is <em>paying</em> for, add it on the
     register instead so the money lands in the day's takings.</p>
  <form method="post" class="toolbar" style="margin:0">
    <input type="hidden" name="action" value="issue">
    <label class="field"><span>Amount</span><input type="number" step="0.01" name="amount" required placeholder="50.00"></label>
    <label class="field"><span>Recipient</span><input type="text" name="recipient" placeholder="Optional"></label>
    <label class="field"><span>Their phone</span><input type="text" name="client_phone" placeholder="Optional — links to a client"></label>
    <label class="field"><span>Expires</span><input type="date" name="expires_on"></label>
    <button class="btn btn-green" type="submit">Issue card</button>
  </form>
</div>

<?php if ($detail): ?>
<div class="card">
  <h2>Card <?= e($detail['code']) ?></h2>
  <p class="sub">Balance <?= money($detail['balance']) ?> of <?= money($detail['initial_amount']) ?>
     · <?= e($detail['status']) ?><?= $detail['expires_on'] ? ' · expires ' . date('m/d/Y', strtotime($detail['expires_on'])) : '' ?></p>
  <div class="table-wrap">
    <table>
      <thead><tr><th>When</th><th>Type</th><th class="num">Amount</th><th class="num">Balance after</th><th>Sale</th></tr></thead>
      <tbody>
      <?php foreach ($txns as $t): ?>
        <tr><td><?= date('m/d/Y g:i A', strtotime($t['created_at'])) ?></td>
            <td><?= e($t['type']) ?></td>
            <td class="num"><?= money($t['amount']) ?></td>
            <td class="num"><?= money($t['balance_after']) ?></td>
            <td><?php if ($t['sale_id']): ?><a href="<?= BASE_PATH ?>/pos/receipt.php?id=<?= (int)$t['sale_id'] ?>" target="_blank" rel="noopener">#<?= (int)$t['sale_id'] ?></a><?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div style="margin-top:14px">
    <a class="btn btn-light" href="<?= BASE_PATH ?>/pos/giftcards.php">Close</a>
  </div>
</div>
<?php endif; ?>

<form class="toolbar" method="get">
  <label class="field" style="flex:1;min-width:220px"><span>Search</span>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Card code or recipient"></label>
  <button class="btn" type="submit">Search</button>
  <?php if ($q): ?><a class="btn btn-light" href="<?= BASE_PATH ?>/pos/giftcards.php">Clear</a><?php endif; ?>
</form>

<div class="table-wrap">
  <table>
    <thead><tr><th>Code</th><th>For</th><th class="num">Loaded</th><th class="num">Balance</th>
               <th>Status</th><th>Issued</th><th></th></tr></thead>
    <tbody>
    <?php if (!$cards): ?>
      <tr><td colspan="7" style="text-align:center;color:var(--ink-soft);padding:40px">No gift cards yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($cards as $c): ?>
      <tr>
        <td style="font-family:monospace;font-weight:700"><?= e($c['code']) ?></td>
        <td><?= e($c['recipient'] ?: ($c['client_name'] ?: '—')) ?></td>
        <td class="num"><?= money($c['initial_amount']) ?></td>
        <td class="num"><strong><?= money($c['balance']) ?></strong></td>
        <td><span class="pill <?= $c['status'] === 'active' ? 'pill-ok' : 'pill-void' ?>"><?= e($c['status']) ?></span></td>
        <td><?= date('m/d/Y', strtotime($c['created_at'])) ?></td>
        <td style="white-space:nowrap">
          <a class="btn btn-light btn-sm" href="?card=<?= (int)$c['id'] ?>">History</a>
          <?php if ($c['status'] !== 'void'): ?>
            <form method="post" style="display:inline-flex;gap:4px;align-items:center">
              <input type="hidden" name="action" value="reload">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <input type="number" step="0.01" name="amount" placeholder="25" style="width:80px;min-height:38px">
              <button class="btn btn-light btn-sm" type="submit">Reload</button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('Void card <?= e($c['code']) ?>? The remaining balance is lost.')">
              <input type="hidden" name="action" value="void">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
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
