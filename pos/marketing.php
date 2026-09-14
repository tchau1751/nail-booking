<?php
$pageTitle = 'Marketing';
$activeNav = 'marketing';
$requireRole = 'manager';   // enforced by layout_start before any output
require_once __DIR__ . '/includes/layout_start.php';
require_once __DIR__ . '/includes/salon.php';
require_once __DIR__ . '/../includes/sms.php';

/**
 * Segments only ever return clients who opted in — an opt-out is honoured
 * everywhere, no exceptions — and only this salon's clients. A campaign is
 * never a way to text another salon's guests.
 */
function segmentQuery(string $segment, string $arg = ''): array {
    $base = 'SELECT * FROM pos_clients WHERE tenant_id=? AND is_active=1 AND marketing_opt_in=1 AND phone <> ""';
    $tid  = tenantId();
    switch ($segment) {
        case 'lapsed':
            $days = (int)($arg ?: 60);
            return [$base . ' AND (last_visit IS NULL OR last_visit < DATE_SUB(CURDATE(), INTERVAL ? DAY))
                     ORDER BY last_visit IS NULL, last_visit', [$tid, $days]];
        case 'recent':
            $days = (int)($arg ?: 30);
            return [$base . ' AND last_visit >= DATE_SUB(CURDATE(), INTERVAL ? DAY) ORDER BY last_visit DESC', [$tid, $days]];
        case 'birthday':
            $m = (int)($arg ?: date('n'));
            return [$base . ' AND birthday IS NOT NULL AND MONTH(birthday)=? ORDER BY DAY(birthday)', [$tid, $m]];
        case 'vip':
            $min = (float)($arg ?: 300);
            return [$base . ' AND total_spend >= ? ORDER BY total_spend DESC', [$tid, $min]];
        case 'points':
            $min = (int)($arg ?: 100);
            return [$base . ' AND points >= ? ORDER BY points DESC', [$tid, $min]];
        case 'all':
        default:
            return [$base . ' ORDER BY full_name', [$tid]];
    }
}

$segments = [
    'all'      => ['Everyone who opted in', ''],
    'lapsed'   => ['Not seen in N days', '60'],
    'recent'   => ['Visited in the last N days', '30'],
    'birthday' => ['Birthday in month (1-12)', ''],
    'vip'      => ['Lifetime spend over', '300'],
    'points'   => ['Points balance at least', '100'],
];

$msg = ''; $err = ''; $preview = null;
$segment = $_POST['segment'] ?? $_GET['segment'] ?? 'all';
$segArg  = $_POST['segment_args'] ?? $_GET['segment_args'] ?? '';
$message = $_POST['message'] ?? '';
$name    = $_POST['name'] ?? '';

try {
    [$sql, $args] = segmentQuery($segment, $segArg);
    $audience = fetchAll($sql, $args);
} catch (Throwable $e) { $audience = []; $err = $e->getMessage(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['action'] ?? '') === 'send') {
            if (trim($message) === '') throw new RuntimeException('Write a message first.');
            if (!$audience) throw new RuntimeException('That segment has nobody in it.');

            $sent = 0; $failed = 0;
            foreach ($audience as $cl) {
                $body = strtr($message, [
                    '{name}'   => explode(' ', $cl['full_name'])[0],
                    '{points}' => (int)$cl['points'],
                    '{salon}'  => settings()['business_name'] ?? '',
                ]);
                $r = sendSMS($cl['phone'], $body, null, 'custom');
                if (!empty($r['success'])) $sent++; else $failed++;
            }
            query('INSERT INTO pos_campaigns (tenant_id, name, message, segment, segment_args, recipients,
                     sent_count, failed_count, admin_id) VALUES (?,?,?,?,?,?,?,?,?)',
                  [tenantId(), trim($name) ?: 'Campaign', $message, $segment, $segArg,
                   count($audience), $sent, $failed, $admin['id'] ?? null]);
            $msg = "Sent $sent message" . ($sent === 1 ? '' : 's') . ($failed ? ", $failed failed" : '') . '.';
        } else {
            $preview = true;   // just re-ran the segment
        }
    } catch (Throwable $e) { $err = $e->getMessage(); }
}

