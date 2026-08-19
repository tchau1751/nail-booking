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
$fullName = trim((string) ($input['full_name'] ?? ''));
$title = trim((string) ($input['title'] ?? 'Nail Technician'));
$photoUrl = trim((string) ($input['photo_url'] ?? ''));
$bio = trim((string) ($input['bio'] ?? ''));
$colorHex = trim((string) ($input['color_hex'] ?? '#b8836a'));
$sortOrder = (int) ($input['sort_order'] ?? 0);

if ($fullName === '' || mb_strlen($fullName) > 150) {
    json_response(['ok' => false, 'error' => 'Please enter a valid staff name.'], 422);
}
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $colorHex)) {
    $colorHex = '#b8836a';
}

$pdo = get_db();

if ($id > 0) {
    $stmt = $pdo->prepare(
        'UPDATE staff SET full_name=:full_name, title=:title, photo_url=:photo_url, bio=:bio, color_hex=:color_hex, sort_order=:sort_order WHERE id=:id'
    );
    $stmt->execute([
        'full_name' => $fullName, 'title' => $title, 'photo_url' => $photoUrl, 'bio' => $bio !== '' ? $bio : null,
        'color_hex' => $colorHex, 'sort_order' => $sortOrder, 'id' => $id,
    ]);
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO staff (full_name, title, photo_url, bio, color_hex, sort_order, is_active) VALUES (:full_name, :title, :photo_url, :bio, :color_hex, :sort_order, 1)'
    );
    $stmt->execute([
        'full_name' => $fullName, 'title' => $title, 'photo_url' => $photoUrl, 'bio' => $bio !== '' ? $bio : null,
        'color_hex' => $colorHex, 'sort_order' => $sortOrder,
    ]);
    $id = (int) $pdo->lastInsertId();
}

json_response(['ok' => true, 'id' => $id]);
