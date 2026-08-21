<?php
$pageTitle = 'Clear test data';
$activeNav = 'settings';
$requireRole = 'owner';   // enforced by layout_start before any output
require_once __DIR__ . '/includes/layout_start.php';
require_once __DIR__ . '/includes/purge.php';

$msg = ''; $err = ''; $preview = null; $previewKind = '';

$from = $_POST['from'] ?? $_GET['from'] ?? date('Y-m-01');
$to   = $_POST['to']   ?? $_GET['to']   ?? date('Y-m-t');
$opts = [
    'checkins'  => isset($_POST['checkins']),
    'drawer'    => isset($_POST['drawer']),
    'expenses'  => isset($_POST['expenses']),
    'giftcards' => isset($_POST['giftcards']),
    'clients'   => isset($_POST['clients']),
    'bookings'  => isset($_POST['bookings']),
];

$action = $_POST['action'] ?? '';
try {
    if ($action === 'preview_range') {
        $preview = purgeRangePreview($from, $to, $opts);
        $previewKind = 'range';
    } elseif ($action === 'preview_all') {
        $preview = purgeAllPreview($opts);
        $previewKind = 'all';
    } elseif ($action === 'purge_range') {
        // Typed in full, every time. A button alone is one mis-tap from a
        // month of takings.
        if (trim((string)($_POST['confirm'] ?? '')) !== 'DELETE') {
            throw new RuntimeException('Type DELETE in the box to confirm.');
        }
        $n = purgeRange($from, $to, $opts);
        $msg = 'Cleared ' . $n['sales'] . ' ticket' . ($n['sales'] === 1 ? '' : 's')
             . ' from ' . date('m/d/Y', strtotime($from)) . ' to ' . date('m/d/Y', strtotime($to))
             . '. Client totals rebuilt from what is left.';
    } elseif ($action === 'purge_all') {
        if (trim((string)($_POST['confirm'] ?? '')) !== 'RESET EVERYTHING') {
            throw new RuntimeException('Type RESET EVERYTHING in the box to confirm.');
        }
        $n = purgeAll($opts);
        $msg = 'Reset done — ' . $n['sales'] . ' tickets and the rest of the history are gone. '
             . 'Your menu, retail, technicians, staff accounts and settings are untouched.';
    }
} catch (Throwable $e) {
    $err = $e->getMessage();
}

$totalSales = (int)fetchOne('SELECT COUNT(*) v FROM pos_sales')['v'];
?>
<?php if ($msg): ?><div class="alert alert-ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>

<div class="card">
  <h2>Before you start</h2>
  <p class="sub">
    There is no undo here. Take a copy of the database first — in phpMyAdmin, pick the
    <strong>nail_booking</strong> database, open <strong>Export</strong> and click Go. That file is
    the only way back.
  </p>
  <p class="sub">
    Nothing on this page touches your service menu, retail products, technicians, staff accounts or
    settings. It clears the history: tickets, refunds, the walk-in queue, clocked hours, the cash
    drawer and the reward ledgers. There are <strong><?= $totalSales ?></strong> tickets on the books
    right now.
  </p>
</div>

<!-- ── Clear a date range ─────────────────────────────────── -->
<div class="card">
  <h2>Clear practice tickets in a date range</h2>
  <p class="sub">
    The usual one: you have been trying the till out all month and want the made-up tickets gone
    before the shop opens for real. Everything outside these dates stays.
  </p>
  <form method="post">
    <input type="hidden" name="action" value="preview_range">
    <div class="toolbar" style="margin:0 0 14px">
      <label class="field"><span>From</span><input type="date" name="from" value="<?= e($from) ?>"></label>
      <label class="field"><span>To</span><input type="date" name="to" value="<?= e($to) ?>"></label>
    </div>
    <label style="display:block;font-weight:700;margin-bottom:6px">
      <input type="checkbox" name="checkins" <?= $opts['checkins'] ? 'checked' : '' ?>>
      Also clear the walk-in queue and clocked hours in this range</label>
    <label style="display:block;font-weight:700;margin-bottom:6px">
      <input type="checkbox" name="drawer" <?= $opts['drawer'] ? 'checked' : '' ?>>
      Also clear cash drawer entries in this range</label>
    <label style="display:block;font-weight:700;margin-bottom:14px">
      <input type="checkbox" name="expenses" <?= $opts['expenses'] ? 'checked' : '' ?>>
      Also clear recorded expenses in this range</label>
    <button class="btn" type="submit">Show me what this would delete</button>
  </form>

  <?php if ($previewKind === 'range' && $preview !== null): ?>
    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <?php if ($preview['sales'] < 1 && $preview['drawer'] < 1 && $preview['expenses'] < 1 && $preview['checkins'] < 1): ?>
      <div class="alert alert-ok">Nothing in that range — there is nothing to clear.</div>
    <?php else: ?>
      <h3 style="font-size:16px;margin-bottom:8px">This will permanently delete</h3>
      <div class="table-wrap">
        <table>
          <tbody>
            <tr><td>Tickets</td><td class="num"><strong><?= $preview['sales'] ?></strong>
              <span style="color:var(--ink-soft)">worth <?= money($preview['taken']) ?></span></td></tr>
            <tr><td>Refunds</td><td class="num"><?= $preview['refunds'] ?></td></tr>
            <?php if ($opts['checkins']): ?>
              <tr><td>Queue check-ins</td><td class="num"><?= $preview['checkins'] ?></td></tr>
              <tr><td>Clocked shifts</td><td class="num"><?= $preview['shifts'] ?></td></tr>
            <?php endif; ?>
            <?php if ($opts['drawer']): ?>
              <tr><td>Cash drawer entries</td><td class="num"><?= $preview['drawer'] ?></td></tr>
            <?php endif; ?>
            <?php if ($opts['expenses']): ?>
              <tr><td>Expenses</td><td class="num"><?= $preview['expenses'] ?></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <p class="sub" style="margin-top:12px">
        Points and stamps earned on those tickets go with them. Only the clients who were on those
        tickets are touched: their visit count, lifetime spend, point balance and stamp balance are
        rebuilt from what is left. Everyone else is untouched.
      </p>
      <form method="post" style="margin-top:12px">
        <input type="hidden" name="action" value="purge_range">
        <input type="hidden" name="from" value="<?= e($from) ?>">
        <input type="hidden" name="to" value="<?= e($to) ?>">
        <?php foreach (['checkins', 'drawer', 'expenses'] as $k): ?>
          <?php if ($opts[$k]): ?><input type="hidden" name="<?= $k ?>" value="1"><?php endif; ?>
        <?php endforeach; ?>
        <label class="field" style="max-width:320px"><span>Type DELETE to confirm</span>
          <input type="text" name="confirm" autocomplete="off" placeholder="DELETE"></label>
        <button class="btn btn-red btn-lg" type="submit">Delete these <?= $preview['sales'] ?> tickets</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>

