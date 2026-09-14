<?php
// ============================================================
//  Devices — every till, tablet and screen the salon runs.
//
//  Register the browser you are holding, give it a name the staff
//  will recognise, and it becomes one of the salon's stations. Lost
//  a tablet? Switch it off here and it is signed out on its next tap.
// ============================================================
$pageTitle = 'Devices';
$activeNav = 'devices';
$requireRole = 'manager';   // enforced by layout_start before any output
require_once __DIR__ . '/includes/layout_start.php';
require_once __DIR__ . '/../includes/devices.php';

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['action'] ?? '') {
            case 'register':
                $d = deviceRegister((string)($_POST['name'] ?? ''), (string)($_POST['kind'] ?? 'pos'));
                $msg = 'This browser is now ' . $d['name'] . '.';
                break;
            case 'rename':
                deviceRename((int)$_POST['id'], (string)($_POST['name'] ?? ''));
                $msg = 'Device renamed.';
                break;
            case 'off':
                deviceSetActive((int)$_POST['id'], false);
                $msg = 'Device switched off. Anyone signed in on it is signed out on their next tap.';
                break;
            case 'on':
                deviceSetActive((int)$_POST['id'], true);
                $msg = 'Device switched back on.';
                break;
            case 'forget':
                deviceForgetHere();
                $msg = 'This browser is no longer registered. The device is still on the list below.';
                break;
        }
        header('Location: ' . BASE_PATH . '/pos/devices.php?m=' . urlencode($msg));
        exit;
    } catch (Throwable $e) { $err = $e->getMessage(); }
}
$msg = $msg ?: ($_GET['m'] ?? '');

$here    = deviceHere();
$devices = devicesList();
$tenant  = currentTenant();
$active  = count(array_filter($devices, function ($d) { return (int)$d['is_active'] === 1; }));
$limit   = $tenant['max_devices'];
?>
<?php if ($msg): ?><div class="alert alert-ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>

<div class="stats">
  <div class="stat"><div class="v"><?= $active ?><?= $limit !== null ? ' / ' . (int)$limit : '' ?></div>
       <div class="k">Devices switched on<?= $limit === null ? ' (no limit)' : '' ?></div></div>
  <div class="stat"><div class="v" style="font-size:20px"><?= e($tenant['plan_name'] ?? '—') ?></div><div class="k">Plan</div></div>
  <div class="stat"><div class="v" style="font-size:20px"><?= e($here['name'] ?? 'Not registered') ?></div><div class="k">This browser</div></div>
</div>

<div class="card">
  <?php if ($here): ?>
    <h2>✅ This browser is <?= e($here['name']) ?></h2>
    <p class="sub"><?= e(DEVICE_KINDS[$here['kind']] ?? $here['kind']) ?>. Sales rung up here are recorded against it,
       and the PIN pad on it lists this salon's staff without anyone signing in first.</p>
    <form method="post" onsubmit="return confirm('Stop treating this browser as <?= e($here['name']) ?>?')">
      <input type="hidden" name="action" value="forget">
      <button class="btn btn-light" type="submit">Unregister this browser</button>
    </form>
  <?php else: ?>
    <h2>➕ Register this browser</h2>
    <p class="sub">Do this once on each till, tablet or screen, signed in as a manager. The device keeps a secret
       key in this browser; clearing the browser's data or using a private window loses it, and it has to be
       registered again.</p>
    <form method="post" class="toolbar" style="margin:0">
      <input type="hidden" name="action" value="register">
      <label class="field" style="flex:1;min-width:200px"><span>Name</span>
        <input type="text" name="name" maxlength="60" required placeholder="POS #1"></label>
      <label class="field"><span>What it is</span>
        <select name="kind">
          <?php foreach (DEVICE_KINDS as $k => $label): ?>
            <option value="<?= e($k) ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select></label>
      <button class="btn btn-green" type="submit">Register</button>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2>All devices</h2>
  <p class="sub">Once any device is registered, <strong>till PINs only work on registered devices</strong> — a
     four-digit PIN is too easy to guess to work from any browser. Email and password still work anywhere.</p>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>Kind</th><th>Last used</th><th>By</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($devices as $d): $isHere = $here && (int)$here['id'] === (int)$d['id']; ?>
        <tr style="<?= $d['is_active'] ? '' : 'opacity:.55' ?>">
          <td>
            <form method="post" style="display:flex;gap:6px">
              <input type="hidden" name="action" value="rename">
              <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
              <input type="text" name="name" value="<?= e($d['name']) ?>" maxlength="60" style="min-height:38px;width:160px">
              <button class="btn btn-light btn-sm" type="submit">Rename</button>
            </form>
            <?php if ($isHere): ?><span class="pill pill-ok">this browser</span><?php endif; ?>
          </td>
          <td><?= e(DEVICE_KINDS[$d['kind']] ?? $d['kind']) ?></td>
          <td><?= $d['last_seen_at'] ? date('m/d g:i A', strtotime($d['last_seen_at'])) : '—' ?></td>
          <td><?= e($d['last_user'] ?: '—') ?></td>
          <td><span class="pill <?= $d['is_active'] ? 'pill-ok' : 'pill-void' ?>"><?= $d['is_active'] ? 'on' : 'off' ?></span></td>
          <td>
            <form method="post"<?= $d['is_active'] ? ' onsubmit="return confirm(\'Switch off ' . e($d['name']) . '? Anyone signed in on it will be signed out.\')"' : '' ?>>
              <input type="hidden" name="action" value="<?= $d['is_active'] ? 'off' : 'on' ?>">
              <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
              <button class="btn btn-sm <?= $d['is_active'] ? 'btn-red' : 'btn-green' ?>" type="submit">
                <?= $d['is_active'] ? 'Switch off' : 'Switch on' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$devices): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--ink-soft);padding:30px">No devices registered yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
