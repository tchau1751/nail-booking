<?php
$pageTitle = 'Feedback';
$activeNav = 'feedback';
$requireRole = 'manager';   // enforced by layout_start before any output
require_once __DIR__ . '/includes/layout_start.php';
require_once __DIR__ . '/includes/salon.php';
require_once __DIR__ . '/../includes/sms.php';

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request') {
    try {
        $ids = array_map('intval', $_POST['ids'] ?? []);
        if (!$ids) throw new RuntimeException('Nothing selected.');
        $sent = 0; $skipped = 0;
        foreach ($ids as $fid) {
            $f = fetchOne('SELECT f.*, c.full_name, c.phone, c.marketing_opt_in
                           FROM pos_feedback f JOIN pos_clients c ON c.id=f.client_id
                           WHERE f.id=? AND f.tenant_id=? AND f.responded_at IS NULL', [$fid, tenantId()]);
            if (!$f || !$f['phone']) { $skipped++; continue; }
            $link = APP_URL . '/pos/review.php?t=' . $f['token'];
            $body = 'Hi ' . explode(' ', $f['full_name'])[0] . '! Thanks for visiting '
                  . (settings()['business_name'] ?? 'us') . '. How did we do? ' . $link;
            $r = sendSMS($f['phone'], $body, null, 'custom');
            if (!empty($r['success'])) $sent++; else $skipped++;
        }
        $msg = "Sent $sent request" . ($sent === 1 ? '' : 's') . ($skipped ? ", $skipped skipped" : '') . '.';
    } catch (Throwable $e) { $err = $e->getMessage(); }
}

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$rng  = [tenantId(), $from, $to];

$stats = fetchOne('SELECT COUNT(*) requested,
                          COUNT(rating) answered,
                          AVG(rating) avg_rating,
                          SUM(CASE WHEN rating <= 3 THEN 1 ELSE 0 END) unhappy
                   FROM pos_feedback WHERE tenant_id=? AND DATE(requested_at) BETWEEN ? AND ?', $rng);

$byTech = fetchAll('SELECT COALESCE(t.name,"Unassigned") tech, COUNT(f.rating) n, AVG(f.rating) avg_rating
                    FROM pos_feedback f LEFT JOIN technicians t ON t.id=f.technician_id
                    WHERE f.tenant_id=? AND f.rating IS NOT NULL AND DATE(f.requested_at) BETWEEN ? AND ?
                    GROUP BY tech ORDER BY avg_rating DESC', $rng);

$responses = fetchAll('SELECT f.*, c.full_name, c.phone, t.name AS tech_name
                       FROM pos_feedback f
                       LEFT JOIN pos_clients c ON c.id=f.client_id
                       LEFT JOIN technicians t ON t.id=f.technician_id
                       WHERE f.tenant_id=? AND f.rating IS NOT NULL AND DATE(f.requested_at) BETWEEN ? AND ?
                       ORDER BY f.responded_at DESC LIMIT 100', $rng);

$pending = fetchAll('SELECT f.*, c.full_name, c.phone
                     FROM pos_feedback f JOIN pos_clients c ON c.id=f.client_id
                     WHERE f.tenant_id=? AND f.responded_at IS NULL AND c.phone <> ""
                     ORDER BY f.id DESC LIMIT 50', [tenantId()]);

$rate = (int)$stats['requested'] > 0 ? round((int)$stats['answered'] / (int)$stats['requested'] * 100) : 0;
?>
<?php if ($msg): ?><div class="alert alert-ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>

<form class="toolbar no-print" method="get">
  <label class="field"><span>From</span><input type="date" name="from" value="<?= e($from) ?>"></label>
  <label class="field"><span>To</span><input type="date" name="to" value="<?= e($to) ?>"></label>
  <button class="btn" type="submit">Apply</button>
  <button class="btn btn-blue" type="button" onclick="window.print()">🖨 Print</button>
</form>

<div class="stats">
  <div class="stat"><div class="v"><?= $stats['avg_rating'] ? number_format((float)$stats['avg_rating'], 2) : '—' ?></div><div class="k">Average rating</div></div>
  <div class="stat"><div class="v"><?= (int)$stats['answered'] ?></div><div class="k">Responses</div></div>
  <div class="stat"><div class="v"><?= $rate ?>%</div><div class="k">Response rate</div></div>
  <div class="stat"><div class="v" style="color:<?= (int)$stats['unhappy'] ? 'var(--red)' : 'inherit' ?>"><?= (int)$stats['unhappy'] ?></div><div class="k">Ratings of 3 or less</div></div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px">
  <div class="card">
    <h2>By technician</h2>
    <table>
      <?php foreach ($byTech as $t): ?>
        <tr><td><?= e($t['tech']) ?> <span style="color:var(--ink-soft)"><?= (int)$t['n'] ?> ratings</span></td>
            <td class="num"><strong><?= number_format((float)$t['avg_rating'], 2) ?></strong>
              <?= str_repeat('⭐', (int)round((float)$t['avg_rating'])) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$byTech): ?><tr><td style="color:var(--ink-soft)">No ratings yet.</td></tr><?php endif; ?>
    </table>
  </div>

  <div class="card no-print">
    <h2>Ask for feedback</h2>
    <p class="sub">Requests are created automatically when a guest with a client record checks out.
       Tick the ones to text a review link to.</p>
    <?php if (!$pending): ?>
      <div class="empty" style="padding:24px">Nothing waiting on a response.</div>
    <?php else: ?>
      <form method="post">
        <input type="hidden" name="action" value="request">
        <div style="max-height:280px;overflow-y:auto;margin-bottom:14px">
          <?php foreach ($pending as $p): ?>
            <label style="display:flex;align-items:center;gap:10px;padding:10px;border-bottom:1px solid var(--line)">
              <input type="checkbox" name="ids[]" value="<?= (int)$p['id'] ?>" style="width:22px;height:22px">
              <span><strong><?= e($p['full_name']) ?></strong>
                <div style="font-size:12px;color:var(--ink-soft)"><?= e(formatPhone($p['phone'])) ?>
                  · <?= date('m/d/Y', strtotime($p['requested_at'])) ?></div></span>
            </label>
          <?php endforeach; ?>
        </div>
        <button class="btn btn-green" type="submit"
                onclick="return confirm('Text a review link to everyone ticked?')">Send review links</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h2>Responses</h2>
  <div class="table-wrap">
    <table>
      <thead><tr><th>When</th><th>Guest</th><th>Tech</th><th>Rating</th><th>Comment</th></tr></thead>
      <tbody>
      <?php foreach ($responses as $r): ?>
        <tr style="<?= (int)$r['rating'] <= 3 ? 'background:#fff8f8' : '' ?>">
          <td><?= date('m/d/Y', strtotime($r['responded_at'])) ?></td>
          <td><?= e($r['full_name'] ?: 'Guest') ?></td>
          <td><?= e($r['tech_name'] ?: '—') ?></td>
          <td><?= str_repeat('⭐', (int)$r['rating']) ?> <?= (int)$r['rating'] ?></td>
          <td><?= e($r['comment']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$responses): ?>
        <tr><td colspan="5" style="text-align:center;color:var(--ink-soft);padding:40px">No responses in this range yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