$history = fetchAll('SELECT c.*, u.name AS who FROM pos_campaigns c
                     LEFT JOIN admin_users u ON u.id=c.admin_id
                     WHERE c.tenant_id=?
                     ORDER BY c.id DESC LIMIT 25', [tenantId()]);
$optedOut = (int)(fetchOne('SELECT COUNT(*) n FROM pos_clients WHERE tenant_id=? AND is_active=1 AND marketing_opt_in=0',
                           [tenantId()])['n'] ?? 0);
$sentAllTime = (int)(fetchOne('SELECT COALESCE(SUM(sent_count),0) n FROM pos_campaigns WHERE tenant_id=?',
                              [tenantId()])['n'] ?? 0);
$twilioReady = (bool)(settings()['twilio_account_sid'] ?: TWILIO_ACCOUNT_SID);
?>
<?php if ($msg): ?><div class="alert alert-ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>
<?php if (!$twilioReady): ?>
  <div class="alert alert-err">Twilio isn't configured yet — add your credentials in the admin dashboard settings before sending.</div>
<?php endif; ?>

<div class="stats">
  <div class="stat"><div class="v"><?= count($audience) ?></div><div class="k">In this segment</div></div>
  <div class="stat"><div class="v"><?= $optedOut ?></div><div class="k">Opted out (never messaged)</div></div>
  <div class="stat"><div class="v"><?= $sentAllTime ?></div><div class="k">Texts sent all time</div></div>
</div>

<div class="card">
  <h2>📣 New campaign</h2>
  <p class="sub">Pick who it goes to, write the text, check the count, then send.
     Use <code>{name}</code>, <code>{points}</code> or <code>{salon}</code> and they'll be filled in per guest.</p>

  <form method="post">
    <div class="toolbar">
      <label class="field"><span>Campaign name</span><input type="text" name="name" value="<?= e($name) ?>" placeholder="August promo"></label>
      <label class="field"><span>Send to</span>
        <select name="segment" onchange="this.form.querySelector('[name=action]').value='preview';this.form.submit()">
          <?php foreach ($segments as $k => $s): ?>
            <option value="<?= $k ?>" <?= $segment === $k ? 'selected' : '' ?>><?= e($s[0]) ?></option>
          <?php endforeach; ?>
        </select></label>
      <label class="field"><span>Value</span>
        <input type="text" name="segment_args" value="<?= e($segArg) ?>"
               placeholder="<?= e($segments[$segment][1] ?? '') ?>"></label>
      <button class="btn btn-light" type="submit" name="action" value="preview">Refresh count</button>
    </div>

    <label class="field"><span>Message</span>
      <textarea name="message" rows="4" maxlength="450"
        placeholder="Hi {name}! Treat yourself this week — 15% off any gel service at {salon}. Reply STOP to opt out."><?= e($message) ?></textarea></label>
    <p class="sub" style="margin-top:-6px">
      Keep it under 160 characters to stay a single text. Always leave the STOP line in — it's required.
    </p>

    <button class="btn btn-green btn-lg" type="submit" name="action" value="send"
      onclick="return confirm('Send this text to <?= count($audience) ?> guest(s)?')"
      <?= (!$audience || !$twilioReady) ? 'disabled' : '' ?>>
      Send to <?= count($audience) ?> guest<?= count($audience) === 1 ? '' : 's' ?>
    </button>
  </form>
</div>

<div class="card">
  <h2>Who will get it</h2>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>Phone</th><th class="num">Visits</th><th class="num">Spend</th><th>Last visit</th></tr></thead>
      <tbody>
      <?php foreach (array_slice($audience, 0, 100) as $a): ?>
        <tr><td><?= e($a['full_name']) ?></td><td><?= e(formatPhone($a['phone'])) ?></td>
            <td class="num"><?= (int)$a['total_visits'] ?></td>
            <td class="num"><?= money($a['total_spend']) ?></td>
            <td><?= $a['last_visit'] ? date('m/d/Y', strtotime($a['last_visit'])) : 'never' ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$audience): ?>
        <tr><td colspan="5" style="text-align:center;color:var(--ink-soft);padding:30px">Nobody matches this segment.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if (count($audience) > 100): ?>
    <p class="sub" style="margin-top:10px">Showing the first 100 of <?= count($audience) ?> — all of them will receive it.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Past campaigns</h2>
  <div class="table-wrap">
    <table>
      <thead><tr><th>When</th><th>Name</th><th>Segment</th><th class="num">Sent</th><th class="num">Failed</th><th>By</th></tr></thead>
      <tbody>
      <?php foreach ($history as $h): ?>
        <tr><td><?= date('m/d/Y g:i A', strtotime($h['created_at'])) ?></td>
            <td><?= e($h['name']) ?><div style="font-size:12px;color:var(--ink-soft)"><?= e(mb_substr($h['message'], 0, 70)) ?>…</div></td>
            <td><?= e($h['segment']) ?><?= $h['segment_args'] ? ' (' . e($h['segment_args']) . ')' : '' ?></td>
            <td class="num"><?= (int)$h['sent_count'] ?></td>
            <td class="num"><?= (int)$h['failed_count'] ?></td>
            <td><?= e($h['who'] ?: '—') ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$history): ?><tr><td colspan="6" style="color:var(--ink-soft)">No campaigns sent yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
