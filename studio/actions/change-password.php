<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!current_admin()) {
    json_response(['ok' => false, 'error' => 'Not authenticated.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

if (!admin_csrf_verify($input['csrf_token'] ?? null)) {
    json_response(['ok' => false, 'error' => 'Session expired — please refresh.'], 419);
}

$current = (string) ($input['current_password'] ?? '');
$new = (string) ($input['new_password'] ?? '');
$confirm = (string) ($input['confirm_password'] ?? '');

if (mb_strlen($new) < 10) {
    json_response(['ok' => false, 'error' => 'New password must be at least 10 characters.'], 422);
}
if ($new !== $confirm) {
    json_response(['ok' => false, 'error' => 'New passwords do not match.'], 422);
}

$admin = current_admin();
$pdo = get_db();
$stmt = $pdo->prepare('SELECT * FROM admin_users WHERE id = :id');
$stmt->execute(['id' => $admin['id']]);
$user = $stmt->fetch();

if (!$user || !password_verify($current, $user['password_hash'])) {
    json_response(['ok' => false, 'error' => 'Current password is incorrect.'], 422);
}

$hash = password_hash($new, PASSWORD_DEFAULT);
$pdo->prepare('UPDATE admin_users SET password_hash = :hash WHERE id = :id')->execute(['hash' => $hash, 'id' => $admin['id']]);

json_response(['ok' => true]);
