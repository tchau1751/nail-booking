<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/uploads.php';

if (!current_admin()) {
    json_response(['ok' => false, 'error' => 'Not authenticated.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}
if (!admin_csrf_verify($_POST['csrf_token'] ?? null)) {
    json_response(['ok' => false, 'error' => 'Session expired — please refresh.'], 419);
}

$allowedSubdirs = ['services', 'staff', 'gallery', 'hero', 'about', 'kiosk', 'logo'];
$subdir = trim((string) ($_POST['subdir'] ?? 'gallery'));
if (!in_array($subdir, $allowedSubdirs, true)) {
    $subdir = 'gallery';
}

try {
    $url = handle_image_upload('image', $subdir);
    if (!$url) {
        json_response(['ok' => false, 'error' => 'No file was uploaded.'], 422);
    }
    json_response(['ok' => true, 'url' => $url]);
} catch (RuntimeException $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 422);
}
