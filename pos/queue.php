<?php
$pageTitle = 'Queue & Turns';
$activeNav = 'queue';
require_once __DIR__ . '/includes/layout_start.php';
require_once __DIR__ . '/includes/salon.php';

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['action'] ?? '') {
            case 'checkin':
                checkInGuest($_POST);
                $msg = 'Guest checked in.';
                break;
            case 'assign':
                assignCheckin((int)$_POST['checkin_id'], (int)$_POST['tech_id']);
                $msg = 'Guest assigned.';
                break;
            case 'status':
                setCheckinStatus((int)$_POST['checkin_id'], $_POST['status']);
                $msg = 'Queue updated.';
                break;
            case 'clock':
                $tid  = (int)$_POST['tech_id'];
                $who  = fetchOne('SELECT name FROM technicians WHERE id=?', [$tid])['name'] ?? 'Technician';
                if ($_POST['dir'] === 'in') { clockIn($tid);  $msg = $who . ' is on the floor.'; }
                else                        { clockOut($tid); $msg = $who . ' has clocked out.'; }
                break;
        }
        // Redirect after POST so a refresh doesn't re-submit.
        header('Location: ' . BASE_PATH . '/pos/queue.php?m=' . urlencode($msg));
        exit;
    } catch (Throwable $e) { $err = $e->getMessage(); }
}
$msg = $msg ?: ($_GET['m'] ?? '');

$board    = turnsBoard();
$waiting  = waitingList();
$services = fetchAll('SELECT id,name,turn_value FROM services WHERE is_active=1 ORDER BY display_order, name');
$onFloor  = array_values(array_filter($board, function ($t) { return $t['on_floor']; }));
$totalTurns = array_sum(array_column($board, 'turns'));
// Baseline for the online-booking alert poll below: only appointments
// booked after this counts as "new" the first time this page loads.
$latestApptId = (int)(fetchOne('SELECT MAX(id) m FROM appointments')['m'] ?? 0);
?>
<?php if ($msg): ?><div class="alert alert-ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>

<!-- New online bookings pop in here with a chime — see the script at the
     bottom of this page. Empty and hidden until something arrives. -->
<div id="bookingAlerts" style="display:none;margin-bottom:14px;border-radius:12px;
     border:1px solid var(--gold);background:#fbf3e6;overflow:hidden"></div>

