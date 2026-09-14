<?php
// ============================================================
//  Staff accounts.
//
//  Managers hand out logins and till PINs. Owners do that plus
//  anything touching another owner: a manager can never create an
//  owner, edit one, or promote anybody to owner — otherwise
//  "manager" would just be "owner" with extra steps.
//
//  Five roles, each able to do everything the ones below it can:
//  technician → cashier → front desk → manager → owner.
// ============================================================
$pageTitle = 'Staff';
$activeNav = 'staff';
$requireRole = 'manager';   // enforced by layout_start before any output
require_once __DIR__ . '/includes/layout_start.php';

const MIN_PASSWORD = 8;

$msg = ''; $err = ''; $newLogin = null;

/** Reject the passwords that get a salon owned in a week. */
function passwordProblem(string $pw, string $name, string $email): ?string {
    if (strlen($pw) < MIN_PASSWORD) return 'Password must be at least ' . MIN_PASSWORD . ' characters.';
    $lower = strtolower($pw);
    $weak  = ['password','12345678','admin123','qwertyui','letmein','welcome1','nailsalon','diamond1'];
    if (in_array($lower, $weak, true))                    return 'That password is one of the first any attacker tries.';
    if (preg_match('/^(.)\1+$/', $pw))                    return 'That password is a single repeated character.';
    if ($name && stripos($pw, explode(' ', $name)[0]) !== false)  return 'Please don\'t put their name in the password.';
    if ($email && stripos($pw, explode('@', $email)[0]) !== false) return 'Please don\'t put their email in the password.';
    return null;
}

