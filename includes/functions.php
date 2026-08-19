<?php
require_once __DIR__ . '/../config/db.php';

date_default_timezone_set(defined('SITE_TIMEZONE') ? SITE_TIMEZONE : 'America/Denver');

function get_business_settings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $pdo = get_db();
    $row = $pdo->query('SELECT * FROM business_settings ORDER BY id ASC LIMIT 1')->fetch();

    $settings = $row ?: [
        'business_name' => 'Diamond Nails & Spa',
        'business_email' => '',
        'business_phone' => '',
        'business_address' => '',
        'hours_note' => '',
        'instagram_url' => '',
        'facebook_url' => '',
        'booking_notice' => '',
    ];

    return $settings;
}

function get_active_services(): array
{
    $pdo = get_db();
    $stmt = $pdo->query(
        'SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
    );
    return $stmt->fetchAll();
}

function get_service_by_id(int $id): ?array
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM services WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_active_staff(): array
{
    $pdo = get_db();
    $stmt = $pdo->query(
        'SELECT * FROM staff WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
    );
    return $stmt->fetchAll();
}

function get_staff_by_id(int $id): ?array
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM staff WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Multi-service bookings (e.g. Acrylic + Pedicure + Eyebrow Wax booked
 * together): up to 3 active services, de-duplicated, in the order given.
 */
const MAX_SERVICES_PER_BOOKING = 3;

function get_active_services_by_ids(array $ids): array
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $ids = array_slice($ids, 0, MAX_SERVICES_PER_BOOKING);
    $services = [];
    foreach ($ids as $id) {
        $svc = get_service_by_id($id);
        if ($svc && (int) $svc['is_active'] === 1) {
            $services[] = $svc;
        }
    }
    return $services;
}

/** A short id linking multiple booking rows created together as one combo. */
function new_booking_group_id(): string
{
    return bin2hex(random_bytes(16));
}

/**
 * Lays multiple services back-to-back starting at $start, each one
 * beginning right as the previous ends. Takes a full date+time so a combo
 * that runs past midnight correctly rolls its later slots onto the next
 * calendar date rather than leaving them mis-dated on the start date.
 * Returns [['service' => ..., 'date' => 'Y-m-d', 'time' => 'H:i:s'], ...].
 */
