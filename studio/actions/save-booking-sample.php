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
$phone = trim((string) ($input['phone'] ?? ''));
$email = trim((string) ($input['email'] ?? ''));
$serviceId = (int) ($input['service_id'] ?? 0);
$staffId = (int) ($input['staff_id'] ?? 0);
$discountId = (int) ($input['discount_id'] ?? 0);
$date = trim((string) ($input['appointment_date'] ?? ''));
$time = trim((string) ($input['appointment_time'] ?? ''));
$status = trim((string) ($input['status'] ?? 'confirmed'));
$notes = trim((string) ($input['notes'] ?? ''));

$allowedStatus = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];

if ($fullName === '' || mb_strlen($fullName) > 150) {
    json_response(['ok' => false, 'error' => 'Please enter the client\'s name.'], 422);
}
if ($phone === '' && $email === '') {
    json_response(['ok' => false, 'error' => 'Please provide a phone number or email.'], 422);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['ok' => false, 'error' => 'Please enter a valid email address.'], 422);
}

$pdo = get_db();

$serviceStmt = $pdo->prepare('SELECT id, price FROM services WHERE id = :id');
$serviceStmt->execute(['id' => $serviceId]);
$service = $serviceStmt->fetch();
if (!$service) {
    json_response(['ok' => false, 'error' => 'Please choose a valid service.'], 422);
}

if ($staffId > 0) {
    $staffStmt = $pdo->prepare('SELECT id FROM staff WHERE id = :id');
    $staffStmt->execute(['id' => $staffId]);
    if (!$staffStmt->fetch()) {
        json_response(['ok' => false, 'error' => 'Please choose a valid technician.'], 422);
    }
} else {
    $staffId = null;
}

$discountAmount = 0.0;
if ($discountId > 0) {
    $discStmt = $pdo->prepare('SELECT id, type, amount FROM discounts WHERE id = :id AND is_active = 1');
    $discStmt->execute(['id' => $discountId]);
    $discount = $discStmt->fetch();
    if (!$discount) {
        json_response(['ok' => false, 'error' => 'That discount is no longer available.'], 422);
    }
    $price = (float) $service['price'];
    $discountAmount = $discount['type'] === 'percentage'
        ? round($price * ((float) $discount['amount'] / 100), 2)
        : (float) $discount['amount'];
    $discountAmount = min($discountAmount, $price);
} else {
    $discountId = null;
}

if (!in_array($status, $allowedStatus, true)) {
    json_response(['ok' => false, 'error' => 'Invalid status.'], 422);
}

$dateObj = DateTime::createFromFormat('Y-m-d', $date);
if (!$dateObj) {
    json_response(['ok' => false, 'error' => 'Please choose a valid date.'], 422);
}
$timeObj = DateTime::createFromFormat('H:i', $time);
if (!$timeObj) {
    json_response(['ok' => false, 'error' => 'Please choose a valid time.'], 422);
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare(
            'UPDATE bookings SET full_name=:full_name, phone=:phone, email=:email, service_id=:service_id,
             staff_id=:staff_id, discount_id=:discount_id, discount_amount=:discount_amount,
             appointment_date=:date, appointment_time=:time, status=:status, notes=:notes
             WHERE id=:id'
        );
        $stmt->execute([
            'full_name' => $fullName, 'phone' => $phone, 'email' => $email,
            'service_id' => $serviceId, 'staff_id' => $staffId,
            'discount_id' => $discountId, 'discount_amount' => $discountAmount,
            'date' => $dateObj->format('Y-m-d'), 'time' => $timeObj->format('H:i:s'),
            'status' => $status, 'notes' => $notes !== '' ? $notes : null, 'id' => $id,
        ]);
    } else {
        $pdo->beginTransaction();

        $clientId = null;
        if ($email !== '' || $phone !== '') {
            $stmt = $pdo->prepare('SELECT id FROM clients WHERE (email <> "" AND email = :email) OR (phone <> "" AND phone = :phone) LIMIT 1');
            $stmt->execute(['email' => $email, 'phone' => $phone]);
            $existing = $stmt->fetch();
            if ($existing) {
                $clientId = (int) $existing['id'];
            } else {
                $pdo->prepare('INSERT INTO clients (full_name, email, phone) VALUES (:name, :email, :phone)')
                    ->execute(['name' => $fullName, 'email' => $email, 'phone' => $phone]);
                $clientId = (int) $pdo->lastInsertId();
            }
        }

        $stmt = $pdo->prepare(
            "INSERT INTO bookings (client_id, service_id, staff_id, discount_id, discount_amount, full_name, email, phone, appointment_date, appointment_time, status, notes, source)
             VALUES (:client_id, :service_id, :staff_id, :discount_id, :discount_amount, :full_name, :email, :phone, :date, :time, :status, :notes, 'phone')"
        );
        $stmt->execute([
            'client_id' => $clientId, 'service_id' => $serviceId, 'staff_id' => $staffId,
            'discount_id' => $discountId, 'discount_amount' => $discountAmount,
            'full_name' => $fullName, 'email' => $email, 'phone' => $phone,
            'date' => $dateObj->format('Y-m-d'), 'time' => $timeObj->format('H:i:s'),
            'status' => $status, 'notes' => $notes !== '' ? $notes : null,
        ]);
        $id = (int) $pdo->lastInsertId();

        $pdo->commit();
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Save booking failed: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Could not save this appointment. Please try again.'], 500);
}

json_response(['ok' => true, 'id' => $id, 'discount_amount' => $discountAmount]);