/** Only an owner may create, alter or disable another owner. */
function guardOwnerTarget(?array $user): void {
    if (($user['role'] ?? '') === 'owner' && !hasRole('owner')) {
        throw new RuntimeException('Only an owner can change an owner\'s account.');
    }
}
function guardOwnerRole(string $role): void {
    if ($role === 'owner' && !hasRole('owner')) {
        throw new RuntimeException('Only an owner can grant the owner role.');
    }
}
/** One of this salon's accounts — never another salon's, whatever id the form sends. */
function staffFind(int $id): ?array {
    return fetchOne('SELECT * FROM admin_users WHERE id=? AND tenant_id=?', [$id, tenantId()]);
}
/** The roles this person can hand out: owner only by an owner. */
function assignableRoles(): array {
    $roles = ROLES;
    if (!hasRole('owner')) unset($roles['owner']);
    return array_reverse($roles, true);   // most trusted first, the way the list reads
}
function postedRole($posted): string {
    return array_key_exists((string)$posted, ROLES) ? (string)$posted : 'cashier';
}
/** A PIN is four to eight digits and must not be a guessable run. */
function pinProblem(string $pin): ?string {
    if (!preg_match('/^\d{4,8}$/', $pin))          return 'A PIN is 4 to 8 digits.';
    if (preg_match('/^(\d)\1+$/', $pin))            return 'That PIN is one digit repeated.';
    if (strpos('0123456789', $pin) !== false)      return 'That PIN is a straight run of digits.';
    if (strpos('9876543210', $pin) !== false)      return 'That PIN is a straight run of digits.';
    if (in_array($pin, ['1234','0000','1111','2580','1379','4321'], true)) return 'That PIN is one of the first anyone tries.';
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        $me     = (int)($admin['id'] ?? 0);

        if ($action === 'create') {
            $name  = trim($_POST['name']);
            $email = strtolower(trim($_POST['email']));
            $role  = postedRole($_POST['role'] ?? '');
            $pw    = (string)$_POST['password'];

            if ($name === '')                                   throw new RuntimeException('Enter their name.');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL))     throw new RuntimeException('That email address doesn\'t look right.');
            // The email is how sign-in finds the salon, so it has to be unique
            // across every salon, not just this one.
            $taken = unscoped(function () use ($email) {
                return fetchOne('SELECT 1 x FROM admin_users WHERE email=?', [$email]);
            });
            if ($taken)                                         throw new RuntimeException('Someone already signs in with that email.');
            guardOwnerRole($role);
            if ($p = passwordProblem($pw, $name, $email))       throw new RuntimeException($p);
            if ($pw !== ($_POST['password2'] ?? ''))            throw new RuntimeException('The two passwords don\'t match.');

            query('INSERT INTO admin_users (tenant_id,name,email,password_hash,role,technician_id,is_active) VALUES (?,?,?,?,?,?,1)',
                  [tenantId(), $name, $email, password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]), $role,
                   $role === 'technician' ? ownedId('technicians', $_POST['technician_id'] ?? null) : null]);
            $msg = 'Account created for ' . $name . '.';
            $newLogin = ['name' => $name, 'email' => $email, 'role' => $role];

        } elseif ($action === 'password') {
            $id   = (int)$_POST['id'];
            $user = staffFind($id);
            if (!$user) throw new RuntimeException('Account not found.');
            guardOwnerTarget($user);
            $pw = (string)$_POST['password'];
            if ($p = passwordProblem($pw, $user['name'], $user['email'])) throw new RuntimeException($p);
            if ($pw !== ($_POST['password2'] ?? ''))                      throw new RuntimeException('The two passwords don\'t match.');

            query('UPDATE admin_users SET password_hash=? WHERE id=? AND tenant_id=?',
                  [password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]), $id, tenantId()]);
            $msg = 'New password set for ' . $user['name'] . '. Tell them in person, not by text.';

        } elseif ($action === 'role') {
            $id = (int)$_POST['id'];
            if ($id === $me) throw new RuntimeException('You can\'t change your own role — ask another owner.');
            $role = postedRole($_POST['role'] ?? '');
            $user = staffFind($id);
            if (!$user) throw new RuntimeException('Account not found.');
            // Both ends are guarded: a manager can neither touch an owner's
            // account nor hand the owner role to anybody, including themselves.
            guardOwnerTarget($user);
            guardOwnerRole($role);
            query('UPDATE admin_users SET role=? WHERE id=? AND tenant_id=?', [$role, $id, tenantId()]);
            $msg = 'Role updated.';

        } elseif ($action === 'link') {
            $id   = (int)$_POST['id'];
            $user = staffFind($id);
            if (!$user) throw new RuntimeException('Account not found.');
            guardOwnerTarget($user);
            query('UPDATE admin_users SET technician_id=? WHERE id=? AND tenant_id=?',
                  [ownedId('technicians', $_POST['technician_id'] ?? null), $id, tenantId()]);
            $msg = 'Technician link saved for ' . $user['name'] . '.';

        } elseif ($action === 'pin') {
            $id   = (int)$_POST['id'];
            $user = staffFind($id);
            if (!$user) throw new RuntimeException('Account not found.');
            guardOwnerTarget($user);
            $pin = preg_replace('/\D/', '', (string)($_POST['pin'] ?? ''));

            if ($pin === '') {   // an empty box clears it
                query('UPDATE admin_users SET pin_hash=NULL, pin_fails=0, pin_locked_until=NULL WHERE id=? AND tenant_id=?',
                      [$id, tenantId()]);
                $msg = $user['name'] . ' can no longer sign in with a PIN.';
            } else {
                if ($p = pinProblem($pin)) throw new RuntimeException($p);
                query('UPDATE admin_users SET pin_hash=?, pin_fails=0, pin_locked_until=NULL WHERE id=? AND tenant_id=?',
                      [password_hash($pin, PASSWORD_BCRYPT, ['cost' => 12]), $id, tenantId()]);
                $msg = 'Till PIN set for ' . $user['name'] . '. Tell them in person.';
            }

        } elseif ($action === 'toggle') {
            $id = (int)$_POST['id'];
            if ($id === $me) throw new RuntimeException('You can\'t switch off your own account.');
            $user = staffFind($id);
            if (!$user) throw new RuntimeException('Account not found.');
            guardOwnerTarget($user);
            // Never leave the salon with no way in.
            if ($user['is_active'] && $user['role'] === 'owner') {
                $owners = (int)fetchOne("SELECT COUNT(*) n FROM admin_users WHERE tenant_id=? AND role='owner' AND is_active=1",
                                        [tenantId()])['n'];
                if ($owners <= 1) throw new RuntimeException('That is the only active owner — promote someone else first.');
            }
            query('UPDATE admin_users SET is_active=? WHERE id=? AND tenant_id=?', [$user['is_active'] ? 0 : 1, $id, tenantId()]);
            $msg = $user['is_active'] ? $user['name'] . ' can no longer sign in.' : $user['name'] . ' can sign in again.';
        }

        if (!$newLogin) { header('Location: ' . BASE_PATH . '/pos/staff.php?m=' . urlencode($msg)); exit; }
    } catch (Throwable $e) { $err = $e->getMessage(); }
}
$msg = $msg ?: ($_GET['m'] ?? '');

