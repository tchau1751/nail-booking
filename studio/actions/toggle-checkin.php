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

$bookingId = (int) ($input['booking_id'] ?? 0);
$action = (string) ($input['action'] ?? '');

if (!$bookingId || !in_array($action, ['check_in', 'undo'], true)) {
    json_response(['ok' => false, 'error' => 'Invalid request.'], 422);
}

$pdo = get_db();
$pointsEarned = 0;
$totalPoints = 0;
$clientName = '';

if ($action === 'check_in') {
    $stmt = $pdo->prepare("UPDATE bookings SET checked_in_at = NOW(), status = IF(status = 'pending', 'confirmed', status) WHERE id = :id");
    $stmt->execute(['id' => $bookingId]);

    $info = $pdo->prepare(
        'SELECT b.client_id, b.full_name, b.discount_amount, b.booking_group_id, s.price
         FROM bookings b JOIN services s ON s.id = b.service_id
         WHERE b.id = :id'
    );
    $info->execute(['id' => $bookingId]);
    $row = $info->fetch();
    if ($row) {
        $clientName = $row['full_name'];
        $clientId = $row['client_id'] ? (int) $row['client_id'] : null;
        if (!empty($row['booking_group_id'])) {
            // Combo booking (2-3 services back-to-back): award once on the
            // combined total, no matter which service in the group gets
            // checked in first.
            $group = sum_booking_group($pdo, $row['booking_group_id']);
            $pointsEarned = award_group_checkin_points($pdo, $row['booking_group_id'], $bookingId, $group['client_id'] ?? $clientId, $group['amount']);
        } else {
            $finalAmount = (float) $row['price'] - (float) ($row['discount_amount'] ?? 0);
            $pointsEarned = award_checkin_points($pdo, $bookingId, $clientId, $finalAmount);
        }
        if ($clientId) {
            $totalPoints = (int) $pdo->query('SELECT reward_points FROM clients WHERE id = ' . (int) $clientId)->fetchColumn();
        }
    }
} else {
    $groupStmt = $pdo->prepare('SELECT booking_group_id FROM bookings WHERE id = :id');
    $groupStmt->execute(['id' => $bookingId]);
    $groupId = $groupStmt->fetchColumn();

    $stmt = $pdo->prepare('UPDATE bookings SET checked_in_at = NULL WHERE id = :id');
    $stmt->execute(['id' => $bookingId]);

    if ($groupId) {
        revoke_group_checkin_points($pdo, $groupId);
    } else {
        revoke_checkin_points($pdo, $bookingId);
    }
}

json_response(['ok' => true, 'points_earned' => $pointsEarned, 'total_points' => $totalPoints, 'client_name' => $clientName]);
