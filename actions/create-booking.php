<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notify.php';

ensure_public_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if (!csrf_verify($input['csrf_token'] ?? null)) {
    json_response(['ok' => false, 'error' => 'Your session expired — please refresh the page and try again.'], 419);
}

// Accepts either service_ids (array, 1-3 services for a combo booking) or
// the older single service_id, for backward compatibility.
$rawServiceIds = $input['service_ids'] ?? null;
if (!is_array($rawServiceIds) || empty($rawServiceIds)) {
    $single = (int) ($input['service_id'] ?? 0);
    $rawServiceIds = $single ? [$single] : [];
}

// Per-service technician: staff_ids maps service_id => staff_id (or null for
// "no preference"). Falls back to a single staff_id applied to every service
// for backward compatibility.
$rawStaffIds = is_array($input['staff_ids'] ?? null) ? $input['staff_ids'] : [];
$fallbackStaffId = (int) ($input['staff_id'] ?? 0);

$fullName = trim((string) ($input['full_name'] ?? ''));
$email = trim((string) ($input['email'] ?? ''));
$phone = trim((string) ($input['phone'] ?? ''));
$dobRaw = trim((string) ($input['date_of_birth'] ?? ''));
$date = trim((string) ($input['appointment_date'] ?? ''));
$time = trim((string) ($input['appointment_time'] ?? ''));
$notes = trim((string) ($input['notes'] ?? ''));

$errors = [];

$services = get_active_services_by_ids($rawServiceIds);
if (empty($services)) {
    $errors['service_id'] = 'Please choose at least one service.';
} elseif (count($services) > MAX_SERVICES_PER_BOOKING) {
    $errors['service_id'] = 'Please choose up to ' . MAX_SERVICES_PER_BOOKING . ' services.';
}

// Resolve a technician per service; validate any that were actually chosen.
$staffByService = [];
foreach ($services as $svc) {
    $sid = isset($rawStaffIds[(string) $svc['id']]) ? (int) $rawStaffIds[(string) $svc['id']] : $fallbackStaffId;
    if ($sid > 0) {
        $svcStaff = get_staff_by_id($sid);
        if (!$svcStaff || (int) $svcStaff['is_active'] !== 1) {
            $errors['staff_id'] = 'One of the selected technicians is no longer available — please pick another.';
        }
        $staffByService[$svc['id']] = $svcStaff;
    } else {
        $staffByService[$svc['id']] = null;
    }
}

if ($fullName === '' || mb_strlen($fullName) > 150) {
    $errors['full_name'] = 'Please enter your name.';
}
if ($email === '' && $phone === '') {
    $errors['contact'] = 'Please provide an email or phone number so we can confirm your visit.';
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'That email address doesn\'t look right.';
}
if ($phone !== '' && !preg_match('/^[0-9+()\-.\s]{7,20}$/', $phone)) {
    $errors['phone'] = 'That phone number doesn\'t look right.';
}

// Date of birth is optional — only validated if the client filled it in.
$dob = null;
if ($dobRaw !== '') {
    $dobObj = DateTime::createFromFormat('Y-m-d', $dobRaw);
    if (!$dobObj || $dobObj > new DateTime('today')) {
        $errors['dob'] = 'That date of birth doesn\'t look right.';
    } else {
        $dob = $dobObj->format('Y-m-d');
    }
}

$dateObj = DateTime::createFromFormat('Y-m-d', $date);
$today = new DateTime('today');
if (!$dateObj || $dateObj < $today) {
    $errors['appointment_date'] = 'Please choose a valid upcoming date.';
}

$timeObj = DateTime::createFromFormat('H:i', $time);
if (!$timeObj) {
    $errors['appointment_time'] = 'Please choose a time.';
}

if (!empty($errors)) {
    json_response(['ok' => false, 'errors' => $errors], 422);
}

$pdo = get_db();