<div class="stats">
  <div class="stat"><div class="v"><?= count(array_filter($waiting, function ($w) { return $w['status'] === 'waiting'; })) ?></div><div class="k">Waiting</div></div>
  <div class="stat"><div class="v"><?= count(array_filter($waiting, function ($w) { return $w['status'] === 'in_service'; })) ?></div><div class="k">In service</div></div>
  <div class="stat"><div class="v"><?= count($onFloor) ?></div><div class="k">Techs on the floor</div></div>
  <div class="stat"><div class="v"><?= rtrim(rtrim(number_format($totalTurns, 2), '0'), '.') ?></div><div class="k">Turns taken today</div></div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px">

  <!-- ── WAITING LIST ─────────────────────────────────── -->
  <div class="card">
    <h2>🪑 Waiting list</h2>
    <p class="sub">Tap a technician's name on a guest to assign them.</p>

    <?php if (!$waiting): ?>
      <div class="empty">Nobody is waiting. Check a guest in below.</div>
    <?php endif; ?>

    <?php foreach ($waiting as $w): ?>
      <div style="border:1px solid var(--line);border-radius:12px;padding:14px;margin-bottom:12px;
                  background:<?= $w['status'] === 'in_service' ? '#f4fbf6' : '#fff' ?>">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
          <div style="min-width:0">
            <div style="font-weight:800;font-size:17px">
              <?= e($w['guest_name']) ?>
              <?php if ($w['party_size'] > 1): ?><span class="pill pill-low">party of <?= (int)$w['party_size'] ?></span><?php endif; ?>
            </div>
            <div style="font-size:13px;color:var(--ink-soft);margin-top:3px">
              <?= e($w['service_name'] ?: 'Service not chosen') ?>
              · waiting <?= (int)$w['waited_min'] ?> min
              <?php if ($w['total_visits'] !== null): ?> · <?= (int)$w['total_visits'] ?> visits<?php endif; ?>
              <?php if ($w['requested_tech']): ?> · <strong>asked for <?= e($w['requested_tech']) ?></strong><?php endif; ?>
            </div>
            <?php if ($w['note']): ?><div style="font-size:13px;margin-top:4px">📝 <?= e($w['note']) ?></div><?php endif; ?>
          </div>
          <div style="text-align:right;white-space:nowrap">
            <?php if ($w['status'] === 'in_service'): ?>
              <div class="pill pill-ok">with <?= e($w['assigned_tech']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px">
          <?php foreach ($board as $t): if (!$t['on_floor']) continue; ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="action" value="assign">
              <input type="hidden" name="checkin_id" value="<?= (int)$w['id'] ?>">
              <input type="hidden" name="tech_id" value="<?= (int)$t['id'] ?>">
              <button class="btn btn-sm <?= $t['is_next'] ? 'btn-green' : 'btn-light' ?>" type="submit"
                      <?= $w['assigned_tech_id'] == $t['id'] ? 'disabled' : '' ?>>
                <?= e($t['name']) ?><?= $t['is_next'] ? ' ← next' : '' ?>
              </button>
            </form>
          <?php endforeach; ?>

          <?php if ($w['status'] === 'in_service'): ?>
            <a class="btn btn-sm btn-blue" href="<?= BASE_PATH ?>/pos/index.php?checkin=<?= (int)$w['id'] ?>">💅 Ring up</a>
          <?php endif; ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Mark <?= e($w['guest_name']) ?> as a no-show?')">
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="checkin_id" value="<?= (int)$w['id'] ?>">
            <input type="hidden" name="status" value="no_show">
            <button class="btn btn-sm btn-light" type="submit">No-show</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- ── TURNS BOARD ──────────────────────────────────── -->
  <div class="card">
    <h2>🔄 Turns</h2>
    <p class="sub">Fewest turns among the free, clocked-in techs is up next. It's a hint — you always have the last word.</p>

    <div class="table-wrap">
      <table>
        <thead><tr><th>Technician</th><th class="num">Turns</th><th class="num">Guests</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($board as $t): ?>
          <tr style="<?= $t['is_next'] ? 'background:#eefaf1' : '' ?>">
            <td>
              <strong><?= e($t['name']) ?></strong>
              <?php if ($t['is_next']): ?><span class="pill pill-ok">next up</span><?php endif; ?>
            </td>
            <td class="num"><?= rtrim(rtrim(number_format($t['turns'], 2), '0'), '.') ?></td>
            <td class="num"><?= (int)$t['guests'] ?></td>
            <td>
              <?php if (!$t['on_floor']): ?><span class="pill" style="background:#eee;color:#777">off</span>
              <?php elseif ($t['busy']): ?><span class="pill pill-low">busy</span>
              <?php else: ?><span class="pill pill-ok">free</span><?php endif; ?>
              <?php if ($t['on_floor'] && !empty($t['since'])): ?>
                <div style="font-size:11px;color:var(--ink-soft);margin-top:3px">since <?= date('g:i A', strtotime($t['since'])) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <form method="post" class="clock-form"
                    <?= $t['on_floor'] ? 'data-confirm="Clock ' . e($t['name']) . ' out for the day?"' : '' ?>>
                <input type="hidden" name="action" value="clock">
                <input type="hidden" name="tech_id" value="<?= (int)$t['id'] ?>">
                <input type="hidden" name="dir" value="<?= $t['on_floor'] ? 'out' : 'in' ?>">
                <button class="btn btn-sm <?= $t['on_floor'] ? 'btn-light' : 'btn-green' ?>" type="submit">
                  <?= $t['on_floor'] ? 'Clock out' : 'Clock in' ?>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── CHECK A GUEST IN ───────────────────────────────── -->
<div class="card">
  <h2>➕ Check in a guest</h2>
  <p class="sub">Front-desk entry. Guests can also sign themselves in on the
     <a href="<?= BASE_PATH ?>/pos/kiosk.php" target="_blank" rel="noopener">kiosk screen</a>.</p>
  <form method="post">
    <input type="hidden" name="action" value="checkin">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px">
      <label class="field"><span>Name</span><input type="text" name="guest_name" required></label>
      <label class="field"><span>Phone</span><input type="text" name="guest_phone" inputmode="tel" placeholder="Optional but recommended"></label>
      <label class="field"><span>Service</span>
        <select name="service_id">
          <option value="">— decide later —</option>
          <?php foreach ($services as $s): ?>
            <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?> (<?= rtrim(rtrim(number_format($s['turn_value'], 2), '0'), '.') ?> turn)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field"><span>Requested technician</span>
        <select name="requested_tech_id">
          <option value="">— no preference —</option>
          <?php foreach ($board as $t): ?>
            <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field"><span>Party size</span><input type="number" name="party_size" value="1" min="1"></label>
      <label class="field"><span>Note</span><input type="text" name="note" placeholder="Optional"></label>
    </div>
    <button class="btn btn-green btn-lg" type="submit">Check in</button>
  </form>
</div>

<script>
  // Clocking out is the destructive half of the toggle, so it asks first —
  // and either way the button locks so a double tap can't undo the action.
  document.addEventListener('submit', function (ev) {
    var ask = ev.target.getAttribute('data-confirm');
    if (ask && !confirm(ask)) { ev.preventDefault(); return; }
    var b = ev.target.querySelector('button[type=submit]');
    if (b) setTimeout(function () { b.disabled = true; }, 0);
  });

  // The board is a wall display as much as a control panel — keep it fresh,
  // but never while someone is mid-form.
  setInterval(function () {
    if (!document.querySelector('input:focus, select:focus, textarea:focus')) location.reload();
  }, 60000);
</script>

<!-- ── Online-booking alert: chime + banner the moment a client books
     through the public website. Separate from the queue/turns data
     above — appointments and walk-in check-ins are different tables,
     so this is the only place staff would otherwise see it happen. -->
<script>
(function () {
  'use strict';
  var STORE_KEY = 'queueLastSeenApptId';
  var stored = parseInt(localStorage.getItem(STORE_KEY), 10);
  var lastSeen = (!isNaN(stored) && stored > 0) ? stored : <?= (int)$latestApptId ?>;

  // Browsers block audio until the page has been interacted with at least
  // once — grab that first click/tap (checking a guest in, clicking a
  // tab, anything) to warm up the audio context ahead of time.
  var audioCtx = null;
  function warmAudio() {
    if (!audioCtx) { try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) {} }
    document.removeEventListener('click', warmAudio);
  }
  document.addEventListener('click', warmAudio);

  function chime() {
    if (!audioCtx) { try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { return; } }
    [660, 880].forEach(function (freq, i) {
      var o = audioCtx.createOscillator(), g = audioCtx.createGain();
      o.type = 'sine'; o.frequency.value = freq;
      o.connect(g); g.connect(audioCtx.destination);
      var t = audioCtx.currentTime + i * 0.16;
      g.gain.setValueAtTime(0.0001, t);
      g.gain.exponentialRampToValueAtTime(0.35, t + 0.02);
      g.gain.exponentialRampToValueAtTime(0.0001, t + 0.32);
      o.start(t); o.stop(t + 0.34);
    });
  }

  function showBooking(b) {
    var box = document.getElementById('bookingAlerts');
    box.style.display = 'block';
    var row = document.createElement('div');
    row.style.cssText = 'display:flex;justify-content:space-between;align-items:center;gap:12px;'
      + 'padding:12px 16px;border-bottom:1px solid rgba(0,0,0,.06);font-size:14px';
    row.innerHTML = '<span>🔔 <strong>New online booking</strong> — ' + b.name +
      ' booked ' + b.service + ' for ' + b.date + ' at ' + b.time + '</span>' +
      '<button type="button" aria-label="Dismiss" style="border:none;background:transparent;' +
      'font-size:18px;line-height:1;cursor:pointer;color:var(--ink-soft)">×</button>';
    row.querySelector('button').addEventListener('click', function () {
      row.remove();
      if (!box.querySelector('div')) box.style.display = 'none';
    });
    box.appendChild(row);
  }

  function poll() {
    fetch('<?= BASE_PATH ?>/pos/api/booking_alerts.php?since=' + lastSeen, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (r) {
        if (!r || !r.ok || !r.bookings || !r.bookings.length) return;
        r.bookings.forEach(showBooking);
        chime();
        lastSeen = r.latest_id;
        localStorage.setItem(STORE_KEY, lastSeen);
      })
      .catch(function () {});
  }
  poll();
  setInterval(poll, 15000);
})();
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
