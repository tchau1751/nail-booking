<?php
// ============================================================
//  Salon floor: clients, the walk-in queue, and turns.
//
//  "Turns" is the fairness rotation nail salons run on. Each
//  service carries a turn value (a full set is 1.00, a polish
//  change might be 0.50). Whoever is clocked in with the fewest
//  turns today is up next; ties break on who was assigned least
//  recently. Nothing is automatic — the board only *hints*, the
//  front desk always makes the call.
//
//  Every query here stays inside the signed-in salon: a phone
//  number is a client of this salon, a turn is taken on this
//  salon's floor.
// ============================================================
require_once __DIR__ . '/pos.php';

/* ── Clients ─────────────────────────────────────────────── */

function normalisePhone(string $p): string {
    $d = preg_replace('/\D+/', '', $p);
    if (strlen($d) === 11 && $d[0] === '1') $d = substr($d, 1);
    return $d;
}

function clientByPhone(string $phone): ?array {
    $d = normalisePhone($phone);
    if ($d === '') return null;
    return fetchOne('SELECT * FROM pos_clients WHERE tenant_id=? AND phone=?', [tenantId(), $d]);
}

/** One of this salon's clients, or null — including for an id that belongs to another salon. */
function clientFind(int $id): ?array {
    return fetchOne('SELECT * FROM pos_clients WHERE id=? AND tenant_id=?', [$id, tenantId()]);
}

/** Finds a client by phone or creates one. Returns the row. */
function clientUpsert(string $name, string $phone, string $email = ''): array {
    $d = normalisePhone($phone);
    if ($d === '') throw new RuntimeException('A phone number is required.');
    $existing = clientByPhone($d);
    if ($existing) {
        // Fill in anything we learned this visit without clobbering good data.
        $updates = [];
        $args = [];
        if ($name !== '' && $name !== $existing['full_name'] && $existing['full_name'] === '') { $updates[] = 'full_name=?'; $args[] = $name; }
        if ($email !== '' && $existing['email'] === '') { $updates[] = 'email=?'; $args[] = $email; }
        if ($updates) {
            array_push($args, $existing['id'], tenantId());
            query('UPDATE pos_clients SET ' . implode(',', $updates) . ' WHERE id=? AND tenant_id=?', $args);
            return clientFind((int)$existing['id']);
        }
        return $existing;
    }
    query('INSERT INTO pos_clients (tenant_id, full_name, phone, email, first_visit) VALUES (?,?,?,?,NULL)',
          [tenantId(), $name ?: 'Guest', $d, $email]);
    return clientFind((int)db()->lastInsertId());
}

function formatPhone(string $p): string {
    $d = normalisePhone($p);
    return strlen($d) === 10
        ? '(' . substr($d, 0, 3) . ') ' . substr($d, 3, 3) . '-' . substr($d, 6)
        : ($p ?: '');
}