function sequential_service_times(DateTime $start, array $services): array
{
    $cursor = clone $start;

    $slots = [];
    foreach ($services as $svc) {
        $slots[] = ['service' => $svc, 'date' => $cursor->format('Y-m-d'), 'time' => $cursor->format('H:i:s')];
        $cursor->modify('+' . (int) $svc['duration_minutes'] . ' minutes');
    }
    return $slots;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function format_price(float $price): string
{
    return '$' . number_format($price, 0);
}

function format_duration(int $minutes): string
{
    if ($minutes < 60) {
        return $minutes . ' min';
    }
    $hours = intdiv($minutes, 60);
    $rest = $minutes % 60;
    return $rest > 0 ? sprintf('%dh %dm', $hours, $rest) : sprintf('%dh', $hours);
}

/**
 * Reward points: 1 stamp per visit (regardless of amount spent), awarded
 * once per booking at check-in. booking_id has a UNIQUE key in
 * client_points_log, so calling this twice for the same booking (e.g. a
 * staff member re-clicking check-in) is a harmless no-op — the duplicate
 * insert is caught and ignored.
 */
const REWARD_POINTS_PER_VISIT = 1;

function award_checkin_points(PDO $pdo, int $bookingId, ?int $clientId, float $amount): int
{
    if (!$clientId) {
        return 0;
    }
    $points = REWARD_POINTS_PER_VISIT;

    try {
        $pdo->prepare(
            'INSERT INTO client_points_log (client_id, booking_id, points_earned, amount) VALUES (:cid, :bid, :pts, :amt)'
        )->execute(['cid' => $clientId, 'bid' => $bookingId, 'pts' => $points, 'amt' => $amount]);
    } catch (PDOException $e) {
        return 0; // already awarded for this booking
    }

    $pdo->prepare('UPDATE clients SET reward_points = reward_points + :pts WHERE id = :cid')
        ->execute(['pts' => $points, 'cid' => $clientId]);

    return $points;
}

/** Reverses points previously awarded for a booking (e.g. an undone check-in). */
function revoke_checkin_points(PDO $pdo, int $bookingId): void
{
    $stmt = $pdo->prepare('SELECT client_id, points_earned FROM client_points_log WHERE booking_id = :bid');
    $stmt->execute(['bid' => $bookingId]);
    $row = $stmt->fetch();
    if (!$row) {
        return;
    }

    $pdo->prepare('DELETE FROM client_points_log WHERE booking_id = :bid')->execute(['bid' => $bookingId]);
    $pdo->prepare('UPDATE clients SET reward_points = GREATEST(0, reward_points - :pts) WHERE id = :cid')
        ->execute(['pts' => $row['points_earned'], 'cid' => $row['client_id']]);
}

/**
 * Same idea as award_checkin_points() but for a multi-service combo booking
 * (e.g. Acrylic + Pedicure + Eyebrow Wax): still just 1 stamp for the whole
 * visit, not once per service. booking_group_id has a UNIQUE key, so
 * checking in a second service from the same combo is a harmless no-op.
 */
function award_group_checkin_points(PDO $pdo, string $groupId, int $representativeBookingId, ?int $clientId, float $totalAmount): int
{
    if (!$clientId) {
        return 0;
    }
    $points = REWARD_POINTS_PER_VISIT;

    try {
        $pdo->prepare(
            'INSERT INTO client_points_log (client_id, booking_id, booking_group_id, points_earned, amount) VALUES (:cid, :bid, :gid, :pts, :amt)'
        )->execute(['cid' => $clientId, 'bid' => $representativeBookingId, 'gid' => $groupId, 'pts' => $points, 'amt' => $totalAmount]);
    } catch (PDOException $e) {
        return 0; // already awarded for this group
    }

    $pdo->prepare('UPDATE clients SET reward_points = reward_points + :pts WHERE id = :cid')
        ->execute(['pts' => $points, 'cid' => $clientId]);

    return $points;
}

/** Reverses points previously awarded for a combo booking group. */
function revoke_group_checkin_points(PDO $pdo, string $groupId): void
{
    $stmt = $pdo->prepare('SELECT client_id, points_earned FROM client_points_log WHERE booking_group_id = :gid');
    $stmt->execute(['gid' => $groupId]);
    $row = $stmt->fetch();
    if (!$row) {
        return;
    }

    $pdo->prepare('DELETE FROM client_points_log WHERE booking_group_id = :gid')->execute(['gid' => $groupId]);
    $pdo->prepare('UPDATE clients SET reward_points = GREATEST(0, reward_points - :pts) WHERE id = :cid')
        ->execute(['pts' => $row['points_earned'], 'cid' => $row['client_id']]);
}

/** Sums (service price - discount) across every booking row sharing a group, for points/notification purposes. */
function sum_booking_group(PDO $pdo, string $groupId): array
{
    $stmt = $pdo->prepare(
        'SELECT b.client_id, b.discount_amount, s.price
         FROM bookings b JOIN services s ON s.id = b.service_id
         WHERE b.booking_group_id = :gid'
    );
    $stmt->execute(['gid' => $groupId]);
    $rows = $stmt->fetchAll();

    $total = 0.0;
    $clientId = null;
    foreach ($rows as $row) {
        $total += (float) $row['price'] - (float) ($row['discount_amount'] ?? 0);
        $clientId = $clientId ?? ($row['client_id'] ? (int) $row['client_id'] : null);
    }
    return ['amount' => $total, 'client_id' => $clientId];
}

/**
 * Gift cards: issued by staff after an in-person sale (this app has no
 * online payment processing), each with a unique code and a balance that
 * shrinks as it's redeemed toward services at check-in.
 */
function generate_gift_card_code(PDO $pdo): string
{
    // Excludes visually-ambiguous characters (0/O, 1/I/L) for codes read off a
    // physical card or typed in by a client.
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    do {
        $part = function () use ($chars) {
            $s = '';
            for ($i = 0; $i < 4; $i++) {
                $s .= $chars[random_int(0, strlen($chars) - 1)];
            }
            return $s;
        };
        $code = 'DN-' . $part() . '-' . $part();
        $stmt = $pdo->prepare('SELECT id FROM gift_cards WHERE code = :code');
        $stmt->execute(['code' => $code]);
    } while ($stmt->fetch());
    return $code;
}

/**
 * Applies up to $requestedAmount from a gift card's balance, logging the
 * redemption. Returns ['ok' => bool, 'error'?, 'amount_applied', 'remaining_balance', 'gift_card_id', 'code'].
 */
function redeem_gift_card(PDO $pdo, string $code, float $requestedAmount, ?int $bookingId = null, string $note = ''): array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return ['ok' => false, 'error' => 'Please enter a gift card code.'];
    }

    $stmt = $pdo->prepare('SELECT * FROM gift_cards WHERE code = :code');
    $stmt->execute(['code' => $code]);
    $card = $stmt->fetch();

    if (!$card) {
        return ['ok' => false, 'error' => 'Gift card code not found.'];
    }
    if ($card['status'] !== 'active') {
        return ['ok' => false, 'error' => 'This gift card is no longer active.'];
    }
    if ((float) $card['balance'] <= 0) {
        return ['ok' => false, 'error' => 'This gift card has no remaining balance.'];
    }

    $amount = min((float) $card['balance'], max(0, $requestedAmount));
    if ($amount <= 0) {
        return ['ok' => false, 'error' => 'Nothing to redeem.'];
    }

    $newBalance = round((float) $card['balance'] - $amount, 2);
    $newStatus = $newBalance <= 0 ? 'redeemed' : 'active';

    $pdo->prepare('UPDATE gift_cards SET balance = :bal, status = :status, updated_at = NOW() WHERE id = :id')
        ->execute(['bal' => $newBalance, 'status' => $newStatus, 'id' => $card['id']]);
    $pdo->prepare('INSERT INTO gift_card_redemptions (gift_card_id, booking_id, amount_used, balance_after, note) VALUES (:gcid, :bid, :amt, :bal, :note)')
        ->execute(['gcid' => $card['id'], 'bid' => $bookingId, 'amt' => $amount, 'bal' => $newBalance, 'note' => $note !== '' ? $note : null]);

    return [
        'ok' => true,
        'amount_applied' => $amount,
        'remaining_balance' => $newBalance,
        'gift_card_id' => (int) $card['id'],
        'code' => $card['code'],
    ];
}

