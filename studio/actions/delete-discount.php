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
    json_response(['ok' => false, 'error' => 'Invalid discount.'], 422);
}

$pdo = get_db();
// bookings.discount_id has ON DELETE SET NULL, so past bookings keep their
// already-applied discount_amount even after the rule itself is removed.
$pdo->prepare('DELETE FROM discounts WHERE id = :id')->execute(['id' => $id]);

json_response(['ok' => true]);
