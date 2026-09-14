<?php
// ============================================================
//  Kiosk endpoints: look a guest up by phone, report the wait,
//  and check them in. Deliberately narrow — this is the one
//  screen a customer can touch, so it can do nothing else.
//
//  The kiosk tablet is signed in to one salon, so a phone number
//  typed here only ever finds that salon's client.
// ============================================================
require_once __DIR__ . '/../includes/salon.php';
require_once __DIR__ . '/../includes/rewards.php';
if (!isLoggedIn()) jsonOut(['error' => 'This tablet is signed out. Ask a manager to sign in again.'], 401);
if (!hasRole('front_desk')) jsonOut(['error' => 'The kiosk has to be signed in by the front desk.'], 403);
// The wait-time poll is a plain read; anything that writes has to prove it came
// from the kiosk page this tablet is actually showing.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !posCsrfValid($_POST['_csrf'] ?? null)) {
    jsonOut(['error' => 'This tablet was signed out — ask a manager to sign in again.'], 419);
}

/** Is this guest already in today's queue? */
function alreadyQueued(int $clientId): bool {
    return (bool)fetchOne("SELECT id FROM pos_checkins
                           WHERE tenant_id=? AND client_id=? AND status IN ('waiting','in_service')
                             AND DATE(checked_in_at)=CURDATE()", [tenantId(), $clientId]);
}

/**
 * How long the next walk-in is likely to wait.
 * Queue length times the average service, divided across whoever is
 * actually free — a rough figure, shown as such.
 */
function waitEstimate(): array {
    $waiting = (int)(fetchOne("SELECT COUNT(*) n FROM pos_checkins
                               WHERE tenant_id=? AND status='waiting' AND DATE(checked_in_at)=CURDATE()",
                              [tenantId()])['n'] ?? 0);
    $board   = turnsBoard();
    $onFloor = 0; $free = 0;
    foreach ($board as $t) {
        if ($t['on_floor']) $onFloor++;
        if ($t['available']) $free++;
    }
    $avg = (float)(fetchOne('SELECT AVG(duration_minutes) a FROM services WHERE tenant_id=? AND is_active=1',
                            [tenantId()])['a'] ?? 45);
    $avg = $avg > 0 ? $avg : 45;

    if ($waiting === 0 && $free > 0) {
        $minutes = 0;
    } elseif ($onFloor === 0) {
        $minutes = null;                 // nobody clocked in — don't invent a number
    } else {
        $minutes = (int)round(($waiting / max(1, $onFloor)) * $avg);
        $minutes = max(0, min(240, $minutes));
    }
    return ['waiting' => $waiting, 'on_floor' => $onFloor, 'free' => $free, 'minutes' => $minutes];
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'status';

try {
    switch ($action) {

        case 'status':
            jsonOut(['ok' => true] + waitEstimate());

        case 'lookup':
            $digits = normalisePhone($_POST['phone'] ?? '');
            if (strlen($digits) < 7) jsonOut(['error' => 'Please enter your full mobile number.'], 422);

            $client = clientByPhone($digits);
            if (!$client) {
                jsonOut(['ok' => true, 'known' => false, 'phone' => $digits]);
            }
            $card = stampCard($client);
            jsonOut([
                'ok'       => true,
                'known'    => true,
                'phone'    => $digits,
                'client'   => [
                    'id'         => (int)$client['id'],
                    'first_name' => explode(' ', trim($client['full_name']))[0],
                    'full_name'  => $client['full_name'],
                    'visits'     => (int)$client['total_visits'],
                    'points'     => (int)$client['points'],
                    'stamps'     => $card['on_card'],
                    'per_card'   => $card['per_card'],
                    'has_reward' => $card['has_reward'],
                    'reward'     => $card['reward'],
                ],
                'already_here' => alreadyQueued((int)$client['id']),
            ]);

        case 'checkin':
            $digits = normalisePhone($_POST['phone'] ?? '');
            $name   = trim($_POST['name'] ?? '');

            if ($digits !== '') {
                $existing = clientByPhone($digits);
                if ($existing && $name === '') $name = $existing['full_name'];
            }
            if ($name === '') jsonOut(['error' => 'Please tell us your name.'], 422);

            // Don't let an impatient tap put the same guest in the queue twice.
            if ($digits !== '') {
                $client = clientByPhone($digits);
                if ($client && alreadyQueued((int)$client['id'])) {
                    jsonOut(['ok' => true, 'duplicate' => true, 'first_name' => explode(' ', $name)[0]]
                            + waitEstimate());
                }
            }

            $id = checkInGuest([
                'guest_name'        => $name,
                'guest_phone'       => $digits,
                'service_id'        => $_POST['service_id'] ?? null,
                'requested_tech_id' => $_POST['technician_id'] ?? null,
                'party_size'        => $_POST['party_size'] ?? 1,
            ]);

            // Birthday, if they offered one. Day and month only — stored against
            // a neutral year because we never asked how old they are, and we
            // never overwrite a birthday already on file.
            $mo = (int)($_POST['dob_month'] ?? 0);
            $dy = (int)($_POST['dob_day'] ?? 0);
            if ($digits !== '' && $mo >= 1 && $mo <= 12 && $dy >= 1 && $dy <= 31) {
                $cl = clientByPhone($digits);
                if ($cl && empty($cl['birthday'])) {
                    if (checkdate($mo, $dy, 2000)) {
                        query('UPDATE pos_clients SET birthday=? WHERE id=? AND tenant_id=?',
                              [sprintf('1900-%02d-%02d', $mo, $dy), $cl['id'], tenantId()]);
                    }
                }
            }

            $row   = fetchOne('SELECT c.*, cl.points, cl.stamps, cl.rewards_earned, cl.rewards_redeemed
                               FROM pos_checkins c
                               LEFT JOIN pos_clients cl ON cl.id = c.client_id
                               WHERE c.id=? AND c.tenant_id=?', [$id, tenantId()]);
            $ahead = (int)(fetchOne("SELECT COUNT(*) n FROM pos_checkins
                                     WHERE tenant_id=? AND status='waiting' AND DATE(checked_in_at)=CURDATE() AND id<>?",
                                    [tenantId(), $id])['n'] ?? 0);
            $card = $row['client_id'] ? stampCard($row) : null;

            jsonOut([
                'ok'         => true,
                'first_name' => explode(' ', $name)[0],
                'ahead'      => $ahead,
                'points'     => $row['points'] !== null ? (int)$row['points'] : null,
                'card'       => $card,
            ] + waitEstimate());

        default:
            jsonOut(['error' => 'Unknown action.'], 400);
    }
} catch (Throwable $e) {
    jsonOut(['error' => $e->getMessage()], 400);
}