/**
 * Reward point redemption: 10 stamps = $10 off, redeemed in whole 10-point
 * blocks. A client with 24 points redeeming "all" gets 20 points -> $20 off,
 * leaving 4 points on their balance (never a fractional/partial block).
 */
const REWARD_REDEMPTION_POINTS_PER_UNIT = 10;
const REWARD_REDEMPTION_DOLLARS_PER_UNIT = 10.0;

/**
 * Redeems up to $requestedAmount worth of a client's points (rounded down to
 * the nearest whole 10-point block), logging the redemption. Returns
 * ['ok' => bool, 'error'?, 'points_redeemed', 'amount_value', 'remaining_points'].
 */
function redeem_reward_points(PDO $pdo, int $clientId, float $requestedAmount, ?int $bookingId = null, string $note = ''): array
{
    $stmt = $pdo->prepare('SELECT reward_points FROM clients WHERE id = :id');
    $stmt->execute(['id' => $clientId]);
    $client = $stmt->fetch();
    if (!$client) {
        return ['ok' => false, 'error' => 'Client not found.'];
    }

    $availablePoints = (int) $client['reward_points'];
    $availableUnits = intdiv($availablePoints, REWARD_REDEMPTION_POINTS_PER_UNIT);
    $requestedUnits = (int) floor(max(0, $requestedAmount) / REWARD_REDEMPTION_DOLLARS_PER_UNIT);
    $units = min($availableUnits, $requestedUnits);

    if ($units <= 0) {
        if ($availablePoints < REWARD_REDEMPTION_POINTS_PER_UNIT) {
            return ['ok' => false, 'error' => 'Not enough points to redeem — need at least ' . REWARD_REDEMPTION_POINTS_PER_UNIT . ' points.'];
        }
        return ['ok' => false, 'error' => 'Nothing to redeem.'];
    }

    $pointsToRedeem = $units * REWARD_REDEMPTION_POINTS_PER_UNIT;
    $amount = round($units * REWARD_REDEMPTION_DOLLARS_PER_UNIT, 2);

    $pdo->prepare('UPDATE clients SET reward_points = reward_points - :pts WHERE id = :id')
        ->execute(['pts' => $pointsToRedeem, 'id' => $clientId]);
    $pdo->prepare('INSERT INTO client_points_redemptions (client_id, booking_id, points_redeemed, amount_value, note) VALUES (:cid, :bid, :pts, :amt, :note)')
        ->execute(['cid' => $clientId, 'bid' => $bookingId, 'pts' => $pointsToRedeem, 'amt' => $amount, 'note' => $note !== '' ? $note : null]);

    return [
        'ok' => true,
        'points_redeemed' => $pointsToRedeem,
        'amount_value' => $amount,
        'remaining_points' => $availablePoints - $pointsToRedeem,
    ];
}

function ensure_public_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    // Uses a custom cookie name (not PHP's default PHPSESSID) because this
    // host's edge proxy strips the default session cookie from responses.
    session_name(defined('PUBLIC_SESSION_NAME') ? PUBLIC_SESSION_NAME : 'diamond_public_session');
    // DB-backed storage — this host has multiple stateless app instances
    // with no shared filesystem, so default file sessions don't persist
    // reliably across requests.
    require_once __DIR__ . '/db_session_handler.php';
    session_set_save_handler(new DbSessionHandler(get_db()), true);
    session_start();
}

function csrf_token(): string
{
    ensure_public_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): bool
{
    ensure_public_session();
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
