<?php
$pageTitle = 'Client';
$activeNav = 'clients';
require_once __DIR__ . '/includes/layout_start.php';
require_once __DIR__ . '/includes/salon.php';
require_once __DIR__ . '/includes/rewards.php';

$id = (int)($_GET['id'] ?? 0);
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['action'] ?? '') {
            case 'save':
                query('UPDATE pos_clients SET full_name=?, phone=?, email=?, birthday=?,
                       preferred_tech_id=?, marketing_opt_in=? WHERE id=?', [
                    trim($_POST['full_name']), normalisePhone($_POST['phone']), trim($_POST['email']),
                    $_POST['birthday'] ?: null, ((int)$_POST['preferred_tech_id']) ?: null,
                    isset($_POST['marketing_opt_in']) ? 1 : 0, $id,
                ]);
                $msg = 'Client saved.';
                break;
            case 'note':
                if (trim($_POST['note']) !== '') {
                    query('INSERT INTO pos_client_notes (client_id, note, is_pinned, admin_id) VALUES (?,?,?,?)',
                          [$id, trim($_POST['note']), isset($_POST['is_pinned']) ? 1 : 0, $admin['id'] ?? null]);
                    $msg = 'Note added.';
                }
                break;
            case 'note_delete':
                query('DELETE FROM pos_client_notes WHERE id=? AND client_id=?', [(int)$_POST['note_id'], $id]);
                $msg = 'Note deleted.';
                break;
            case 'stamp_redeem':
                stampRedeemReward($id);
                $msg = 'Reward handed over — their card starts again.';
                break;
            case 'stamp_adjust':
                $d = (int)$_POST['delta'];
                if ($d > 0)      stampAward($id, null, trim($_POST['reason']) ?: 'Added by staff');
                elseif ($d < 0)  stampRevoke($id, null, trim($_POST['reason']) ?: 'Removed by staff');
                $msg = 'Stamp card updated.';
                break;
            case 'points':
                $delta = (int)$_POST['points'];
                if ($delta !== 0) {
                    pointsLog($id, 'adjust', $delta, trim($_POST['reason']) ?: 'Manual adjustment');
                    $msg = 'Points adjusted.';
                }
                break;
        }
        header('Location: ' . BASE_PATH . '/pos/client.php?id=' . $id . '&m=' . urlencode($msg));
        exit;
    } catch (Throwable $e) { $err = $e->getMessage(); }
}
$msg = $msg ?: ($_GET['m'] ?? '');

$client = fetchOne('SELECT * FROM pos_clients WHERE id=?', [$id]);
if (!$client) { echo '<div class="alert alert-err">Client not found.</div>';
                require __DIR__ . '/includes/layout_end.php'; exit; }

