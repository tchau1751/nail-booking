<?php
// ============================================================
//  Till sign-in by PIN.
//
//  Standalone on purpose — no nav, no role gate, nothing that
//  assumes somebody is already signed in. Tap your name, tap
//  four digits, you're at the register.
//
//  The email + password login is still there for anything a PIN
//  should not open; the link at the bottom goes to it.
// ============================================================
require_once __DIR__ . '/includes/pos.php';

startSecureSession();
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id  = (int)($_POST['user_id'] ?? 0);
    $pin = preg_replace('/\D/', '', (string)($_POST['pin'] ?? ''));
    if (!$id || strlen($pin) < PIN_MIN_DIGITS) {
        $err = 'Pick your name and enter your PIN.';
    } else {
        $err = loginByPin($id, $pin) ?? '';
        if ($err === '') {
            header('Location: ' . BASE_PATH . '/pos/');
            exit;
        }
    }
}

if (isLoggedIn()) { header('Location: ' . BASE_PATH . '/pos/'); exit; }

$people = pinUsers();
$salon  = settings()['business_name'] ?? 'Nail Salon';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no,viewport-fit=cover">
<title>Sign in — <?= e($salon) ?> POS</title>
<link rel="stylesheet" href="<?= BASE_PATH ?>/pos/assets/pos.css?v=<?= @filemtime(__DIR__ . '/assets/pos.css') ?>">
<style>
  body{display:flex;align-items:center;justify-content:center;min-height:100dvh;padding:16px;}
  .signin{background:#fff;border-radius:18px;box-shadow:0 18px 48px rgba(0,0,0,.28);
    width:100%;max-width:640px;padding:20px;}
  .signin h1{font-size:20px;font-weight:800;margin-bottom:4px;}
  .signin .sub{font-size:13px;color:var(--ink-soft);margin-bottom:14px;}
  .people{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;}
  .person{min-height:44px;padding:0 16px;border-radius:12px;border:1px solid var(--line);
    background:#fff;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;}
  .person.active{background:var(--ink);color:#fff;border-color:var(--ink);}
  .person .r{font-size:11px;opacity:.7;text-transform:uppercase;}
  .dots{font-size:30px;letter-spacing:.35em;text-align:center;min-height:44px;
    padding:6px 0;color:var(--ink);}
  .signin .pad{margin:8px 0;}
  .err{background:#fdecec;color:#8f2020;border:1px solid #f5c2c2;padding:10px 12px;
    border-radius:11px;font-weight:700;margin-bottom:12px;}
  .foot{margin-top:14px;text-align:center;font-size:13px;color:var(--ink-soft);}
</style>
</head>
<body data-theme="<?= e(posTheme()) ?>">
<form class="signin" method="post" id="pinForm">
  <h1>💎 <?= e($salon) ?></h1>
  <div class="sub">Tap your name, then your PIN.</div>

  <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>

  <?php if (!$people): ?>
    <div class="err">No one has a till PIN yet. Sign in with an email and password,
      then set PINs under <b>Staff</b>.</div>
  <?php else: ?>
    <div class="people" id="people">
      <?php foreach ($people as $i => $p): ?>
        <button type="button" class="person<?= $i === 0 ? ' active' : '' ?>" data-id="<?= (int)$p['id'] ?>">
          <?= e($p['name']) ?><span class="r"><?= e($p['role']) ?></span>
        </button>
      <?php endforeach; ?>
    </div>

    <div class="dots" id="dots">····</div>
    <div class="pad">
      <?php foreach ([1,2,3,4,5,6,7,8,9] as $d): ?>
        <button type="button" data-k="<?= $d ?>"><?= $d ?></button>
      <?php endforeach; ?>
      <button type="button" data-k="clear">C</button>
      <button type="button" data-k="0">0</button>
      <button type="button" data-k="del">⌫</button>
    </div>

    <input type="hidden" name="user_id" id="userId" value="<?= (int)$people[0]['id'] ?>">
    <input type="hidden" name="pin" id="pinValue">
    <button class="btn btn-green btn-lg" type="submit" style="width:100%">Sign in</button>
  <?php endif; ?>

  <div class="foot"><a href="<?= BASE_PATH ?>/admin/login.php">Sign in with email and password</a></div>
</form>

<script>
(function () {
  var pin = '', dots = document.getElementById('dots');
  var people = document.getElementById('people');
  if (!people) return;

  function paint() {
    dots.textContent = pin.length ? '●'.repeat(pin.length) : '····';
    document.getElementById('pinValue').value = pin;
  }
  people.addEventListener('click', function (ev) {
    var b = ev.target.closest('.person');
    if (!b) return;
    [].forEach.call(people.children, function (el) { el.classList.remove('active'); });
    b.classList.add('active');
    document.getElementById('userId').value = b.getAttribute('data-id');
    pin = ''; paint();
  });
  document.querySelector('.pad').addEventListener('click', function (ev) {
    var k = ev.target.getAttribute('data-k');
    if (k === null) return;
    if (k === 'clear') pin = '';
    else if (k === 'del') pin = pin.slice(0, -1);
    else if (pin.length < 12) pin += k;
    paint();
    // Four digits is the usual PIN, so submit as soon as it can be right.
    if (pin.length === 4) document.getElementById('pinForm').requestSubmit();
  });
  paint();
})();
</script>
</body>
</html>
