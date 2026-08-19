<?php
require_once __DIR__ . '/includes/auth.php';

if (current_admin()) {
    header('Location: /studio/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $pdo = get_db();
        $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE username = :u LIMIT 1');
        $stmt->execute(['u' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_user'] = [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
            ];
            $pdo->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $user['id']]);
            header('Location: /studio/index.php');
            exit;
        }
        $error = 'Incorrect username or password.';
    }
}

$token = admin_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — Diamond Nails & Spa Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Playfair+Display:wght@600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/studio/assets/css/admin.css">
</head>
<body class="auth-body">
  <div class="auth-card">
    <div class="auth-brand">
      <span class="brand-mark">D</span>
      <div>Diamond Nails <small>Studio Dashboard</small></div>
    </div>

    <?php if ($error): ?><div class="auth-alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token) ?>">
      <div class="form-field">
        <label>Username</label>
        <input type="text" name="username" required autofocus>
      </div>
      <div class="form-field">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>
      <button class="btn btn-primary btn-block" type="submit">Sign In</button>
    </form>
  </div>
</body>
</html>
