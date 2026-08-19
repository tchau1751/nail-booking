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
$name = trim((string) ($input['name'] ?? ''));
$subtitle = trim((string) ($input['subtitle'] ?? ''));

if ($name === '' || mb_strlen($name) > 150) {
    json_response(['ok' => false, 'error' => 'Please enter a valid category name.'], 422);
}

$pdo = get_db();

if ($id > 0) {
    $pdo->prepare('UPDATE menu_categories SET name=:name, subtitle=:subtitle WHERE id=:id')
        ->execute(['name' => $name, 'subtitle' => $subtitle !== '' ? $subtitle : null, 'id' => $id]);
} else {
    $nextSort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM menu_categories')->fetchColumn();
    $pdo->prepare('INSERT INTO menu_categories (name, subtitle, sort_order) VALUES (:name, :subtitle, :sort)')
        ->execute(['name' => $name, 'subtitle' => $subtitle !== '' ? $subtitle : null, 'sort' => $nextSort]);
    $id = (int) $pdo->lastInsertId();
}

json_response(['ok' => true, 'id' => $id]);