$pageTitle = $client['full_name'];
$techs   = fetchAll('SELECT id,name FROM technicians WHERE is_active=1 ORDER BY display_order, name');
$notes   = clientNotes($id);
$visits  = fetchAll("SELECT s.*, t.name AS tech_name FROM pos_sales s
                     LEFT JOIN technicians t ON t.id=s.technician_id
                     WHERE s.client_id=? ORDER BY s.id DESC LIMIT 50", [$id]);
$points  = fetchAll('SELECT * FROM pos_loyalty_txns WHERE client_id=? ORDER BY id DESC LIMIT 30', [$id]);
$cards   = fetchAll('SELECT * FROM pos_gift_cards WHERE client_id=? ORDER BY id DESC', [$id]);
$consents = fetchAll('SELECT * FROM pos_consents WHERE client_id=? ORDER BY signed_at DESC', [$id]);
$templates = fetchAll('SELECT * FROM pos_consent_templates WHERE is_active=1 ORDER BY id');
$favourite = fetchOne("SELECT i.name, COUNT(*) n FROM pos_sale_items i
                       JOIN pos_sales s ON s.id=i.sale_id
                       WHERE s.client_id=? AND i.item_type='service' AND s.status='completed'
                       GROUP BY i.name ORDER BY n DESC LIMIT 1", [$id]);
?>
<?php if ($msg): ?><div class="alert alert-ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>

<div class="toolbar">
  <a class="btn btn-light" href="<?= BASE_PATH ?>/pos/clients.php">← All clients</a>
  <a class="btn btn-blue" href="<?= BASE_PATH ?>/pos/index.php?client=<?= $id ?>">💅 Start a ticket</a>
  <a class="btn btn-light" href="<?= BASE_PATH ?>/pos/consent.php?client=<?= $id ?>">✍️ Sign a form</a>
</div>

<div class="stats">
  <div class="stat"><div class="v"><?= (int)$client['total_visits'] ?></div><div class="k">Visits</div></div>
  <div class="stat"><div class="v"><?= money($client['total_spend']) ?></div><div class="k">Lifetime spend</div></div>
  <div class="stat"><div class="v"><?= number_format((int)$client['points']) ?></div>
       <div class="k">Points · worth <?= money(pointsToMoney((int)$client['points'])) ?></div></div>
  <div class="stat"><div class="v"><?= stampCard($client)['on_card'] ?>/<?= stampsPerCard() ?></div><div class="k">Stamp card</div></div>
  <div class="stat"><div class="v" style="font-size:20px"><?= e($favourite['name'] ?? '—') ?></div><div class="k">Favourite service</div></div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px">

  <div class="card">
    <h2>Details</h2>
    <form method="post">
      <input type="hidden" name="action" value="save">
      <label class="field"><span>Name</span><input type="text" name="full_name" value="<?= e($client['full_name']) ?>" required></label>
      <label class="field"><span>Phone</span><input type="text" name="phone" value="<?= e(formatPhone($client['phone'])) ?>" required></label>
      <label class="field"><span>Email</span><input type="text" name="email" value="<?= e($client['email']) ?>"></label>
      <label class="field"><span>Birthday</span><input type="date" name="birthday" value="<?= e($client['birthday']) ?>"></label>
      <label class="field"><span>Preferred technician</span>
        <select name="preferred_tech_id">
          <option value="">— none —</option>
          <?php foreach ($techs as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= $client['preferred_tech_id'] == $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
          <?php endforeach; ?>
        </select></label>
      <label style="display:block;font-weight:700;margin-bottom:14px">
        <input type="checkbox" name="marketing_opt_in" <?= $client['marketing_opt_in'] ? 'checked' : '' ?>> Happy to receive promotional texts
      </label>
      <button class="btn btn-green" type="submit">Save</button>
    </form>
  </div>

  <div class="card">
    <h2>📝 Notes</h2>
    <p class="sub">What she likes, what to avoid, allergies, colour formulas.</p>
    <form method="post" style="margin-bottom:16px">
      <input type="hidden" name="action" value="note">
      <label class="field"><span>New note</span><textarea name="note" rows="3" placeholder="e.g. Allergic to acetone soak — use foil wraps"></textarea></label>
      <label style="display:block;font-weight:700;margin-bottom:12px"><input type="checkbox" name="is_pinned"> Pin to the top</label>
      <button class="btn btn-green btn-sm" type="submit">Add note</button>
    </form>
    <?php if (!$notes): ?><div class="empty" style="padding:20px">No notes yet.</div><?php endif; ?>
    <?php foreach ($notes as $n): ?>
      <div style="border-left:4px solid <?= $n['is_pinned'] ? 'var(--gold)' : 'var(--line)' ?>;
                  padding:10px 12px;margin-bottom:10px;background:#fbf9f8;border-radius:0 10px 10px 0">
        <div style="white-space:pre-wrap"><?= $n['is_pinned'] ? '📌 ' : '' ?><?= e($n['note']) ?></div>
        <div style="font-size:12px;color:var(--ink-soft);margin-top:6px;display:flex;justify-content:space-between;align-items:center">
          <span><?= e($n['who'] ?: 'Staff') ?> · <?= date('m/d/Y', strtotime($n['created_at'])) ?></span>
          <form method="post" onsubmit="return confirm('Delete this note?')">
            <input type="hidden" name="action" value="note_delete">
            <input type="hidden" name="note_id" value="<?= (int)$n['id'] ?>">
            <button class="btn btn-light btn-sm" type="submit">Delete</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h2>🎫 Stamp card</h2>
    <?php $card = stampCard($client); ?>
    <p class="sub">One stamp per visit. <?= $card['per_card'] ?> fills a card and earns
       <strong><?= e($card['reward']) ?></strong>.</p>

    <?php if ($card['has_reward']): ?>
      <div style="background:rgba(31,157,85,.1);border:1px solid var(--green);border-radius:12px;
                  padding:14px 16px;margin-bottom:14px;font-weight:700">
        🎉 <?= (int)$card['pending'] ?> reward<?= $card['pending'] === 1 ? '' : 's' ?> waiting to be claimed
        <form method="post" style="margin-top:10px"
              onsubmit="return confirm('Give <?= e($client['full_name']) ?> their <?= e($card['reward']) ?>?')">
          <input type="hidden" name="action" value="stamp_redeem">
          <button class="btn btn-green btn-sm" type="submit">Hand it over</button>
        </form>
      </div>
    <?php endif; ?>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
      <?php for ($i = 0; $i < $card['per_card']; $i++): ?>
        <span style="width:34px;height:34px;border-radius:50%;border:2px solid var(--gold);
                     display:flex;align-items:center;justify-content:center;font-weight:800;
                     <?= $i < $card['on_card'] ? 'background:var(--gold);color:#fff' : 'color:var(--gold)' ?>">
          <?= $i < $card['on_card'] ? '✓' : ($i + 1) ?>
        </span>
      <?php endfor; ?>
    </div>
    <p class="sub"><?= $card['on_card'] ?> of <?= $card['per_card'] ?> on this card
       · <?= (int)$client['stamps'] ?> stamps all time
       · <?= $card['to_next'] ?: 0 ?> more for the next reward</p>

    <form method="post" class="toolbar" style="margin:0">
      <input type="hidden" name="action" value="stamp_adjust">
      <label class="field"><span>Adjust</span>
        <select name="delta"><option value="1">Add a stamp</option><option value="-1">Remove a stamp</option></select></label>
      <label class="field" style="flex:1"><span>Reason</span><input type="text" name="reason" placeholder="Optional"></label>
      <button class="btn btn-light" type="submit">Apply</button>
    </form>
    <div class="table-wrap" style="margin-top:12px">
      <table>
        <thead><tr><th>When</th><th>What</th><th class="num">Change</th><th class="num">Balance</th></tr></thead>
        <tbody>
        <?php foreach (fetchAll('SELECT * FROM pos_stamp_txns WHERE client_id=? ORDER BY id DESC LIMIT 15', [$id]) as $st): ?>
          <tr><td><?= date('m/d/y', strtotime($st['created_at'])) ?></td>
              <td><?= e($st['note'] ?: $st['type']) ?></td>
              <td class="num"><?= $st['stamps'] > 0 ? '+' : '' ?><?= (int)$st['stamps'] ?></td>
              <td class="num"><?= (int)$st['balance_after'] ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <h2>⭐ Points</h2>
    <form method="post" class="toolbar" style="margin-bottom:14px">
      <input type="hidden" name="action" value="points">
      <label class="field"><span>Adjust by</span><input type="number" name="points" placeholder="e.g. 50 or -50" required></label>
      <label class="field" style="flex:1"><span>Reason</span><input type="text" name="reason" placeholder="Goodwill, correction…"></label>
      <button class="btn" type="submit">Apply</button>
    </form>
    <div class="table-wrap">
      <table>
        <thead><tr><th>When</th><th>Type</th><th class="num">Points</th><th class="num">Balance</th></tr></thead>
        <tbody>
        <?php foreach ($points as $p): ?>
          <tr><td><?= date('m/d/y', strtotime($p['created_at'])) ?></td>
              <td><?= e($p['type']) ?><?php if ($p['note']): ?><div style="font-size:12px;color:var(--ink-soft)"><?= e($p['note']) ?></div><?php endif; ?></td>
              <td class="num"><?= $p['points'] > 0 ? '+' : '' ?><?= (int)$p['points'] ?></td>
              <td class="num"><?= (int)$p['balance_after'] ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$points): ?><tr><td colspan="4" style="color:var(--ink-soft)">No points activity yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <h2>🧾 Visit history</h2>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Date</th><th>Tech</th><th class="num">Total</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($visits as $v): ?>
          <tr>
            <td><?= date('m/d/Y', strtotime($v['created_at'])) ?>
              <?php if ($v['status'] !== 'completed'): ?><span class="pill pill-void"><?= e($v['status']) ?></span><?php endif; ?>
            </td>
            <td><?= e($v['tech_name'] ?: '—') ?></td>
            <td class="num"><?= money($v['grand_total']) ?></td>
            <td><a class="btn btn-light btn-sm" href="<?= BASE_PATH ?>/pos/receipt.php?id=<?= (int)$v['id'] ?>" target="_blank" rel="noopener">Receipt</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$visits): ?><tr><td colspan="4" style="color:var(--ink-soft)">No visits recorded yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <h2>🎁 Gift cards</h2>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Code</th><th class="num">Balance</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($cards as $c): ?>
          <tr><td style="font-family:monospace"><?= e($c['code']) ?></td>
              <td class="num"><?= money($c['balance']) ?></td>
              <td><span class="pill <?= $c['status'] === 'active' ? 'pill-ok' : 'pill-void' ?>"><?= e($c['status']) ?></span></td></tr>
        <?php endforeach; ?>
        <?php if (!$cards): ?><tr><td colspan="3" style="color:var(--ink-soft)">No gift cards on file.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <h2>✍️ Signed forms</h2>
    <?php if (!$consents): ?>
      <p class="sub">Nothing signed yet.</p>
      <?php foreach ($templates as $t): ?>
        <a class="btn btn-light btn-sm" style="margin:0 6px 6px 0"
           href="<?= BASE_PATH ?>/pos/consent.php?client=<?= $id ?>&form=<?= e($t['form_key']) ?>"><?= e($t['title']) ?></a>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Form</th><th>Signed</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($consents as $c): ?>
            <tr><td><?= e($c['form_title']) ?></td>
                <td><?= date('m/d/Y', strtotime($c['signed_at'])) ?></td>
                <td><a class="btn btn-light btn-sm" href="<?= BASE_PATH ?>/pos/consent.php?view=<?= (int)$c['id'] ?>" target="_blank" rel="noopener">View</a></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="margin-top:12px">
        <?php foreach ($templates as $t): ?>
          <a class="btn btn-light btn-sm" style="margin:0 6px 6px 0"
             href="<?= BASE_PATH ?>/pos/consent.php?client=<?= $id ?>&form=<?= e($t['form_key']) ?>">+ <?= e($t['title']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
