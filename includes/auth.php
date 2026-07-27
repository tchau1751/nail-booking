<?php
require_once __DIR__ . '/db.php';

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/' . SUBFOLDER . '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function isLoggedIn(): bool {
    startSecureSession();
    return !empty($_SESSION['admin_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_PATH . '/admin/login.php');
        exit;
    }
}

function currentAdmin(): ?array {
    if (!isLoggedIn()) return null;
    return fetchOne('SELECT * FROM admin_users WHERE id=?', [$_SESSION['admin_id']]);
}

function loginAdmin(string $email, string $password): bool {
    $a = fetchOne('SELECT * FROM admin_users WHERE email=? AND is_active=1', [$email]);
    if (!$a || !password_verify($password, $a['password_hash'])) return false;
    startSecureSession();
    session_regenerate_id(true);
    $_SESSION['admin_id']   = $a['id'];
    $_SESSION['admin_name'] = $a['name'];
    $_SESSION['admin_role'] = $a['role'];
    return true;
}

function logoutAdmin(): void {
    startSecureSession();
    $_SESSION = [];
    session_destroy();
}
