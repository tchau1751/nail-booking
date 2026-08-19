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
$type = ($input['type'] ?? '') === 'percentage' ? 'percentage' : 'flat';
$amount = (float) ($input['amount'] ?? 0);
$isActive = !empty($input['is_active']) ? 1 : 0;

if ($name === '' || mb_strlen($name) > 100) {
    json_response(['ok' => false, 'error' => 'Please enter a name for this discount.'], 422);
}
if ($amount <= 0) {
    json_response(['ok' => false, 'error' => 'Please enter an amount greater than zero.'], 422);
}
if ($type === 'percentage' && $amount > 100) {
    json_response(['ok' => false, 'error' => 'Percentage discounts cannot exceed 100%.'], 422);
}

$pdo = get_db();

if ($id > 0) {
    $stmt = $pdo->prepare('UPDATE discounts SET name=:name, type=:type, amount=:amount, is_active=:active WHERE id=:id');
    $stmt->execute(['name' => $name, 'type' => $type, 'amount' => $amount, 'active' => $isActive, 'id' => $id]);
} else {
    $stmt = $pdo->prepare('INSERT INTO discounts (name, type, amount, is_active) VALUES (:name, :type, :amount, :active)');
    $stmt->execute(['name' => $name, 'type' => $type, 'amount' => $amount, 'active' => $isActive]);
    $id = (int) $pdo->lastInsertId();
}

json_response(['ok' => true, 'id' => $id]);