$users = fetchAll('SELECT u.*, t.name AS tech_name FROM admin_users u
                   LEFT JOIN technicians t ON t.id = u.technician_id
                   WHERE u.tenant_id=?
                   ORDER BY u.is_active DESC, FIELD(u.role,"owner","manager","front_desk","cashier","technician"), u.name',
                  [tenantId()]);
$techs = fetchAll('SELECT id, name FROM technicians WHERE tenant_id=? AND is_active=1 ORDER BY display_order, name', [tenantId()]);
$roleNote = [
    'owner'      => 'Everything, and the only role that can create or change another owner',
    'manager'    => 'Everything except owner accounts — sales, refunds, reports, payroll, settings, logins, PINs, approving discounts',
    'front_desk' => 'The register, plus the queue, kiosk, clients, stamp cards and consent forms',
    'cashier'    => 'The register and receipts. A discount or a custom price still needs a manager\'s PIN',
    'technician' => 'The queue board, and clocking themselves in and out once linked to their name',
];
?>
<?php if ($msg): ?><div class="alert alert-ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>

<?php if ($newLogin): ?>
  <div class="card" style="border:2px solid var(--green)">
    <h2>✅ Account ready</h2>
    <p class="sub">Give <?= e($newLogin['name']) ?> these details <strong>in person</strong>. The password isn't
       stored anywhere readable — if it gets lost, set a new one here.</p>
    <table style="max-width:420px">
      <tr><td>Sign in at</td><td><strong><?= e(APP_URL) ?>/admin/login.php</strong></td></tr>
      <tr><td>Email</td><td><strong><?= e($newLogin['email']) ?></strong></td></tr>
      <tr><td>Password</td><td><em>the one you just typed</em></td></tr>
      <tr><td>Role</td><td><?= e(roleLabel($newLogin['role'])) ?></td></tr>
    </table>
    <a class="btn btn-light" href="<?= BASE_PATH ?>/pos/staff.php" style="margin-top:14px">Done</a>
  </div>
<?php endif; ?>

