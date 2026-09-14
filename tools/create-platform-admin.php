<?php
// ============================================================
//  Create the first platform admin, or reset one's password.
//
//  Command line only — this is how the first key to every salon is
//  cut, so it is never reachable from a browser.
//
//    php tools/create-platform-admin.php you@example.com "Your Name"
//
//  It asks for the password on the next line.
// ============================================================
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

chdir(dirname(__DIR__));
require 'includes/platform.php';

$email = strtolower(trim($argv[1] ?? ''));
$name  = trim($argv[2] ?? '') ?: 'Platform admin';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php tools/create-platform-admin.php you@example.com \"Your Name\"\n");
    exit(2);
}

fwrite(STDOUT, "Password for $email (at least 12 characters): ");
$password = rtrim((string)fgets(STDIN), "\r\n");
if (strlen($password) < 12) {
    fwrite(STDERR, "That password is too short.\n");
    exit(2);
}

db();   // brings the schema up to date first
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$existing = fetchOne('SELECT id FROM platform_admins WHERE email=?', [$email]);
if ($existing) {
    query('UPDATE platform_admins SET password_hash=?, name=?, is_active=1 WHERE id=?', [$hash, $name, $existing['id']]);
    platformAudit('admin_password_reset', $email . ' (command line)', null, (int)$existing['id']);
    echo "Password reset for $email.\n";
} else {
    query('INSERT INTO platform_admins (name, email, password_hash) VALUES (?,?,?)', [$name, $email, $hash]);
    platformAudit('admin_created', $email . ' (command line)', null, (int)db()->lastInsertId());
    echo "Platform admin created for $email. Sign in at " . BASE_PATH . "/platform/login.php\n";
}
