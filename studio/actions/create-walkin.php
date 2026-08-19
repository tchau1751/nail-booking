<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/notify.php';

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

$fullName = trim((string) ($input['full_name'] ?? ''));
$phone = trim((string) ($input['phone'] ?? ''));
$email = trim((string) ($input['email'] ?? ''));
$dobRaw = trim((string) ($input['date_of_birth'] ?? ''));

// Accepts either service_ids (array, 1-3 services for a combo walk-in) or
// the older single service_id, for backward compatibility.
$rawServiceIds = $input['service_ids'] ?? null;
if (!is_array($rawServiceIds) || empty($rawServiceIds)) {
    $single = (int) ($input['service_id'] ?? 0);
    $rawServiceIds = $single ? [$single] : [];
}

$services = get_active_services_by_ids($rawServiceIds);
if (empty($services)) {
    json_response(['ok' => false, 'error' => 'Please choose a service.'], 422);
}

// Per-service technician: staff_ids maps service_id => staff_id (or null for
// "no preference"). Falls back to a single staff_id applied to every service
// for backward compatibility with older clients.
$rawStaffIds = is_array($input['staff_ids'] ?? null) ? $input['staff_ids'] : [];
$fallbackStaffId = (int) ($input['staff_id'] ?? 0);
$staffByService = [];
foreach ($services as $svc) {
    $sid = isset($rawStaffIds[(string) $svc['id']]) ? (int) $rawStaffIds[(string) $svc['id']] : $fallbackStaffId;
    $staffByService[$svc['id']] = $sid > 0 ? get_staff_by_id($sid) : null;
}

if ($fullName === '') {
    json_response(['ok' => false, 'error' => 'Please enter the client\'s name.'], 422);
}

$dob = null;
if ($dobRaw !== '') {
    $dobObj = DateTime::createFromFormat('Y-m-d', $dobRaw);
    if (!$dobObj || $dobObj > new DateTime('today')) {
        json_response(['ok' => false, 'error' => 'That date of birth doesn\'t look right.'], 422);
    }
    $dob = $dobObj->format('Y-m-d');
}

$pdo = get_db();
$pdo->beginTransaction();

try {
    $clientId = null;
    if ($email !== '' || $phone !== '') {
        $stmt = $pdo->prepare('SELECT id FROM clients WHERE (email <> "" AND email = :email) OR (phone <> "" AND phone = :phone) LIMIT 1');
        $stmt->execute(['email' => $email, 'phone' => $phone]);
        $existing = $stmt->fetch();
        if ($existing) {
            $clientId = (int) $existing['id'];
            if ($dob !== null) {
                $pdo->prepare('UPDATE clients SET date_of_birth = :dob WHERE id = :id')
                    ->execute(['dob' => $dob, 'id' => $clientId]);
            }
        } else {
            $pdo->prepare('INSERT INTO clients (full_name, email, phone, date_of_birth) VALUES (:name, :email, :phone, :dob)')
                ->execute(['name' => $fullName, 'email' => $email, 'phone' => $phone, 'dob' => $dob]);
            $clientId = (int) $pdo->lastInsertId();
        }
    }

    $isCombo = count($services) > 1;
    $groupId = $isCombo ? new_booking_group_id() : null;
    $now = new DateTime();
    $slots = sequential_service_times($now, $services);

    $insertStmt = $pdo->prepare(
        "INSERT INTO bookings (client_id, service_id, staff_id, full_name, email, phone, appointment_date, appointment_time, status, checked_in_at, source, booking_group_id)
         VALUES (:client_id, :service_id, :staff_id, :full_name, :email, :phone, :date, :time, 'confirmed', NOW(), 'walk-in', :group_id)"
    );

    $bookingIds = [];
    foreach ($slots as $slot) {
        $slotStaff = $staffByService[$slot['service']['id']] ?? null;
        $insertStmt->execute([
            'client_id' => $clientId,
            'service_id' => $slot['service']['id'],
            'staff_id' => $slotStaff ? $slotStaff['id'] : null,
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'date' => $slot['date'],
            'time' => $slot['time'],
            'group_id' => $groupId,
        ]);
        $bookingIds[] = (int) $pdo->lastInsertId();
    }

    $pointsEarned = 0;
    if ($clientId) {
        if ($isCombo) {
            $totalAmount = array_sum(array_map(fn($s) => (float) $s['price'], $services));
            $pointsEarned = award_group_checkin_points($pdo, $groupId, $bookingIds[0], $clientId, $totalAmount);
        } else {
            $pointsEarned = award_checkin_points($pdo, $bookingIds[0], $clientId, (float) $services[0]['price']);
        }
    }

    $pdo->commit();

    $totalPoints = $clientId ? (int) $pdo->query('SELECT reward_points FROM clients WHERE id = ' . (int) $clientId)->fetchColumn() : 0;
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Walk-in creation failed: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Could not save this walk-in. Please try again.'], 500);
}

json_response(['ok' => true, 'booking_id' => $bookingIds[0], 'points_earned' => $pointsEarned, 'total_points' => $totalPoints]);
