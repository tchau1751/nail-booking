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
$direction = (string) ($input['direction'] ?? '');

if (!$id || !in_array($direction, ['up', 'down'], true)) {
    json_response(['ok' => false, 'error' => 'Invalid request.'], 422);
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT id, sort_order FROM menu_categories WHERE id = :id');
$stmt->execute(['id' => $id]);
$current = $stmt->fetch();
if (!$current) {
    json_response(['ok' => false, 'error' => 'Category not found.'], 404);
}

$neighborStmt = $direction === 'up'
    ? $pdo->prepare('SELECT id, sort_order FROM menu_categories WHERE sort_order < :sort ORDER BY sort_order DESC LIMIT 1')
    : $pdo->prepare('SELECT id, sort_order FROM menu_categories WHERE sort_order > :sort ORDER BY sort_order ASC LIMIT 1');
$neighborStmt->execute(['sort' => $current['sort_order']]);
$neighbor = $neighborStmt->fetch();

if ($neighbor) {
    $pdo->prepare('UPDATE menu_categories SET sort_order = :sort WHERE id = :id')
        ->execute(['sort' => $neighbor['sort_order'], 'id' => $current['id']]);
    $pdo->prepare('UPDATE menu_categories SET sort_order = :sort WHERE id = :id')
        ->execute(['sort' => $current['sort_order'], 'id' => $neighbor['id']]);
}

json_response(['ok' => true]);
