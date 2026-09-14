<?php
// Platform sign-in. Separate from every salon's sign-in on purpose.
require_once __DIR__ . '/../includes/platform.php';

if (platformAdmin()) { header('Location: ' . BASE_PATH . '/platform/'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!platformCsrfValid($_POST['_csrf'] ?? null)) {
        $err = 'That form went stale. Try again.';
    } else {
        $err = platformLogin((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? '')) ?? '';
        if ($err === '') { header('Location: ' . BASE_PATH . '/platform/'); exit; }
    }
}
$noAdmins = !fetchOne('SELECT 1 x FROM platform_admins LIMIT 1');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Platform sign-in</title>
<link rel="stylesheet" href="<?= BASE_PATH ?>/pos/assets/pos.css">
<style>
  body{display:flex;align-items:center;justify-content:center;min-height:100dvh;padding:16px;}
  .box{background:#fff;border-radius:18px;box-shadow:0 18px 48px rgba(0,0,0,.25);width:100%;max-width:420px;padding:26px;}
  .box h1{font-size:20px;margin-bottom:4px}
</style>
</head>
<body data-theme="blue-lavender">
<form class="box" method="post">
  <h1>🏢 Platform</h1>
  <p class="sub">Every salon on this server. Salon staff sign in on their own till.</p>
  <?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>
  <?php if ($noAdmins): ?>
    <div class="alert alert-err">No platform admin exists yet. Create the first one on the server itself:<br>
      <code>php tools/create-platform-admin.php you@example.com "Your Name"</code></div>
  <?php endif; ?>
  <input type="hidden" name="_csrf" value="<?= e(platformCsrf()) ?>">
  <label class="field"><span>Email</span><input type="email" name="email" required autocomplete="username"></label>
  <label class="field"><span>Password</span><input type="password" name="password" required autocomplete="current-password"></label>
  <button class="btn btn-green btn-lg" type="submit" style="width:100%">Sign in</button>
</form>
</body>
</html>
