<?php
// ============================================================
//  Changing a booking from the salon's side — the day calendar's
//  drag-and-drop and its edit box, and the booking admin.
//
//  Every lookup is the signed-in salon's own. A booking or
//  technician id from another salon is simply not found, so a
//  guessed id can never move someone else's guest.
// ============================================================
require_once __DIR__ . '/db.php';

const BOOKING_STATUSES = ['pending', 'confirmed', 'cancelled', 'completed'];

/** One of this salon's bookings, or null. */
function bookingFind(int $id): ?array {
    return fetchOne('SELECT * FROM appointments WHERE id=? AND tenant_id=?', [$id, tenantId()]);
}

/**
 * A live booking that would sit on top of start..end — the rule the booking
 * page uses: the same technician or one nobody is assigned to, and with no
 * technician, any booking at all.
 */
function bookingClash(int $exceptId, string $date, string $start, string $end, ?int $techId): ?array {
    $sql = "SELECT id, full_name, start_time FROM appointments
            WHERE tenant_id=? AND appointment_date=? AND id<>?
              AND status IN ('pending','confirmed')
              AND start_time < ? AND end_time > ?";
    $params = [tenantId(), $date, $exceptId, $end, $start];
    if ($techId) {
        $sql .= ' AND (technician_id=? OR technician_id IS NULL)';
        $params[] = $techId;
    }
    return fetchOne($sql . ' ORDER BY start_time LIMIT 1', $params);
}

/**
 * Change only what $changes names, and return the booking as it now stands:
 *   date           'Y-m-d'
 *   time           'H:i' or 'H:i:s' — the end follows from the service's length
 *   technician_id  one of this salon's technicians, or null for "anyone"
 *   status         one of BOOKING_STATUSES
 * Throws InvalidArgumentException with a sentence the calendar can show as is.
 */
function bookingUpdate(int $id, array $changes): array {
    $tid  = tenantId();
    $appt = bookingFind($id);
    if (!$appt) throw new InvalidArgumentException('Appointment not found');
    if (!$changes) throw new InvalidArgumentException('No updates provided');

    $sets = []; $params = [];

    $date = $appt['appointment_date'];
    if (array_key_exists('date', $changes)) {
        $d = is_string($changes['date']) ? DateTime::createFromFormat('!Y-m-d', $changes['date']) : false;
        if (!$d || $d->format('Y-m-d') !== $changes['date']) throw new InvalidArgumentException('Invalid date format');
        $date = $d->format('Y-m-d');
    }

    $start = $appt['start_time'];
    $end   = $appt['end_time'];
    if (array_key_exists('time', $changes)) {
        $raw = is_string($changes['time']) ? $changes['time'] : '';
        $t   = DateTime::createFromFormat('!H:i:s', $raw) ?: DateTime::createFromFormat('!H:i', $raw);
        if (!$t || !in_array($raw, [$t->format('H:i'), $t->format('H:i:s')], true)) {
            throw new InvalidArgumentException('Invalid time format');
        }
        // Saving the edit box with the time it already had keeps the booking's
        // length as it is; only a real move is re-timed from the service.
        if ($t->format('H:i:s') !== $appt['start_time']) {
            $service = fetchOne('SELECT duration_minutes FROM services WHERE id=? AND tenant_id=?', [$appt['service_id'], $tid]);
            if (!$service) throw new InvalidArgumentException('Service not found');
            $from = new DateTime($date . ' ' . $t->format('H:i:s'));
            $to   = (clone $from)->modify('+' . max(5, (int)$service['duration_minutes']) . ' minutes');
            if ($to->format('Y-m-d') !== $date) throw new InvalidArgumentException('That would run past midnight');
            $start = $from->format('H:i:s');
            $end   = $to->format('H:i:s');
        }
    }

    $techId = $appt['technician_id'] !== null ? (int)$appt['technician_id'] : null;
    if (array_key_exists('technician_id', $changes)) {
        $want = $changes['technician_id'];
        if ($want === null || $want === '' || $want === 0 || $want === '0') {
            $techId = null;
        } elseif ((is_int($want) || (is_string($want) && ctype_digit($want))) && tenantOwns('technicians', $want)) {
            $techId = (int)$want;
        } else {
            throw new InvalidArgumentException('That technician is not on this salon\'s list');
        }
    }

    $status = $appt['status'];
    if (array_key_exists('status', $changes)) {
        if (!in_array($changes['status'], BOOKING_STATUSES, true)) throw new InvalidArgumentException('Invalid status');
        $status = $changes['status'];
    }

    $moved = $date !== $appt['appointment_date'] || $start !== $appt['start_time'];
    $techChanged = $techId !== ($appt['technician_id'] !== null ? (int)$appt['technician_id'] : null);

    if ($date !== $appt['appointment_date']) { $sets[] = 'appointment_date=?'; $params[] = $date; }
    if ($start !== $appt['start_time'])      { $sets[] = 'start_time=?'; $params[] = $start;
                                               $sets[] = 'end_time=?';   $params[] = $end; }
    if ($techChanged)                        { $sets[] = 'technician_id=?'; $params[] = $techId; }
    if ($status !== $appt['status'])         { $sets[] = 'status=?'; $params[] = $status; }

    if (!$sets) return $appt;   // asked for what it already is

    if (($moved || $techChanged) && in_array($status, ['pending', 'confirmed'], true)
        && bookingClash($id, $date, $start, $end, $techId)) {
        throw new InvalidArgumentException('This time slot is no longer available');
    }

    $params[] = $id;
    $params[] = $tid;
    query('UPDATE appointments SET ' . implode(', ', $sets) . ' WHERE id=? AND tenant_id=?', $params);
    return bookingFind($id);
}