<!-- ── Reset everything ───────────────────────────────────── -->
<div class="card" style="border:2px solid var(--red)">
  <h2 style="color:var(--red)">Reset everything</h2>
  <p class="sub">
    Back to opening day. Every ticket, refund, payment, check-in, clocked shift, cash movement,
    expense, campaign and piece of feedback is deleted, and every client's points, stamps, visit
    count and lifetime spend goes to zero. Your menu, retail shelf, technicians, staff accounts and
    settings survive.
  </p>
  <form method="post">
    <input type="hidden" name="action" value="preview_all">
    <label style="display:block;font-weight:700;margin-bottom:6px">
      <input type="checkbox" name="giftcards" <?= $opts['giftcards'] ? 'checked' : '' ?>>
      Also delete gift cards <span style="font-weight:500;color:var(--ink-soft)">— leave off and
      outstanding cards keep their balance</span></label>
    <label style="display:block;font-weight:700;margin-bottom:6px">
      <input type="checkbox" name="clients" <?= $opts['clients'] ? 'checked' : '' ?>>
      Also delete the client list <span style="font-weight:500;color:var(--ink-soft)">— leave off and
      clients stay, just with their counters zeroed</span></label>
    <label style="display:block;font-weight:700;margin-bottom:14px">
      <input type="checkbox" name="bookings" <?= $opts['bookings'] ? 'checked' : '' ?>>
      Also delete online bookings and the SMS log</label>
    <button class="btn" type="submit">Show me what this would delete</button>
  </form>

  <?php if ($previewKind === 'all' && $preview !== null): ?>
    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <h3 style="font-size:16px;margin-bottom:8px">This will permanently delete</h3>
    <div class="table-wrap">
      <table>
        <tbody>
          <tr><td>Tickets</td><td class="num"><strong><?= $preview['sales'] ?></strong>
            <span style="color:var(--ink-soft)">worth <?= money($preview['taken']) ?></span></td></tr>
          <tr><td>Refunds</td><td class="num"><?= $preview['refunds'] ?></td></tr>
          <tr><td>Queue check-ins</td><td class="num"><?= $preview['checkins'] ?></td></tr>
          <tr><td>Clocked shifts</td><td class="num"><?= $preview['shifts'] ?></td></tr>
          <tr><td>Cash drawer entries</td><td class="num"><?= $preview['drawer'] ?></td></tr>
          <tr><td>Expenses</td><td class="num"><?= $preview['expenses'] ?></td></tr>
          <tr><td>Feedback</td><td class="num"><?= $preview['feedback'] ?></td></tr>
          <tr><td>Campaigns</td><td class="num"><?= $preview['campaigns'] ?></td></tr>
          <?php if ($opts['giftcards']): ?>
            <tr><td>Gift cards</td><td class="num"><?= $preview['giftcards'] ?></td></tr><?php endif; ?>
          <?php if ($opts['clients']): ?>
            <tr><td>Clients</td><td class="num"><?= $preview['clients'] ?></td></tr><?php endif; ?>
          <?php if ($opts['bookings']): ?>
            <tr><td>Online bookings</td><td class="num"><?= $preview['bookings'] ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <form method="post" style="margin-top:12px">
      <input type="hidden" name="action" value="purge_all">
      <?php foreach (['giftcards', 'clients', 'bookings'] as $k): ?>
        <?php if ($opts[$k]): ?><input type="hidden" name="<?= $k ?>" value="1"><?php endif; ?>
      <?php endforeach; ?>
      <label class="field" style="max-width:360px"><span>Type RESET EVERYTHING to confirm</span>
        <input type="text" name="confirm" autocomplete="off" placeholder="RESET EVERYTHING"></label>
      <button class="btn btn-red btn-lg" type="submit">Reset everything</button>
    </form>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
