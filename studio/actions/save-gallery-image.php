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
$imageUrl = trim((string) ($input['image_url'] ?? ''));
$altText = trim((string) ($input['alt_text'] ?? ''));
$layoutClass = trim((string) ($input['layout_class'] ?? ''));
$sortOrder = (int) ($input['sort_order'] ?? 0);

if ($imageUrl === '' || mb_strlen($imageUrl) > 500) {
    json_response(['ok' => false, 'error' => 'Please add a photo.'], 422);
}
if (!in_array($layoutClass, ['', 'wide', 'tall'], true)) {
    $layoutClass = '';
}

$pdo = get_db();

if ($id > 0) {
    $stmt = $pdo->prepare(
        'UPDATE gallery_images SET image_url=:img, alt_text=:alt, layout_class=:class, sort_order=:sort WHERE id=:id'
    );
    $stmt->execute(['img' => $imageUrl, 'alt' => $altText, 'class' => $layoutClass, 'sort' => $sortOrder, 'id' => $id]);
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO gallery_images (image_url, alt_text, layout_class, sort_order) VALUES (:img, :alt, :class, :sort)'
    );
    $stmt->execute(['img' => $imageUrl, 'alt' => $altText, 'class' => $layoutClass, 'sort' => $sortOrder]);
    $id = (int) $pdo->lastInsertId();
}

json_response(['ok' => true, 'id' => $id]);
