<?php
$pageTitle = 'Expenses';
$activeNav = 'reports';
$requireRole = 'manager';   // enforced by layout_start before any output
require_once __DIR__ . '/includes/layout_start.php';

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['action'] ?? '') === 'add') {
            query('INSERT INTO pos_expenses (tenant_id, expense_date, category, description, amount, admin_id)
                   VALUES (?,?,?,?,?,?)', [
                tenantId(),
                $_POST['expense_date'] ?: date('Y-m-d'),
                trim($_POST['category']) ?: 'Supplies',
                trim($_POST['description']),
                abs((float)$_POST['amount']),
                $admin['id'] ?? null,
            ]);
            $msg = 'Expense recorded.';
        } elseif (($_POST['action'] ?? '') === 'delete') {
            query('DELETE FROM pos_expenses WHERE id=? AND tenant_id=?', [(int)$_POST['id'], tenantId()]);
            $msg = 'Expense deleted.';
        }
        header('Location: ' . BASE_PATH . '/pos/expenses.php?m=' . urlencode($msg));
        exit;
    } catch (Throwable $e) { $err = $e->getMessage(); }
}
$msg = $msg ?: ($_GET['m'] ?? '');

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-t');
$rows = fetchAll('SELECT e.*, u.name AS who FROM pos_expenses e
                  LEFT JOIN admin_users u ON u.id=e.admin_id
                  WHERE e.tenant_id = ? AND e.expense_date BETWEEN ? AND ?
                  ORDER BY e.expense_date DESC, e.id DESC', [tenantId(), $from, $to]);
$byCat = fetchAll('SELECT category, SUM(amount) v FROM pos_expenses
                   WHERE tenant_id = ? AND expense_date BETWEEN ? AND ? GROUP BY category ORDER BY v DESC',
                  [tenantId(), $from, $to]);
$total = array_sum(array_column($rows, 'amount'));
$categories = ['Supplies','Rent','Utilities','Payroll','Marketing','Equipment','Licences & fees','Insurance','Other'];
?>
<?php if ($msg): ?><div class="alert alert-ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>

<div class="toolbar">
  <a class="btn btn-light" href="<?= BASE_PATH ?>/pos/reports.php">← Reports</a>
</div>

<div class="card">
  <h2>➕ Record an expense</h2>
  <p class="sub">Everything you spend to run the salon. These feed the profit &amp; loss report.</p>
  <form method="post" class="toolbar" style="margin:0">
    <input type="hidden" name="action" value="add">
    <label class="field"><span>Date</span><input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required></label>
    <label class="field"><span>Category</span>
      <select name="category">
        <?php foreach ($categories as $c): ?><option><?= e($c) ?></option><?php endforeach; ?>
      </select></label>
    <label class="field" style="flex:1;min-width:200px"><span>Description</span>
      <input type="text" name="description" placeholder="e.g. Gel restock from supplier"></label>
    <label class="field"><span>Amount</span><input type="number" step="0.01" name="amount" required></label>
    <button class="btn btn-green" type="submit">Add</button>
  </form>
</div>

<form class="toolbar" method="get">
  <label class="field"><span>From</span><input type="date" name="from" value="<?= e($from) ?>"></label>
  <label class="field"><span>To</span><input type="date" name="to" value="<?= e($to) ?>"></label>
  <button class="btn" type="submit">Apply</button>
</form>

<div class="stats">
  <div class="stat"><div class="v"><?= money($total) ?></div><div class="k">Total in range</div></div>
  <?php foreach (array_slice($byCat, 0, 3) as $c): ?>
    <div class="stat"><div class="v"><?= money($c['v']) ?></div><div class="k"><?= e($c['category']) ?></div></div>
  <?php endforeach; ?>
</div>

<div class="table-wrap">
  <table>
    <thead><tr><th>Date</th><th>Category</th><th>Description</th><th class="num">Amount</th><th>By</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="6" style="text-align:center;color:var(--ink-soft);padding:40px">No expenses in this range.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= date('m/d/Y', strtotime($r['expense_date'])) ?></td>
        <td><?= e($r['category']) ?></td>
        <td><?= e($r['description']) ?></td>
        <td class="num"><?= money($r['amount']) ?></td>
        <td><?= e($r['who'] ?: '—') ?></td>
        <td>
          <form method="post" onsubmit="return confirm('Delete this expense?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-red btn-sm" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
