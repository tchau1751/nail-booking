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

$id = (int) ($input['id'] ?? 0);
if (!$id) {
    json_response(['ok' => false, 'error' => 'Invalid photo.'], 422);
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT image_url FROM gallery_images WHERE id = :id');
$stmt->execute(['id' => $id]);
$row = $stmt->fetch();

$pdo->prepare('DELETE FROM gallery_images WHERE id = :id')->execute(['id' => $id]);

// Best-effort cleanup of the uploaded file, if it's one of ours.
if ($row && str_starts_with($row['image_url'], '/uploads/gallery/')) {
    $path = __DIR__ . '/../../' . ltrim($row['image_url'], '/');
    if (is_file($path)) {
        @unlink($path);
    }
}

json_response(['ok' => true]);
