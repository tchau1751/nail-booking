<?php
require_once __DIR__ . '/db.php';

/**
 * Generate available time slots for a given date, service, and optional technician.
 * Returns array of ['start'=>'HH:mm', 'end'=>'HH:mm', 'label'=>'9:00 AM']
 */
function getAvailableSlots(string $date, int $serviceId, ?int $techId = null): array {
    $s       = settings();
    $interval = (int)($s['slot_interval_minutes'] ?? 30);
    $notice   = (int)($s['booking_notice_hours']  ?? 2);

    // 1. Check blocked date
    $blocked = fetchOne('SELECT id FROM blocked_dates WHERE blocked_date=?', [$date]);
    if ($blocked) return [];

    // 2. Business hours for this weekday
    $dow = (int)date('w', strtotime($date));  // 0=Sun
    $bh  = fetchOne('SELECT * FROM business_hours WHERE weekday=?', [$dow]);
    if (!$bh || !$bh['is_open']) return [];

    // 3. Service duration
    $svc = fetchOne('SELECT duration_minutes FROM services WHERE id=? AND is_active=1', [$serviceId]);
    if (!$svc) return [];
    $duration = (int)$svc['duration_minutes'];

    // 4. Existing appointments on this date (exclude cancelled)
    $existingQuery = $techId
        ? 'SELECT start_time,end_time FROM appointments WHERE appointment_date=? AND status!=? AND (technician_id=? OR technician_id IS NULL)'
        : 'SELECT start_time,end_time FROM appointments WHERE appointment_date=? AND status!=?';
    $existingParams = $techId ? [$date,'cancelled',$techId] : [$date,'cancelled'];
    $existing = fetchAll($existingQuery, $existingParams);

    $openTs  = strtotime("{$date} {$bh['start_time']}");
    $closeTs = strtotime("{$date} {$bh['end_time']}");
    $nowTs   = time() + $notice * 3600;

    $slots = [];
    $cursor = $openTs;

    while (true) {
        $slotEnd = $cursor + $duration * 60;
        if ($slotEnd > $closeTs) break;

        if ($cursor < $nowTs) {
            $cursor += $interval * 60;
            continue;
        }

        // Check overlap with existing appointments
        $overlap = false;
        foreach ($existing as $ex) {
            $exStart = strtotime("{$date} {$ex['start_time']}");
            $exEnd   = strtotime("{$date} {$ex['end_time']}");
            if ($cursor < $exEnd && $slotEnd > $exStart) {
                $overlap = true;
                break;
            }
        }

        if (!$overlap) {
            $slots[] = [
                'start' => date('H:i', $cursor),
                'end'   => date('H:i', $slotEnd),
                'label' => date('g:i A', $cursor),
            ];
        }

        $cursor += $interval * 60;
    }

    return $slots;
}
