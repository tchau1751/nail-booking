<?php
// ============================================================
//  The admin password screen. A locked till is sent here from any
//  back-office screen (ADMIN_LOCKED_PAGES, the booking admin) and
//  taken back to it once the salon's admin password checks out.
// ============================================================
$pageTitle = 'Admin';
$activeNav = 'admin';
$requireRole = 'manager';   // the password is a second lock; the role still comes first
require_once __DIR__ . '/includes/layout_start.php';

$next = adminNextUrl($_POST['next'] ?? $_GET['next'] ?? '');
$err  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action'] ?? '') {
        case 'unlock':
            $err = adminUnlock(trim((string)($_POST['pin'] ?? ''))) ?? '';
            break;
        case 'reset':
            $err = adminPinResetByOwner((string)($_POST['password'] ?? '')) ?? '';
            if (!$err) $next = BASE_PATH . '/pos/settings.php#admin-password';
            break;
        case 'lock':
            adminLock();
            header('Location: ' . BASE_PATH . '/pos/');
            exit;
    }
    if (!$err) {
        header('Location: ' . $next);
        exit;
    }
} elseif (adminUnlocked()) {
    header('Location: ' . $next);
    exit;
}
?>
<style>
  .lockcard{max-width:420px;margin:5vh auto 0;text-align:center;}
  .lockcard .lock-ico{font-size:46px;line-height:1;}
  .lockcard input[type=password]{font-size:28px;text-align:center;letter-spacing:10px;}
  .lockcard .pad{max-width:320px;margin:14px auto;}
  .lockcard details{margin-top:18px;text-align:left;}
  .lockcard summary{cursor:pointer;font-weight:600;color:var(--ink-soft);}
</style>

<div class="card lockcard">
  <div class="lock-ico">🔒</div>
  <h2 style="margin:8px 0 4px">Admin</h2>
  <p class="sub">Enter the salon's admin password to open Services, Staff, Set-ups, Sales and Reports.
     They stay open for <?= ADMIN_UNLOCK_MINUTES ?> minutes after the last admin screen.</p>
  <?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>

  <form method="post">
    <input type="hidden" name="action" value="unlock">
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <label class="field"><span>Admin password</span>
      <input type="password" name="pin" id="adminPin" inputmode="numeric" pattern="[0-9]*" maxlength="8"
             autocomplete="off" autofocus required></label>
    <div class="pad" id="adminPad">
      <?php foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9', 'clear', '0', 'del'] as $k): ?>
        <button type="button" data-k="<?= $k ?>"><?= $k === 'del' ? '⌫' : ($k === 'clear' ? 'C' : $k) ?></button>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-green btn-lg" type="submit" style="width:100%">Unlock</button>
  </form>

  <?php if (hasRole('owner')): ?>
    <details>
      <summary>Forgot the admin password?</summary>
      <p class="sub">Your own sign-in password puts it back to <?= ADMIN_PIN_DEFAULT ?> and opens Settings,
         so you can choose a new one straight away.</p>
      <form method="post">
        <input type="hidden" name="action" value="reset">
        <label class="field"><span>Your sign-in password</span>
          <input type="password" name="password" autocomplete="current-password" required></label>
        <button class="btn btn-light" type="submit">Reset the admin password</button>
      </form>
    </details>
  <?php endif; ?>
</div>

<script>
  (function () {
    var input = document.getElementById('adminPin');
    document.getElementById('adminPad').addEventListener('click', function (ev) {
      var k = ev.target.getAttribute && ev.target.getAttribute('data-k');
      if (!k) return;
      if (k === 'del') input.value = input.value.slice(0, -1);
      else if (k === 'clear') input.value = '';
      else if (input.value.length < 8) input.value += k;
      input.focus();
    });
  })();
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
