<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(SESSION_NAME);
    // DB-backed storage — this host has multiple stateless app instances
    // with no shared filesystem, so default file sessions don't persist
    // reliably across requests (which would randomly log admins out).
    require_once __DIR__ . '/../../includes/db_session_handler.php';
    session_set_save_handler(new DbSessionHandler(get_db()), true);
    session_start();
}

function current_admin(): ?array
{
    return $_SESSION['admin_user'] ?? null;
}

function require_login(): void
{
    if (!current_admin()) {
        header('Location: /studio/login.php');
        exit;
    }
}

function admin_csrf_token(): string
{
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf'];
}

function admin_csrf_verify(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['admin_csrf']) && hash_equals($_SESSION['admin_csrf'], $token);
}
