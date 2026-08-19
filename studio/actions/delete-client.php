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
    json_response(['ok' => false, 'error' => 'Invalid client.'], 422);
}

// Safe to hard-delete: bookings.client_id is ON DELETE SET NULL, and each
// booking already stores its own copy of full_name/email/phone at the time
// it was made, so booking history and the calendar are unaffected.
$pdo = get_db();
$pdo->prepare('DELETE FROM clients WHERE id = :id')->execute(['id' => $id]);

json_response(['ok' => true]);
