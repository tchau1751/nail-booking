<?php
/**
 * ONE-TIME web installer. Visit this once after uploading the project to your
 * Namecheap (or any PHP/MySQL) host — it tests your database connection,
 * creates all tables, seeds sample services/staff, writes config/config.php,
 * and creates your first admin login, all in a single step.
 *
 * Refuses to run again once an admin account already exists. Delete this
 * file from your server after a successful install — it disables itself,
 * but removing it is the safer habit.
 */

session_start();

function csrf_token(): string
{
    if (empty($_SESSION['install_csrf'])) {
        $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['install_csrf'];
}

function csrf_verify(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['install_csrf']) && hash_equals($_SESSION['install_csrf'], $token);
}

function run_sql_file(PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException("Could not read $path");
    }
    // Strip line comments, then split into individual statements. Safe for our
    // own schema/seed files (no semicolons appear inside string literals there).
    $sql = preg_replace('/^--.*$/m', '', $sql);
    foreach (explode(";\n", $sql) as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }
        $pdo->exec($statement);
    }
}

function already_installed(string $host, string $name, string $user, string $pass): bool
{
    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$name;charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
        $count = (int) $pdo->query("SHOW TABLES LIKE 'admin_users'")->rowCount();
        if ($count === 0) {
            return false;
        }
        return (int) $pdo->query('SELECT COUNT(*) c FROM admin_users')->fetch()['c'] > 0;
    } catch (Exception $e) {
        return false;
    }
}

$configPath = __DIR__ . '/config/config.php';
$existingConfig = [];
if (file_exists($configPath)) {
    $contents = file_get_contents($configPath);
    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
        if (preg_match("/define\('$key',\s*'([^']*)'\)/", $contents, $m)) {
            $existingConfig[$key] = $m[1];
        }
    }
}

$alreadyDone = false;
if (!empty($existingConfig['DB_HOST']) && $existingConfig['DB_NAME'] !== 'yourcpaneluser_diamond') {
    $alreadyDone = already_installed(
        $existingConfig['DB_HOST'], $existingConfig['DB_NAME'],
        $existingConfig['DB_USER'] ?? '', $existingConfig['DB_PASS'] ?? ''
    );
}

$error = '';
$success = false;

if (!$alreadyDone && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired — please reload this page and try again.';
    } else {
        $dbHost = trim((string) ($_POST['db_host'] ?? 'localhost'));
        $dbName = trim((string) ($_POST['db_name'] ?? ''));
        $dbUser = trim((string) ($_POST['db_user'] ?? ''));
        $dbPass = (string) ($_POST['db_pass'] ?? '');
        $siteUrl = trim((string) ($_POST['site_url'] ?? ''));
        $adminUsername = trim((string) ($_POST['admin_username'] ?? ''));
        $adminEmail = trim((string) ($_POST['admin_email'] ?? ''));
        $adminPassword = (string) ($_POST['admin_password'] ?? '');
        $adminConfirm = (string) ($_POST['admin_confirm'] ?? '');

        if ($dbName === '' || $dbUser === '') {
            $error = 'Please fill in your database name and username.';
        } elseif ($adminUsername === '' || mb_strlen($adminUsername) < 3) {
            $error = 'Admin username must be at least 3 characters.';
        } elseif (mb_strlen($adminPassword) < 10) {
            $error = 'Admin password must be at least 10 characters.';
        } elseif ($adminPassword !== $adminConfirm) {
            $error = 'Admin passwords do not match.';
        } else {
            try {
                $pdo = new PDO(
                    "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
                    $dbUser,
                    $dbPass,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 8]
                );

                $existingAdmins = 0;
                try {
                    $existingAdmins = (int) $pdo->query('SELECT COUNT(*) c FROM admin_users')->fetch()['c'];
                } catch (Exception $e) {
                    // Table doesn't exist yet — fine, this is a fresh install.
                }

                if ($existingAdmins > 0) {
                    $error = 'This database already has an admin account. Installation already completed — delete install.php.';
                } else {
                    run_sql_file($pdo, __DIR__ . '/sql/schema.sql');

                    $hasServices = (int) $pdo->query('SELECT COUNT(*) c FROM services')->fetch()['c'];
                    if ($hasServices === 0) {
                        run_sql_file($pdo, __DIR__ . '/sql/seed.sql');
                    }

                    $hash = password_hash($adminPassword, PASSWORD_DEFAULT);
                    $pdo->prepare('INSERT INTO admin_users (username, email, password_hash, role) VALUES (:u, :e, :p, "owner")')
                        ->execute(['u' => $adminUsername, 'e' => $adminEmail, 'p' => $hash]);

                    $siteUrlFinal = $siteUrl !== '' ? $siteUrl : ('https://' . ($_SERVER['HTTP_HOST'] ?? 'yourdomain.com'));
                    $configContents = "<?php\n"
                        . "define('DB_HOST', " . var_export($dbHost, true) . ");\n"
                        . "define('DB_NAME', " . var_export($dbName, true) . ");\n"
                        . "define('DB_USER', " . var_export($dbUser, true) . ");\n"
                        . "define('DB_PASS', " . var_export($dbPass, true) . ");\n"
                        . "define('DB_CHARSET', 'utf8mb4');\n\n"
                        . "define('SITE_URL', " . var_export($siteUrlFinal, true) . ");\n"
                        . "define('SITE_TIMEZONE', 'America/Denver');\n\n"
                        . "define('RESEND_API_KEY', '');\n"
                        . "define('RESEND_FROM_EMAIL', 'Diamond Nails & Spa <bookings@yourdomain.com>');\n\n"
                        . "define('TWILIO_ACCOUNT_SID', '');\n"
                        . "define('TWILIO_AUTH_TOKEN', '');\n"
                        . "define('TWILIO_FROM_NUMBER', '');\n\n"
                        . "define('SESSION_NAME', 'diamond_admin_session');\n";

                    if (!is_writable(dirname($configPath)) && !is_writable($configPath)) {
                        $error = 'Database setup succeeded, but config/config.php is not writable. '
                            . 'Please edit it manually with these values — DB_HOST: ' . e($dbHost)
                            . ', DB_NAME: ' . e($dbName) . ', DB_USER: ' . e($dbUser) . ', DB_PASS: (as entered).';
                    } else {
                        file_put_contents($configPath, $configContents);
                        $success = true;
                    }
                }
            } catch (PDOException $e) {
                $error = 'Could not connect to that database: ' . $e->getMessage();
            }
        }
    }
}

