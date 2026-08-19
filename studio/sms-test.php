<?php
/* Twilio tester — shows the exact raw response so we can read the real error.
   Delete this file once SMS is working. */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle    = 'SMS Test';
$pageSubtitle = 'Send one test message and show Twilio\'s raw reply';
$activeNav    = '';

ob_start();
require_once __DIR__ . '/includes/layout_start.php';

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function mask($v) {
    $v = (string)$v;
    if ($v === '') return '(empty)';
    if (strlen($v) <= 8) return str_repeat('*', strlen($v));
    return substr($v, 0, 4) . str_repeat('*', max(4, strlen($v) - 8)) . substr($v, -4);
}

/* ---- gather credentials from wherever this install keeps them ---- */
$sid = $token = $from = '';
$src = [];

if (function_exists('settings')) {
    try {
        $s = settings();
        if (!empty($s['twilio_account_sid']))  { $sid   = $s['twilio_account_sid'];  $src['sid']   = 'Studio Settings (database)'; }
        if (!empty($s['twilio_auth_token']))   { $token = $s['twilio_auth_token'];   $src['token'] = 'Studio Settings (database)'; }
        if (!empty($s['twilio_from_number']))  { $from  = $s['twilio_from_number'];  $src['from']  = 'Studio Settings (database)'; }
    } catch (Throwable $e) { /* fall through to constants */ }
}
if (!$sid   && defined('TWILIO_ACCOUNT_SID')) { $sid   = TWILIO_ACCOUNT_SID;  $src['sid']   = 'config constant'; }
if (!$token && defined('TWILIO_AUTH_TOKEN'))  { $token = TWILIO_AUTH_TOKEN;   $src['token'] = 'config constant'; }
if (!$from  && defined('TWILIO_FROM_NUMBER')) { $from  = TWILIO_FROM_NUMBER;  $src['from']  = 'config constant'; }

$result = null;
$check  = null;
$act    = $_POST['act'] ?? 'send';