try {
    $pdo->beginTransaction();

    $clientId = null;
    if ($email !== '' || $phone !== '') {
        $stmt = $pdo->prepare('SELECT id FROM clients WHERE (email <> "" AND email = :email) OR (phone <> "" AND phone = :phone) LIMIT 1');
        $stmt->execute(['email' => $email, 'phone' => $phone]);
        $existing = $stmt->fetch();

        if ($existing) {
            $clientId = (int) $existing['id'];
            if ($dob !== null) {
                $pdo->prepare('UPDATE clients SET full_name = :name, email = :email, phone = :phone, date_of_birth = :dob WHERE id = :id')
                    ->execute(['name' => $fullName, 'email' => $email, 'phone' => $phone, 'dob' => $dob, 'id' => $clientId]);
            } else {
                $pdo->prepare('UPDATE clients SET full_name = :name, email = :email, phone = :phone WHERE id = :id')
                    ->execute(['name' => $fullName, 'email' => $email, 'phone' => $phone, 'id' => $clientId]);
            }
        } else {
            $pdo->prepare('INSERT INTO clients (full_name, email, phone, date_of_birth) VALUES (:name, :email, :phone, :dob)')
                ->execute(['name' => $fullName, 'email' => $email, 'phone' => $phone, 'dob' => $dob]);
            $clientId = (int) $pdo->lastInsertId();
        }
    }

    $isCombo = count($services) > 1;
    $groupId = $isCombo ? new_booking_group_id() : null;
    $startDateTime = DateTime::createFromFormat('Y-m-d H:i:s', $dateObj->format('Y-m-d') . ' ' . $timeObj->format('H:i:s'));
    $slots = sequential_service_times($startDateTime, $services);

    $insertStmt = $pdo->prepare(
        'INSERT INTO bookings (client_id, service_id, staff_id, full_name, email, phone, appointment_date, appointment_time, notes, source, booking_group_id)
         VALUES (:client_id, :service_id, :staff_id, :full_name, :email, :phone, :appointment_date, :appointment_time, :notes, "website", :group_id)'
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
            'appointment_date' => $slot['date'],
            'appointment_time' => $slot['time'],
            'notes' => $notes !== '' ? $notes : null,
            'group_id' => $groupId,
        ]);
        $bookingIds[] = (int) $pdo->lastInsertId();
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Booking creation failed: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Something went wrong saving your booking. Please try again or call us.'], 500);
}

$primaryBooking = [
    'id' => $bookingIds[0],
    'full_name' => $fullName,
    'email' => $email,
    'phone' => $phone,
    'appointment_date' => $slots[0]['date'],
    'appointment_time' => $slots[0]['time'],
];

// If every service shares the same technician (or only one was chosen),
// notifications can name them directly; otherwise the "With:" line is
// omitted since a booking confirmation isn't the place for a full breakdown.
$distinctStaffIds = array_unique(array_filter(array_map(fn($s) => $s ? $s['id'] : null, $staffByService)));
$commonStaff = count($distinctStaffIds) === 1 ? reset($staffByService) : null;

$business = get_business_settings();
if ($isCombo) {
    send_combo_booking_notifications($primaryBooking, $services, $business, $commonStaff);
} else {
    send_booking_notifications($primaryBooking, $services[0], $business, $staffByService[$services[0]['id']] ?? null);
}

$totalMinutes = array_sum(array_map(fn($s) => (int) $s['duration_minutes'], $services));
$totalPrice = array_sum(array_map(fn($s) => (float) $s['price'], $services));

$staffLines = [];
foreach ($services as $svc) {
    $svcStaff = $staffByService[$svc['id']] ?? null;
    if ($svcStaff) {
        $staffLines[] = count($services) > 1 ? "{$svc['name']}: {$svcStaff['full_name']}" : $svcStaff['full_name'];
    }
}

json_response([
    'ok' => true,
    'booking' => [
        'id' => $bookingIds[0],
        'service_name' => implode(' + ', array_map(fn($s) => $s['name'], $services)),
        'full_name' => $fullName,
        'date_label' => $dateObj->format('l, F j, Y'),
        'time_label' => date('g:i A', strtotime($slots[0]['time'])),
        'duration' => format_duration($totalMinutes),
        'price' => format_price($totalPrice),
        'staff_name' => !empty($staffLines) ? implode(', ', $staffLines) : null,
    ],
]);
