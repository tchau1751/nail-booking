<?php
require_once __DIR__ . '/../includes/functions.php';

ensure_public_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

if (!csrf_verify($input['csrf_token'] ?? null)) {
    json_response(['ok' => false, 'error' => 'This kiosk session expired — please start over.'], 419);
}

$phone = trim((string) ($input['phone'] ?? ''));
$fullName = trim((string) ($input['full_name'] ?? ''));
$dobRaw = trim((string) ($input['date_of_birth'] ?? ''));
$discountId = (int) ($input['discount_id'] ?? 0);
$giftCardCode = trim((string) ($input['gift_card_code'] ?? ''));
$redeemPoints = !empty($input['redeem_points']);

// Date of birth is optional and keyed in as MM/DD/YYYY on the kiosk (not a
// native date picker) — only saved if it parses to a real, non-future date.
$dob = null;
if ($dobRaw !== '') {
    $dobObj = DateTime::createFromFormat('m/d/Y', $dobRaw);
    if ($dobObj && $dobObj <= new DateTime('today') && $dobObj->format('m/d/Y') === $dobRaw) {
        $dob = $dobObj->format('Y-m-d');
    }
}

// Accepts either service_ids (array, 1-3 services for a combo check-in) or
// the older single service_id, for backward compatibility.
$rawServiceIds = $input['service_ids'] ?? null;
if (!is_array($rawServiceIds) || empty($rawServiceIds)) {
    $single = (int) ($input['service_id'] ?? 0);
    $rawServiceIds = $single ? [$single] : [];
}

$digits = preg_replace('/\D+/', '', $phone);
if (mb_strlen($digits) < 7) {
    json_response(['ok' => false, 'error' => 'Please enter a valid phone number.'], 422);
}

$services = get_active_services_by_ids($rawServiceIds);
if (empty($services)) {
    json_response(['ok' => false, 'error' => 'Please choose a service.'], 422);
}

if ($fullName === '' || mb_strlen($fullName) > 150) {
    json_response(['ok' => false, 'error' => 'Please enter your name.'], 422);
}

$pdo = get_db();

// A single discount, if chosen, applies to the first service in the combo —
// the total (for reward points) still nets it out across the whole group.
$discountAmount = 0.0;
if ($discountId > 0) {
    $discStmt = $pdo->prepare('SELECT id, type, amount FROM discounts WHERE id = :id AND is_active = 1');
    $discStmt->execute(['id' => $discountId]);
    $discount = $discStmt->fetch();
    if ($discount) {
        $price = (float) $services[0]['price'];
        $discountAmount = $discount['type'] === 'percentage'
            ? round($price * ((float) $discount['amount'] / 100), 2)
            : (float) $discount['amount'];
        $discountAmount = min($discountAmount, $price);
    } else {
        $discountId = 0;
    }
}

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare('SELECT id FROM clients WHERE phone <> "" AND phone = :phone LIMIT 1');
    $stmt->execute(['phone' => $phone]);
    $existing = $stmt->fetch();

    if ($existing) {
        $clientId = (int) $existing['id'];
        if ($dob !== null) {
            $pdo->prepare('UPDATE clients SET full_name = :name, date_of_birth = :dob WHERE id = :id')
                ->execute(['name' => $fullName, 'dob' => $dob, 'id' => $clientId]);
        } else {
            $pdo->prepare('UPDATE clients SET full_name = :name WHERE id = :id')
                ->execute(['name' => $fullName, 'id' => $clientId]);
        }
    } else {
        $pdo->prepare('INSERT INTO clients (full_name, phone, date_of_birth) VALUES (:name, :phone, :dob)')
            ->execute(['name' => $fullName, 'phone' => $phone, 'dob' => $dob]);
        $clientId = (int) $pdo->lastInsertId();
    }

    $isCombo = count($services) > 1;
    $groupId = $isCombo ? new_booking_group_id() : null;
    $now = new DateTime();
    $slots = sequential_service_times($now, $services);

    $insertStmt = $pdo->prepare(
        "INSERT INTO bookings (client_id, service_id, discount_id, discount_amount, full_name, phone, appointment_date, appointment_time, status, checked_in_at, source, booking_group_id)
         VALUES (:client_id, :service_id, :discount_id, :discount_amount, :full_name, :phone, :date, :time, 'confirmed', NOW(), 'kiosk', :group_id)"
    );

    $bookingIds = [];
    foreach ($slots as $i => $slot) {
        $insertStmt->execute([
            'client_id' => $clientId,
            'service_id' => $slot['service']['id'],
            'discount_id' => ($i === 0 && $discountId > 0) ? $discountId : null,
            'discount_amount' => $i === 0 ? $discountAmount : 0,
            'full_name' => $fullName,
            'phone' => $phone,
            'date' => $slot['date'],
            'time' => $slot['time'],
            'group_id' => $groupId,
        ]);
        $bookingIds[] = (int) $pdo->lastInsertId();
    }

    $totalOwed = array_sum(array_map(fn($s) => (float) $s['price'], $services)) - $discountAmount;

    if ($isCombo) {
        $pointsEarned = award_group_checkin_points($pdo, $groupId, $bookingIds[0], $clientId, $totalOwed);
    } else {
        $pointsEarned = award_checkin_points($pdo, $bookingIds[0], $clientId, $totalOwed);
    }

    $remainingOwed = $totalOwed;

    $pointsRedeemed = null;
    $pointsRedeemedError = null;
    if ($redeemPoints && $remainingOwed > 0) {
        $ptsRedemption = redeem_reward_points($pdo, $clientId, $remainingOwed, $bookingIds[0], 'Kiosk self-service redemption');
        if ($ptsRedemption['ok']) {
            $pointsRedeemed = $ptsRedemption;
            $remainingOwed = max(0, $remainingOwed - $ptsRedemption['amount_value']);
            $pdo->prepare('UPDATE bookings SET points_redeemed_amount = :amt WHERE id = :id')
                ->execute(['amt' => $ptsRedemption['amount_value'], 'id' => $bookingIds[0]]);
        } else {
            $pointsRedeemedError = $ptsRedemption['error'];
        }
    }

    $giftCardApplied = null;
    $giftCardError = null;
    if ($giftCardCode !== '') {
        $redemption = redeem_gift_card($pdo, $giftCardCode, $remainingOwed, $bookingIds[0], 'Kiosk check-in');
        if ($redemption['ok']) {
            $giftCardApplied = $redemption['amount_applied'];
            $pdo->prepare('UPDATE bookings SET gift_card_id = :gcid, gift_card_amount = :amt WHERE id = :id')
                ->execute(['gcid' => $redemption['gift_card_id'], 'amt' => $giftCardApplied, 'id' => $bookingIds[0]]);
        } else {
            $giftCardError = $redemption['error'];
        }
    }

    $totalPoints = (int) $pdo->query('SELECT reward_points FROM clients WHERE id = ' . (int) $clientId)->fetchColumn();

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Kiosk check-in failed: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Something went wrong. Please ask the front desk for help.'], 500);
}

json_response([
    'ok' => true,
    'booking' => [
        'id' => $bookingIds[0],
        'full_name' => $fullName,
        'service_name' => implode(' + ', array_map(fn($s) => $s['name'], $services)),
        'points_earned' => $pointsEarned,
        'total_points' => $totalPoints,
        'gift_card_applied' => $giftCardApplied,
        'gift_card_error' => $giftCardError,
        'points_redeemed_amount' => $pointsRedeemed['amount_value'] ?? null,
        'points_redeemed_error' => $pointsRedeemedError,
    ],
]);
