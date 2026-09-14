<?php
require_once __DIR__ . '/../includes/auth.php';
if (isLoggedIn()) { header('Location: ' . BASE_PATH . '/admin/'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = loginAdmin(trim($_POST['email'] ?? ''), $_POST['password'] ?? '') ?? '';
    if ($error === '') {
        header('Location: ' . BASE_PATH . '/admin/'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin Login — Diamond Nail &amp; Spa</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/admin.css">
</head>
<body class="login-page">
<div class="login-wrap">
  <div class="login-logo">
    <span class="logo-icon">💎</span>
    <h1>Diamond Nail &amp; Spa</h1>
    <p>Sign in to manage your spa dashboard</p>
  </div>
  <form method="POST" class="login-form">
    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <div class="field">
      <label>Email address</label>
      <input type="email" name="email" required autocomplete="email"
             placeholder="admin@diamondnailspa.com"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </div>
    <div class="field">
      <label>Password</label>
      <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
    </div>
    <button type="submit" class="btn btn-primary btn-full">Sign in</button>
  </form>
  <p class="login-hint">💡 Default: admin@diamondnailspa.com / Admin@1234</p>
</div>
</body>
</html>