function clientNotes(int $clientId): array {
    return fetchAll('SELECT n.*, u.name AS who FROM pos_client_notes n
                     LEFT JOIN admin_users u ON u.id = n.admin_id
                     WHERE n.tenant_id=? AND n.client_id=?
                     ORDER BY n.is_pinned DESC, n.id DESC', [tenantId(), $clientId]);
}

/* ── Shifts: who is on the floor ─────────────────────────── */

function techIsOn(int $techId, ?string $date = null): bool {
    $date = $date ?: date('Y-m-d');
    return (bool)fetchOne('SELECT 1 x FROM pos_tech_shifts
                           WHERE tenant_id=? AND technician_id=? AND shift_date=? AND clock_out IS NULL',
                          [tenantId(), $techId, $date]);
}

function clockIn(int $techId): void {
    if (!tenantOwns('technicians', $techId)) throw new RuntimeException('Technician not found.');
    if (techIsOn($techId)) return;
    query('INSERT INTO pos_tech_shifts (tenant_id, technician_id, shift_date, clock_in) VALUES (?,?,?,NOW())',
          [tenantId(), $techId, date('Y-m-d')]);
}

function clockOut(int $techId): void {
    query('UPDATE pos_tech_shifts SET clock_out=NOW()
           WHERE tenant_id=? AND technician_id=? AND shift_date=? AND clock_out IS NULL',
          [tenantId(), $techId, date('Y-m-d')]);
}

/* ── Turns ───────────────────────────────────────────────── */

/**
 * The rotation board for a day: every active technician with the turns
 * they have taken, whether they are clocked in, and who is up next.
 * Sorted the way the front desk reads it — next up first.
 */
function turnsBoard(?string $date = null): array {
    $date = $date ?: date('Y-m-d');
    $rows = fetchAll(
        "SELECT t.id, t.name, t.commission_rate, t.pay_type,
                COALESCE(SUM(CASE WHEN c.status IN ('in_service','done') THEN c.turn_value END), 0) AS turns,
                COUNT(CASE WHEN c.status IN ('in_service','done') THEN 1 END) AS guests,
                COUNT(CASE WHEN c.status = 'in_service' THEN 1 END) AS busy,
                MAX(c.assigned_at) AS last_assigned,
                (SELECT s.clock_in FROM pos_tech_shifts s
                  WHERE s.tenant_id=t.tenant_id AND s.technician_id=t.id
                    AND s.shift_date=? AND s.clock_out IS NULL
                  ORDER BY s.id DESC LIMIT 1) AS since
         FROM technicians t
         LEFT JOIN pos_checkins c
                ON c.tenant_id = t.tenant_id AND c.assigned_tech_id = t.id
               AND DATE(c.checked_in_at) = ?
         WHERE t.tenant_id = ? AND t.is_active = 1
         GROUP BY t.id, t.name, t.commission_rate, t.pay_type
         ORDER BY t.display_order, t.name", [$date, $date, tenantId()]);

    foreach ($rows as &$r) {
        $r['turns']     = (float)$r['turns'];
        $r['on_floor']  = !empty($r['since']);
        $r['busy']      = (int)$r['busy'];
        $r['available'] = $r['on_floor'] && $r['busy'] === 0;
    }
    unset($r);

    // Next up: fewest turns among the clocked-in and free, oldest assignment breaks ties.
    $candidates = array_values(array_filter($rows, function ($r) { return $r['available']; }));
    usort($candidates, function ($a, $b) {
        if ($a['turns'] !== $b['turns']) return $a['turns'] <=> $b['turns'];
        return strcmp((string)($a['last_assigned'] ?? ''), (string)($b['last_assigned'] ?? ''));
    });
    $nextId = $candidates[0]['id'] ?? null;
    foreach ($rows as &$r) $r['is_next'] = ($r['id'] == $nextId);
    unset($r);

    return $rows;
}

function nextUpTech(?string $date = null): ?array {
    foreach (turnsBoard($date) as $r) if ($r['is_next']) return $r;
    return null;
}

/* ── The queue ───────────────────────────────────────────── */

function checkInGuest(array $in): int {
    $name  = trim($in['guest_name'] ?? '');
    $phone = trim($in['guest_phone'] ?? '');
    if ($name === '') throw new RuntimeException('Please enter a name.');

    $clientId = null;
    if ($phone !== '') {
        $c = clientUpsert($name, $phone);
        $clientId = (int)$c['id'];
        if ($name !== '' && $c['full_name'] === 'Guest') {
            query('UPDATE pos_clients SET full_name=? WHERE id=? AND tenant_id=?', [$name, $clientId, tenantId()]);
        }
    }

    // The service, the requested technician and the booking all arrive from the
    // tablet. Each is kept only if it is this salon's.
    $serviceId = ownedId('services', $in['service_id'] ?? null);
    $turn = 1.00;
    if ($serviceId) {
        $s = fetchOne('SELECT turn_value FROM services WHERE id=? AND tenant_id=?', [$serviceId, tenantId()]);
        if ($s) $turn = (float)$s['turn_value'];
    }

    query('INSERT INTO pos_checkins (tenant_id, client_id, guest_name, guest_phone, party_size, service_id,
             requested_tech_id, appointment_id, turn_value, note)
           VALUES (?,?,?,?,?,?,?,?,?,?)', [
        tenantId(), $clientId, $name, normalisePhone($phone), max(1, (int)($in['party_size'] ?? 1)),
        $serviceId, ownedId('technicians', $in['requested_tech_id'] ?? null),
        ownedId('appointments', $in['appointment_id'] ?? null), $turn, trim($in['note'] ?? ''),
    ]);
    return (int)db()->lastInsertId();
}

function waitingList(): array {
    return fetchAll(
        "SELECT c.*, s.name AS service_name, s.price AS service_price,
                rt.name AS requested_tech, at.name AS assigned_tech,
                cl.points, cl.total_visits, cl.preferred_tech_id,
                TIMESTAMPDIFF(MINUTE, c.checked_in_at, NOW()) AS waited_min
         FROM pos_checkins c
         LEFT JOIN services s     ON s.id  = c.service_id
         LEFT JOIN technicians rt ON rt.id = c.requested_tech_id
         LEFT JOIN technicians at ON at.id = c.assigned_tech_id
         LEFT JOIN pos_clients cl ON cl.id = c.client_id
         WHERE c.tenant_id = ? AND c.status IN ('waiting','in_service') AND DATE(c.checked_in_at) = CURDATE()
         ORDER BY FIELD(c.status,'waiting','in_service'), c.checked_in_at", [tenantId()]);
}

function assignCheckin(int $checkinId, int $techId): void {
    $c = fetchOne('SELECT * FROM pos_checkins WHERE id=? AND tenant_id=?', [$checkinId, tenantId()]);
    if (!$c) throw new RuntimeException('Check-in not found.');
    if ($c['status'] === 'done') throw new RuntimeException('That guest is already finished.');
    if (!tenantOwns('technicians', $techId)) throw new RuntimeException('Technician not found.');
    query("UPDATE pos_checkins SET assigned_tech_id=?, status='in_service', assigned_at=NOW()
           WHERE id=? AND tenant_id=?", [$techId, $checkinId, tenantId()]);
}

function setCheckinStatus(int $checkinId, string $status): void {
    if (!in_array($status, ['waiting','in_service','done','no_show'], true)) {
        throw new RuntimeException('Unknown status.');
    }
    $done = in_array($status, ['done','no_show'], true);
    query('UPDATE pos_checkins SET status=?, completed_at=' . ($done ? 'NOW()' : 'NULL') . ' WHERE id=? AND tenant_id=?',
          [$status, $checkinId, tenantId()]);
}
