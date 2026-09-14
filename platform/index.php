<?php
// ============================================================
//  Platform — every salon on the server.
//
//  Open a salon, move it between plans, suspend it. What each salon
//  has switched on is shown against what its plan allows, so an
//  over-limit salon is visible before its owner calls about it.
// ============================================================
require_once __DIR__ . '/../includes/platform.php';
$me = platformRequire();

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!platformCsrfValid($_POST['_csrf'] ?? null)) throw new RuntimeException('That form went stale. Open the page again.');
        switch ($_POST['action'] ?? '') {
            case 'create':
                $tid  = tenantCreate($_POST);
                $slug = tenantFind($tid)['slug'] ?? '';
                $msg  = 'Salon opened. The owner signs in at ' . APP_URL . '/admin/login.php and guests book at '
                      . APP_URL . '/?salon=' . $slug;
                break;
            case 'plan':
                tenantSetPlan((int)$_POST['tenant_id'], (string)($_POST['plan'] ?? ''));
                $msg = 'Plan changed.';
                break;
            case 'status':
                tenantSetStatus((int)$_POST['tenant_id'], (string)($_POST['status'] ?? ''));
                $msg = 'Status changed.';
                break;
        }
        header('Location: ' . BASE_PATH . '/platform/?m=' . urlencode($msg));
        exit;
    } catch (Throwable $e) { $err = $e->getMessage(); }
}
$msg = $msg ?: ($_GET['m'] ?? '');

