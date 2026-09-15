<?php
// ============================================================
//  APPOINTMENT tab — the day calendar under the till's own top bar,
//  so the front desk can move between tabs. The calendar is its own
//  page (admin/calendar-standalone.php) with its own styles; framing
//  it keeps either stylesheet from restyling the other. It checks
//  the role and the salon again itself.
// ============================================================
$pageTitle   = 'Appointment';
$activeNav   = 'appointment';
$fullBleed   = true;
$requireRole = 'front_desk';
require_once __DIR__ . '/includes/layout_start.php';

$asked = is_string($_GET['date'] ?? null) ? $_GET['date'] : '';
$day   = DateTime::createFromFormat('!Y-m-d', $asked);
$query = ($day && $day->format('Y-m-d') === $asked) ? '?date=' . $asked : '';
?>
<style>
  .apptframe{flex:1 1 auto;width:100%;min-height:0;border:0;display:block;
    border-radius:var(--radius);background:#f5f5f5;box-shadow:var(--shadow);}
</style>
<iframe class="apptframe" title="Day calendar"
        src="<?= BASE_PATH ?>/admin/calendar-standalone.php<?= e($query) ?>"></iframe>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