/* ---- look up the FINAL status of a previously sent message ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $act === 'check') {
    $msid = trim($_POST['msid'] ?? '');
    if (!$sid || !$token) {
        $check = ['http' => 0, 'raw' => 'Credentials missing.'];
    } elseif ($msid === '') {
        $check = ['http' => 0, 'raw' => 'Enter a message SID (starts with SM).'];
    } else {
        $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages/"
                        . rawurlencode($msid) . ".json");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => "{$sid}:{$token}",
            CURLOPT_TIMEOUT        => 20,
        ]);
        $raw  = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);
        $check = ['http' => $http, 'raw' => $raw !== false ? $raw : 'cURL error: ' . $cerr];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $act === 'send') {
    $to = preg_replace('/\D/', '', $_POST['to'] ?? '');
    if (strlen($to) === 10) $to = '1' . $to;
    $to = '+' . $to;
    $body = trim($_POST['body'] ?? '') ?: 'Diamond Nails test message.';

    if (!$sid || !$token || !$from) {
        $result = ['http' => 0, 'raw' => 'Cannot send: credentials are missing (see the panel above).', 'to' => $to];
    } elseif (!function_exists('curl_init')) {
        $result = ['http' => 0, 'raw' => 'Cannot send: the cURL extension is not available on this server.', 'to' => $to];
    } else {
        $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['To' => $to, 'From' => $from, 'Body' => $body]),
            CURLOPT_USERPWD        => "{$sid}:{$token}",
            CURLOPT_TIMEOUT        => 20,
        ]);
        $raw   = curl_exec($ch);
        $http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr  = curl_error($ch);
        curl_close($ch);
        $result = ['http' => $http, 'raw' => $raw !== false ? $raw : 'cURL error: ' . $cerr, 'to' => $to];
    }
}
$BP = defined('BASE_PATH') ? BASE_PATH : '';
?>
<style>
#stRoot{display:block!important;width:100%!important;max-width:900px;margin:0 auto;
  font-family:Manrope,Arial,sans-serif;color:#2b2b2b;box-sizing:border-box}
#stRoot *{box-sizing:border-box}
#stRoot .card{display:block!important;width:100%!important;background:#fff;border:1px solid #e6e6e6;
  border-radius:10px;padding:18px;margin:0 0 14px}
#stRoot h1{font-size:22px;font-weight:800;margin:0 0 2px}
#stRoot h2{font-size:15px;font-weight:800;margin:0 0 10px}
#stRoot .sub{font-size:13px;color:#8a8a8a;margin:0 0 16px}
#stRoot table{width:100%;border-collapse:collapse;font-size:13px}
#stRoot td{padding:8px 6px;border-bottom:1px solid #f0f0f0}
#stRoot td:first-child{color:#777;width:150px}
#stRoot .ok{color:#1b5e20;font-weight:700}
#stRoot .bad{color:#8c1d18;font-weight:700}
#stRoot .row{display:flex!important;gap:8px;flex-wrap:wrap;align-items:center}
#stRoot input[type=text]{flex:1 1 220px;padding:10px 12px;border:1px solid #ccc;
  border-radius:6px;font-size:14px;background:#fff}
#stRoot button{padding:11px 18px;border:0;border-radius:6px;background:#1ba0c8;color:#fff;
  font-weight:700;font-size:13px;cursor:pointer}
#stRoot pre{background:#1e1e1e;color:#e6e6e6;padding:14px;border-radius:8px;
  font-size:12.5px;overflow-x:auto;white-space:pre-wrap;word-break:break-word;margin:0}
#stRoot .warn{background:#fff8e1;color:#7a5c00;border:1px solid #ffe9a8;
  padding:12px 14px;border-radius:8px;font-size:13px;margin:0 0 12px}
#stRoot .back{display:inline-block;padding:11px 16px;border-radius:8px;background:#6c757d;
  color:#fff;text-decoration:none;font-weight:700;font-size:13px}
</style>

<div id="stRoot">

  <div class="card">
    <h1>SMS Test</h1>
    <p class="sub">Sends one real message, then prints exactly what Twilio said back.</p>

    <h2>Credentials this site is using</h2>
    <table>
      <tr>
        <td>Account SID</td>
        <td class="<?= $sid ? 'ok' : 'bad' ?>"><?= h(mask($sid)) ?>
          <?php if ($sid): ?><span style="color:#999;font-weight:400"> — <?= h($src['sid'] ?? '?') ?></span><?php endif; ?>
        </td>
      </tr>
      <tr>
        <td>Auth Token</td>
        <td class="<?= $token ? 'ok' : 'bad' ?>"><?= h(mask($token)) ?>
          <?php if ($token): ?><span style="color:#999;font-weight:400"> — <?= h($src['token'] ?? '?') ?></span><?php endif; ?>
        </td>
      </tr>
      <tr>
        <td>From number</td>
        <td class="<?= $from ? 'ok' : 'bad' ?>"><?= h($from ?: '(empty)') ?>
          <?php if ($from): ?><span style="color:#999;font-weight:400"> — <?= h($src['from'] ?? '?') ?></span><?php endif; ?>
        </td>
      </tr>
      <tr>
        <td>cURL available</td>
        <td class="<?= function_exists('curl_init') ? 'ok' : 'bad' ?>">
          <?= function_exists('curl_init') ? 'yes' : 'NO — Twilio cannot be reached' ?>
        </td>
      </tr>
    </table>
  </div>

  <div class="card">
    <h2>Send a test</h2>
    <div class="warn">This sends a real SMS and Twilio will charge for it. Use your own phone.</div>
    <form class="row" method="post">
      <input type="hidden" name="act" value="send">
      <input type="text" name="to" placeholder="Your mobile number" required
             value="<?= h($_POST['to'] ?? '') ?>">
      <input type="text" name="body" placeholder="Message (optional)"
             value="<?= h($_POST['body'] ?? '') ?>">
      <button>Send test SMS</button>
    </form>
  </div>

  <?php
    $lastSid = '';
    if ($result && is_string($result['raw'])) {
        $j = json_decode($result['raw'], true);
        if (!empty($j['sid'])) $lastSid = $j['sid'];
    }
    if (!$lastSid) $lastSid = trim($_POST['msid'] ?? '');
  ?>
  <div class="card">
    <h2>Check delivery status</h2>
    <p class="sub" style="margin-bottom:10px">
      Wait about a minute after sending, then check. This asks Twilio what actually
      happened to the message — <strong>this is the answer we need</strong>, not the
      <code>queued</code> you get at send time.
    </p>
    <form class="row" method="post">
      <input type="hidden" name="act" value="check">
      <input type="text" name="msid" placeholder="Message SID (SM...)" required
             value="<?= h($lastSid) ?>">
      <button style="background:#6c3">Check status</button>
    </form>
  </div>

  <?php if ($check): ?>
  <div class="card">
    <h2>Final status</h2>
    <?php
      $cj = is_string($check['raw']) ? json_decode($check['raw'], true) : null;
      $st = $cj['status'] ?? null;
      $ec = $cj['error_code'] ?? null;
      $em = $cj['error_message'] ?? null;
    ?>
    <?php if ($st): ?>
      <p style="font-size:15px;margin:0 0 10px">
        Status:
        <strong class="<?= in_array($st, ['delivered', 'sent'], true) ? 'ok' : 'bad' ?>"><?= h($st) ?></strong>
        <?php if ($ec): ?>
          &nbsp;·&nbsp; Error <strong class="bad"><?= h($ec) ?></strong>
          <?php if ($em): ?> — <?= h($em) ?><?php endif; ?>
        <?php endif; ?>
      </p>
      <?php if ((string)$ec === '30032'): ?>
        <div class="warn"><strong>Error 30032 — toll-free number not verified.</strong>
          Carriers are blocking it. Fix this in Twilio Console → Phone Numbers →
          Regulatory Compliance → Toll-Free Verification. No code change will help.</div>
      <?php elseif ((string)$ec === '30007'): ?>
        <div class="warn"><strong>Error 30007 — carrier filtered the message</strong>
          as suspected spam. Usually message wording, or an unregistered sender.</div>
      <?php elseif ((string)$ec === '21608'): ?>
        <div class="warn"><strong>Error 21608 — trial account.</strong>
          It can only text numbers you have verified in the Twilio console. Upgrade the account.</div>
      <?php endif; ?>
    <?php endif; ?>
    <pre><?= h(is_string($check['raw']) ? $check['raw'] : json_encode($check['raw'], JSON_PRETTY_PRINT)) ?></pre>
  </div>
  <?php endif; ?>

  <?php if ($result): ?>
  <div class="card">
    <h2>Twilio's reply — sending to <?= h($result['to']) ?></h2>
    <p class="sub" style="margin-bottom:10px">
      HTTP status:
      <strong class="<?= ($result['http'] >= 200 && $result['http'] < 300) ? 'ok' : 'bad' ?>">
        <?= (int)$result['http'] ?>
      </strong>
      <?= ($result['http'] >= 200 && $result['http'] < 300)
            ? ' — accepted (delivery still depends on the carrier)'
            : ' — rejected, the reason is below' ?>
    </p>
    <pre><?= h(is_string($result['raw']) ? $result['raw'] : json_encode($result['raw'], JSON_PRETTY_PRINT)) ?></pre>
  </div>
  <?php endif; ?>

  <a class="back" href="<?= $BP ?>/studio/">Back to Bookings</a>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>