<?php
$pageTitle = 'Clients';
$activeNav = 'clients';
require_once __DIR__ . '/includes/layout_start.php';
require_once __DIR__ . '/includes/salon.php';
require_once __DIR__ . '/includes/rewards.php';

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    try {
        $c = clientUpsert(trim($_POST['full_name']), trim($_POST['phone']), trim($_POST['email']));
        header('Location: ' . BASE_PATH . '/pos/client.php?id=' . $c['id']);
        exit;
    } catch (Throwable $e) { $err = $e->getMessage(); }
}

$q    = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'recent';
$order = [
    'recent' => 'last_visit IS NULL, last_visit DESC, id DESC',
    'name'   => 'full_name ASC',
    'spend'  => 'total_spend DESC',
    'visits' => 'total_visits DESC',
    'points' => 'points DESC',
][$sort] ?? 'last_visit DESC';

$args = [];
$where = 'is_active=1';
if ($q !== '') {
    $where .= ' AND (full_name LIKE ? OR phone LIKE ? OR email LIKE ?)';
    $digits = normalisePhone($q);
    array_push($args, "%$q%", '%' . ($digits ?: $q) . '%', "%$q%");
}
$clients = fetchAll("SELECT * FROM pos_clients WHERE $where ORDER BY $order LIMIT 300", $args);
$stats = fetchOne('SELECT COUNT(*) c, COALESCE(SUM(points),0) p, COALESCE(SUM(total_spend),0) s
                   FROM pos_clients WHERE is_active=1');
?>
<?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>

<div class="stats">
  <div class="stat"><div class="v"><?= (int)$stats['c'] ?></div><div class="k">Clients</div></div>
  <div class="stat"><div class="v"><?= money($stats['s']) ?></div><div class="k">Lifetime spend</div></div>
  <div class="stat"><div class="v"><?= number_format((int)$stats['p']) ?></div><div class="k">Points outstanding</div></div>
  <div class="stat"><div class="v"><?= money(pointsToMoney((int)$stats['p'])) ?></div><div class="k">Points liability</div></div>
</div>

<form class="toolbar" method="get">
  <label class="field" style="flex:1;min-width:220px"><span>Search</span>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Name, phone or email"></label>
  <label class="field"><span>Sort by</span>
    <select name="sort" onchange="this.form.submit()">
      <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Most recent visit</option>
      <option value="name"   <?= $sort === 'name'   ? 'selected' : '' ?>>Name</option>
      <option value="spend"  <?= $sort === 'spend'  ? 'selected' : '' ?>>Lifetime spend</option>
      <option value="visits" <?= $sort === 'visits' ? 'selected' : '' ?>>Visits</option>
      <option value="points" <?= $sort === 'points' ? 'selected' : '' ?>>Points</option>
    </select></label>
  <button class="btn" type="submit">Search</button>
  <?php if ($q): ?><a class="btn btn-light" href="<?= BASE_PATH ?>/pos/clients.php">Clear</a><?php endif; ?>
</form>

<div class="table-wrap">
  <table>
    <thead><tr><th>Client</th><th>Phone</th><th class="num">Visits</th><th class="num">Spend</th>
               <th class="num">Stamps</th><th class="num">Points</th><th>Last visit</th><th></th></tr></thead>
    <tbody>
    <?php if (!$clients): ?>
      <tr><td colspan="7" style="text-align:center;color:var(--ink-soft);padding:40px">
        <?= $q ? 'No client matches that search.' : 'No clients yet — they are created automatically when a guest checks in with a phone number.' ?>
      </td></tr>
    <?php endif; ?>
    <?php foreach ($clients as $c): ?>
      <tr>
        <td><strong><?= e($c['full_name']) ?></strong>
          <?php if ($c['email']): ?><div style="font-size:12px;color:var(--ink-soft)"><?= e($c['email']) ?></div><?php endif; ?>
        </td>
        <td><?= e(formatPhone($c['phone'])) ?></td>
        <td class="num"><?= (int)$c['total_visits'] ?></td>
        <td class="num"><?= money($c['total_spend']) ?></td>
        <td class="num">
          <?php $cd = stampCard($c); ?>
          <?= $cd['on_card'] ?>/<?= $cd['per_card'] ?>
          <?php if ($cd['has_reward']): ?><span class="pill pill-ok">reward</span><?php endif; ?>
        </td>
        <td class="num"><?= number_format((int)$c['points']) ?></td>
        <td><?= $c['last_visit'] ? date('m/d/Y', strtotime($c['last_visit'])) : '—' ?></td>
        <td><a class="btn btn-light btn-sm" href="<?= BASE_PATH ?>/pos/client.php?id=<?= (int)$c['id'] ?>">Open</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card" style="margin-top:16px">
  <h2>➕ Add a client</h2>
  <p class="sub">Most clients create themselves at check-in. Use this for phone bookings or walk-ups you want on file first.</p>
  <form method="post" class="toolbar" style="margin:0">
    <input type="hidden" name="action" value="add">
    <label class="field"><span>Name</span><input type="text" name="full_name" required></label>
    <label class="field"><span>Phone</span><input type="text" name="phone" inputmode="tel" required></label>
    <label class="field"><span>Email</span><input type="text" name="email"></label>
    <button class="btn btn-green" type="submit">Add</button>
  </form>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
