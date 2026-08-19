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
$categoryId = (int) ($input['category_id'] ?? 0);
$name = trim((string) ($input['name'] ?? ''));
$priceLabel = trim((string) ($input['price_label'] ?? ''));
$description = trim((string) ($input['description'] ?? ''));

if ($name === '' || mb_strlen($name) > 255) {
    json_response(['ok' => false, 'error' => 'Please enter a valid item name.'], 422);
}
if (mb_strlen($priceLabel) > 50) {
    json_response(['ok' => false, 'error' => 'Price label is too long.'], 422);
}
if (!$categoryId) {
    json_response(['ok' => false, 'error' => 'Invalid category.'], 422);
}

$pdo = get_db();

if ($id > 0) {
    $pdo->prepare('UPDATE menu_items SET name=:name, price_label=:price, description=:description WHERE id=:id')
        ->execute([
            'name' => $name,
            'price' => $priceLabel !== '' ? $priceLabel : null,
            'description' => $description !== '' ? $description : null,
            'id' => $id,
        ]);
} else {
    $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_sort FROM menu_items WHERE category_id = :cat');
    $sortStmt->execute(['cat' => $categoryId]);
    $nextSort = (int) $sortStmt->fetch()['next_sort'];

    $pdo->prepare('INSERT INTO menu_items (category_id, name, price_label, description, sort_order) VALUES (:cat, :name, :price, :description, :sort)')
        ->execute([
            'cat' => $categoryId,
            'name' => $name,
            'price' => $priceLabel !== '' ? $priceLabel : null,
            'description' => $description !== '' ? $description : null,
            'sort' => $nextSort,
        ]);
    $id = (int) $pdo->lastInsertId();
}

json_response(['ok' => true, 'id' => $id]);