function e(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Install — Diamond Nails & Spa</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; }
  body { margin:0; font-family:'Poppins',sans-serif; background:radial-gradient(circle at 20% 20%, rgba(217,169,136,0.16), transparent 45%), radial-gradient(circle at 80% 80%, rgba(110,68,87,0.14), transparent 50%), #f7f3ee; color:#2c211d; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
  .card { width:100%; max-width:560px; background:#fff; border-radius:22px; box-shadow:0 20px 50px -12px rgba(44,33,29,0.22); padding:40px; }
  h1 { font-family:'Playfair Display',serif; font-size:24px; margin:0 0 6px; }
  .sub { color:#6b584f; font-size:14px; margin:0 0 26px; }
  fieldset { border:1px solid #ece2d8; border-radius:14px; padding:18px; margin-bottom:20px; }
  legend { font-size:12.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#b8836a; padding:0 6px; }
  .row-2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
  .field { margin-bottom:14px; }
  .field:last-child { margin-bottom:0; }
  label { display:block; font-size:13px; font-weight:600; margin-bottom:6px; }
  input { width:100%; padding:11px 14px; border-radius:10px; border:1.5px solid #ece2d8; font-family:inherit; font-size:14px; background:#fbf7f2; }
  input:focus { outline:none; border-color:#b8836a; background:#fff; }
  .hint { font-size:12px; color:#9c8a80; margin-top:5px; }
  button { width:100%; padding:15px; border:none; border-radius:14px; background:linear-gradient(135deg,#d9a988,#b8836a 55%,#6e4457); color:#fff; font-size:15px; font-weight:700; cursor:pointer; font-family:inherit; }
  .alert { padding:12px 16px; border-radius:12px; font-size:13.5px; margin-bottom:20px; line-height:1.6; }
  .alert.error { background:#fbe9e7; color:#a8433c; }
  .alert.success { background:#e7f2e6; color:#4c7a4f; }
  a { color:#b8836a; }
</style>
</head>
<body>
  <div class="card">
    <h1>Diamond Nails &amp; Spa — Installer</h1>
    <p class="sub">One-time setup: creates your database tables, sample data, and admin login.</p>

    <?php if ($alreadyDone): ?>
      <div class="alert success">
        This site is already installed and has an admin account.<br><br>
        <a href="/admin/login.php">Go to admin login</a> · <a href="/">View public site</a><br><br>
        <strong>For security, please delete <code>install.php</code> from your server now.</strong>
      </div>
    <?php elseif ($success): ?>
      <div class="alert success">
        Installation complete! Tables created, sample services/staff seeded, and your admin account is ready.<br><br>
        <a href="/admin/login.php">Log in to the dashboard</a> · <a href="/">View your public site</a><br><br>
        <strong>Important: delete <code>install.php</code> from your server now</strong> — it has done its job and leaving it up is an unnecessary risk.
      </div>
    <?php else: ?>
      <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">

        <fieldset>
          <legend>Database (from cPanel → MySQL Databases)</legend>
          <div class="field">
            <label>Database Host</label>
            <input type="text" name="db_host" value="<?= e($_POST['db_host'] ?? 'localhost') ?>">
          </div>
          <div class="row-2">
            <div class="field">
              <label>Database Name</label>
              <input type="text" name="db_name" placeholder="cpaneluser_diamond" value="<?= e($_POST['db_name'] ?? '') ?>">
            </div>
            <div class="field">
              <label>Database User</label>
              <input type="text" name="db_user" placeholder="cpaneluser_dbuser" value="<?= e($_POST['db_user'] ?? '') ?>">
            </div>
          </div>
          <div class="field">
            <label>Database Password</label>
            <input type="password" name="db_pass" value="<?= e($_POST['db_pass'] ?? '') ?>">
          </div>
        </fieldset>

        <fieldset>
          <legend>Site</legend>
          <div class="field">
            <label>Site URL (optional — auto-detected if left blank)</label>
            <input type="text" name="site_url" placeholder="https://www.yourdomain.com" value="<?= e($_POST['site_url'] ?? '') ?>">
          </div>
        </fieldset>

        <fieldset>
          <legend>Your Admin Login</legend>
          <div class="row-2">
            <div class="field">
              <label>Username</label>
              <input type="text" name="admin_username" value="<?= e($_POST['admin_username'] ?? '') ?>">
            </div>
            <div class="field">
              <label>Email</label>
              <input type="email" name="admin_email" value="<?= e($_POST['admin_email'] ?? '') ?>">
            </div>
          </div>
          <div class="row-2">
            <div class="field">
              <label>Password</label>
              <input type="password" name="admin_password">
              <div class="hint">Minimum 10 characters.</div>
            </div>
            <div class="field">
              <label>Confirm Password</label>
              <input type="password" name="admin_confirm">
            </div>
          </div>
        </fieldset>

        <button type="submit">Install</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