$plans   = fetchAll('SELECT * FROM plans ORDER BY display_order');
$tenants = fetchAll('SELECT t.*, p.code AS plan_code, p.name AS plan_name, p.price_month,
                            p.max_devices, p.max_employees, p.max_users
                       FROM tenants t LEFT JOIN plans p ON p.id = t.plan_id
                      ORDER BY t.id');
$audit   = fetchAll('SELECT a.*, pa.name AS who, t.name AS salon FROM platform_audit a
                     LEFT JOIN platform_admins pa ON pa.id = a.admin_id
                     LEFT JOIN tenants t ON t.id = a.tenant_id
                     ORDER BY a.id DESC LIMIT 25');

$counts = array_count_values(array_column($tenants, 'status'));
$monthly = 0.0;
foreach ($tenants as $t) {
    if (in_array($t['status'], ['active', 'past_due'], true)) $monthly += (float)$t['price_month'];
}
$csrf = platformCsrf();

/** "2 / 3", or "2" when the plan has no limit, flagged when over. */
function usageCell(int $used, $max): string {
    if ($max === null) return (string)$used;
    $over = $used > (int)$max;
    return '<span' . ($over ? ' style="color:var(--red);font-weight:800" title="Over the plan"' : '') . '>'
         . $used . ' / ' . (int)$max . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Platform — salons</title>
<link rel="stylesheet" href="<?= BASE_PATH ?>/pos/assets/pos.css">
</head>
<body data-theme="blue-lavender">
<header class="topbar">
  <div class="brand">🏢 <span>Platform</span></div>
  <div class="topright">
    <span style="font-size:13px;opacity:.85"><?= e($me['name']) ?></span>
    <a class="btn btn-ghost" href="<?= BASE_PATH ?>/platform/logout.php">Sign out</a>
  </div>
</header>
<main class="page">

<?php if ($msg): ?><div class="alert alert-ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>

<div class="stats">
  <div class="stat"><div class="v"><?= count($tenants) ?></div><div class="k">Salons</div></div>
  <div class="stat"><div class="v"><?= (int)($counts['active'] ?? 0) ?></div><div class="k">Active</div></div>
  <div class="stat"><div class="v"><?= (int)($counts['trial'] ?? 0) ?></div><div class="k">On trial</div></div>
  <div class="stat"><div class="v"><?= (int)($counts['past_due'] ?? 0) + (int)($counts['suspended'] ?? 0) ?></div><div class="k">Past due or suspended</div></div>
  <div class="stat"><div class="v">$<?= number_format($monthly, 0) ?></div><div class="k">Monthly plans, active + past due</div></div>
</div>

<div class="card">
  <h2>Salons</h2>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Salon</th><th>Plan</th><th>Status</th><th class="num">Devices</th><th class="num">Technicians</th>
                 <th class="num">Logins</th><th class="num">Sales, 30 days</th></tr></thead>
      <tbody>
      <?php foreach ($tenants as $t): $u = tenantUsage((int)$t['id']); ?>
        <tr style="<?= $t['status'] === 'cancelled' ? 'opacity:.5' : '' ?>">
          <td><strong><?= e($t['name']) ?></strong>
            <div style="font-size:12px;color:var(--ink-soft)">
              <a href="<?= e(BASE_PATH . '/?salon=' . $t['slug']) ?>" target="_blank" rel="noopener">?salon=<?= e($t['slug']) ?></a>
              · since <?= date('m/d/Y', strtotime($t['created_at'])) ?>
              <?php if ($t['status'] === 'trial' && $t['trial_ends_on']): ?> · trial ends <?= date('m/d', strtotime($t['trial_ends_on'])) ?><?php endif; ?>
            </div></td>
          <td>
            <form method="post" style="display:flex;gap:6px">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="action" value="plan">
              <input type="hidden" name="tenant_id" value="<?= (int)$t['id'] ?>">
              <select name="plan" style="min-height:38px">
                <?php foreach ($plans as $p): ?>
                  <option value="<?= e($p['code']) ?>" <?= $p['code'] === $t['plan_code'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-light btn-sm" type="submit">Set</button>
            </form>
          </td>
          <td>
            <form method="post" style="display:flex;gap:6px"
                  onsubmit="return this.status.value !== 'suspended' && this.status.value !== 'cancelled' || confirm('Stop new sign-ins at <?= e($t['name']) ?>?')">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="action" value="status">
              <input type="hidden" name="tenant_id" value="<?= (int)$t['id'] ?>">
              <select name="status" style="min-height:38px">
                <?php foreach (TENANT_STATUSES as $k => $label): ?>
                  <option value="<?= e($k) ?>" <?= $k === $t['status'] ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-light btn-sm" type="submit">Set</button>
            </form>
          </td>
          <td class="num"><?= usageCell($u['devices'], $t['max_devices']) ?></td>
          <td class="num"><?= usageCell($u['employees'], $t['max_employees']) ?></td>
          <td class="num"><?= usageCell($u['users'], $t['max_users']) ?></td>
          <td class="num"><?= $u['sales_30d'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="sub" style="margin-top:12px">Suspended and cancelled salons cannot sign in. People already on a till finish
     what they are doing, and nothing a salon owns is ever deleted from here.</p>
</div>

<div class="card">
  <h2>➕ Open a salon</h2>
  <p class="sub">Creates the salon with its starter settings, opening hours, policy forms and polish list, and an
     owner who can sign in straight away and set everything else up.</p>
  <form method="post" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="create">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px">
      <label class="field"><span>Salon name</span><input type="text" name="name" required maxlength="160"></label>
      <label class="field"><span>Booking address (optional)</span><input type="text" name="slug" maxlength="60" placeholder="made from the name"></label>
      <label class="field"><span>Plan</span>
        <select name="plan">
          <?php foreach ($plans as $p): ?>
            <option value="<?= e($p['code']) ?>" <?= $p['code'] === 'professional' ? 'selected' : '' ?>><?= e($p['name']) ?> — $<?= number_format((float)$p['price_month'], 0) ?>/mo</option>
          <?php endforeach; ?>
        </select></label>
      <label class="field"><span>Start as</span>
        <select name="status"><option value="trial">Trial (14 days)</option><option value="active">Active</option></select></label>
      <label class="field"><span>Owner's name</span><input type="text" name="owner_name" maxlength="120"></label>
      <label class="field"><span>Owner's email (their username)</span><input type="email" name="owner_email" required></label>
      <label class="field"><span>Owner's first password</span><input type="password" name="owner_password" required minlength="10" autocomplete="new-password"></label>
    </div>
    <button class="btn btn-green" type="submit">Open salon</button>
  </form>
</div>

<div class="card">
  <h2>Plans</h2>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Plan</th><th class="num">Per month</th><th class="num">Devices</th><th class="num">Technicians</th><th class="num">Logins</th><th class="num">Locations</th></tr></thead>
      <tbody>
      <?php foreach ($plans as $p): ?>
        <tr><td><strong><?= e($p['name']) ?></strong></td>
            <td class="num">$<?= number_format((float)$p['price_month'], 0) ?></td>
            <?php foreach (['max_devices', 'max_employees', 'max_users', 'max_locations'] as $col): ?>
              <td class="num"><?= $p[$col] === null ? 'no limit' : (int)$p[$col] ?></td>
            <?php endforeach; ?></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <h2>Recent platform activity</h2>
  <div class="table-wrap">
    <table>
      <thead><tr><th>When</th><th>Who</th><th>What</th><th>Salon</th><th>Detail</th></tr></thead>
      <tbody>
      <?php foreach ($audit as $a): ?>
        <tr><td><?= date('m/d g:i A', strtotime($a['created_at'])) ?></td>
            <td><?= e($a['who'] ?: '—') ?></td>
            <td><?= e(str_replace('_', ' ', $a['action'])) ?></td>
            <td><?= e($a['salon'] ?: '—') ?></td>
            <td><?= e($a['detail']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$audit): ?><tr><td colspan="5" style="color:var(--ink-soft)">Nothing yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

</main>
</body>
</html>