<div class="card">
  <h2>👤 Add a staff login</h2>
  <p class="sub">Only people who use the till need an account. Technicians are paid and tracked
     without one — add them under <a href="<?= BASE_PATH ?>/admin/">Technicians</a>. Give a technician a
     login only if they should clock themselves in on the queue board.</p>
  <form method="post" autocomplete="off">
    <input type="hidden" name="action" value="create">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px">
      <label class="field"><span>Name</span><input type="text" name="name" required></label>
      <label class="field"><span>Email (this is their username)</span><input type="text" name="email" required></label>
      <label class="field"><span>Role</span>
        <select name="role">
          <?php foreach (assignableRoles() as $r => $label): ?>
            <option value="<?= e($r) ?>" <?= $r === 'cashier' ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select></label>
      <label class="field"><span>Their name on the turns board (technicians)</span>
        <select name="technician_id">
          <option value="">— not a technician —</option>
          <?php foreach ($techs as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?>
        </select></label>
      <label class="field"><span>Password</span><input type="password" name="password" required minlength="<?= MIN_PASSWORD ?>" autocomplete="new-password"></label>
      <label class="field"><span>Repeat password</span><input type="password" name="password2" required autocomplete="new-password"></label>
    </div>
    <p class="sub">
      <?php foreach ($roleNote as $r => $n): ?>
        <strong><?= e(roleLabel($r)) ?>:</strong> <?= e($n) ?><br>
      <?php endforeach; ?>
    </p>
    <button class="btn btn-green" type="submit">Create account</button>
  </form>
</div>

<div class="card">
  <h2>Accounts</h2>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Technician</th><th>Status</th><th>Till PIN</th><th>New password</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): $isMe = (int)$u['id'] === (int)($admin['id'] ?? 0); ?>
        <tr style="<?= $u['is_active'] ? '' : 'opacity:.5' ?>">
          <td><strong><?= e($u['name']) ?></strong><?= $isMe ? ' <span class="pill pill-ok">you</span>' : '' ?></td>
          <td><?= e($u['email']) ?></td>
          <td>
            <?php if ($isMe || ($u['role'] === 'owner' && !hasRole('owner'))): ?>
              <?= e(roleLabel($u['role'])) ?>
            <?php else: ?>
              <form method="post" style="display:flex;gap:6px">
                <input type="hidden" name="action" value="role">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <select name="role" style="min-height:38px">
                  <?php foreach (assignableRoles() as $r => $label): ?>
                    <option value="<?= e($r) ?>" <?= roleRank($u['role']) === roleRank($r) ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-light btn-sm" type="submit">Set</button>
              </form>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($u['role'] === 'technician'): ?>
              <form method="post" style="display:flex;gap:6px">
                <input type="hidden" name="action" value="link">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <select name="technician_id" style="min-height:38px">
                  <option value="">— not linked —</option>
                  <?php foreach ($techs as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= (int)$u['technician_id'] === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-light btn-sm" type="submit">Set</button>
              </form>
            <?php else: ?>
              <span style="color:var(--ink-soft)"><?= e($u['tech_name'] ?: '—') ?></span>
            <?php endif; ?>
          </td>
          <td><span class="pill <?= $u['is_active'] ? 'pill-ok' : 'pill-void' ?>"><?= $u['is_active'] ? 'active' : 'disabled' ?></span></td>
          <td>
            <form method="post" style="display:flex;gap:6px;align-items:center" autocomplete="off">
              <input type="hidden" name="action" value="pin">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <input type="password" name="pin" inputmode="numeric" pattern="\d*" maxlength="8"
                     placeholder="<?= !empty($u['pin_hash']) ? '•••• set' : 'no PIN' ?>"
                     style="min-height:38px;width:96px" autocomplete="new-password">
              <button class="btn btn-light btn-sm" type="submit">Save</button>
            </form>
            <?php if (!empty($u['pin_locked_until']) && strtotime($u['pin_locked_until']) > time()): ?>
              <span class="pill pill-void">locked out</span>
            <?php endif; ?>
          </td>
          <td>
            <form method="post" style="display:flex;gap:6px" autocomplete="off"
                  onsubmit="return confirm('Set a new password for <?= e($u['name']) ?>?')">
              <input type="hidden" name="action" value="password">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <input type="password" name="password" placeholder="New password" required
                     minlength="<?= MIN_PASSWORD ?>" style="min-height:38px;width:150px" autocomplete="new-password">
              <input type="password" name="password2" placeholder="Repeat" required
                     style="min-height:38px;width:110px" autocomplete="new-password">
              <button class="btn btn-amber btn-sm" type="submit">Set</button>
            </form>
          </td>
          <td>
            <?php if (!$isMe): ?>
              <form method="post" onsubmit="return confirm('<?= $u['is_active'] ? 'Stop' : 'Allow' ?> <?= e($u['name']) ?> signing in?')">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button class="btn btn-sm <?= $u['is_active'] ? 'btn-red' : 'btn-green' ?>" type="submit">
                  <?= $u['is_active'] ? 'Disable' : 'Enable' ?>
                </button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="sub" style="margin-top:14px">
    Accounts are disabled, never deleted — a past sale must always show who rang it up.
  </p>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
