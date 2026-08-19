<?php
/* SMS Log — shows what Twilio actually did with each outgoing message. */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle    = 'SMS Log';
$pageSubtitle = 'Delivery status of outgoing text messages';
$activeNav    = '';

ob_start();
require_once __DIR__ . '/includes/layout_start.php';

$pdo = get_db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$err = '';
$rows = [];
$tbl  = null;

foreach (['sms_log', 'sms_logs', 'message_log'] as $t) {
    try { $pdo->query("SELECT 1 FROM `$t` LIMIT 1"); $tbl = $t; break; }
    catch (Throwable $e) { /* try next */ }
}

if (!$tbl) {
    $err = 'No sms_log table found in this database.';
} else {
    try {
        $rows = $pdo->query("SELECT * FROM `$tbl` ORDER BY 1 DESC LIMIT 40")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $err = 'Query failed: ' . $e->getMessage(); }
}

/* tally statuses so the answer is obvious at a glance */
$tally = [];
foreach ($rows as $r) {
    $s = $r['status'] ?? '(no status column)';
    $tally[$s] = ($tally[$s] ?? 0) + 1;
}
$BP = defined('BASE_PATH') ? BASE_PATH : '';
?>
<style>
#smsRoot{display:block!important;width:100%!important;max-width:1200px;margin:0 auto;
  font-family:Manrope,Arial,sans-serif;color:#2b2b2b;box-sizing:border-box}
#smsRoot *{box-sizing:border-box}
#smsRoot .card{display:block!important;width:100%!important;background:#fff;border:1px solid #e6e6e6;
  border-radius:10px;padding:16px;margin:0 0 14px}
#smsRoot h1{font-size:22px;font-weight:800;margin:0 0 2px}
#smsRoot .sub{font-size:13px;color:#8a8a8a;margin:0 0 14px}
#smsRoot .pill{display:inline-block;padding:5px 11px;border-radius:20px;font-size:12px;
  font-weight:700;margin:0 6px 6px 0}
#smsRoot .scroll{width:100%;overflow-x:auto}
#smsRoot table{width:100%;border-collapse:collapse;font-size:12.5px}
#smsRoot th{background:#f4f6f8;padding:9px 10px;text-align:left;font-weight:700;
  border-bottom:2px solid #e0e0e0;white-space:nowrap}
#smsRoot td{padding:9px 10px;border-bottom:1px solid #f0f0f0;vertical-align:top;
  max-width:320px;word-break:break-word}
#smsRoot tbody tr:nth-child(even){background:#fbfbfb}
#smsRoot .note{padding:12px 14px;border-radius:8px;margin:0 0 12px;font-size:13px;
  background:#fdecea;color:#8c1d18;border:1px solid #f5c6cb}
#smsRoot .back{display:inline-block;padding:11px 16px;border-radius:8px;background:#1ba0c8;
  color:#fff;text-decoration:none;font-weight:700;font-size:13px}
</style>

<div id="smsRoot">
  <div class="card">
    <h1>SMS Log</h1>
    <p class="sub">The 40 most recent outgoing messages<?= $tbl ? ' from <code>' . h($tbl) . '</code>' : '' ?>.</p>

    <?php if ($err): ?><div class="note"><?= h($err) ?></div><?php endif; ?>

    <?php if ($tally): ?>
      <div>
        <?php foreach ($tally as $s => $n):
          $bg = '#eceff1'; $fg = '#37474f';
          if ($s === 'sent')           { $bg = '#e8f5e9'; $fg = '#1b5e20'; }
          if ($s === 'failed')         { $bg = '#fdecea'; $fg = '#8c1d18'; }
          if ($s === 'not_configured') { $bg = '#fff8e1'; $fg = '#8a6d00'; }
        ?>
          <span class="pill" style="background:<?= $bg ?>;color:<?= $fg ?>">
            <?= h($s) ?>: <?= (int)$n ?>
          </span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <?php if (!$rows): ?>
      <div style="padding:30px;text-align:center;color:#999">No messages logged yet.</div>
    <?php else: ?>
      <div class="scroll">
        <table>
          <thead>
            <tr><?php foreach (array_keys($rows[0]) as $c): ?><th><?= h($c) ?></th><?php endforeach; ?></tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <?php foreach ($r as $k => $v): ?>
                  <?php
                    $st = '';
                    if ($k === 'status') {
                        if ($v === 'sent')   $st = 'color:#1b5e20;font-weight:700';
                        elseif ($v === 'failed' || $v === 'not_configured') $st = 'color:#8c1d18;font-weight:700';
                    }
                  ?>
                  <td style="<?= $st ?>"><?= h($v) ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <a class="back" href="<?= $BP ?>/studio/">Back to Bookings</a>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>