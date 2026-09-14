<?php
$pageTitle = 'Products';
$activeNav = 'products';
$requireRole = 'manager';   // enforced by layout_start before any output
require_once __DIR__ . '/includes/layout_start.php';

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $args = [
                trim($_POST['name']), trim($_POST['sku']), trim($_POST['barcode']) ?: null,
                trim($_POST['category']) ?: 'Retail', (float)$_POST['price'], (float)$_POST['cost'],
                (int)$_POST['stock_qty'], (int)$_POST['low_stock_at'],
                isset($_POST['is_taxable']) ? 1 : 0, isset($_POST['is_active']) ? 1 : 0,
            ];
            if ($id) {
                array_push($args, $id, tenantId());
                query('UPDATE pos_products SET name=?,sku=?,barcode=?,category=?,price=?,cost=?,stock_qty=?,
                       low_stock_at=?,is_taxable=?,is_active=? WHERE id=? AND tenant_id=?', $args);
                $msg = 'Product updated.';
            } else {
                query('INSERT INTO pos_products (tenant_id,name,sku,barcode,category,price,cost,stock_qty,
                       low_stock_at,is_taxable,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?)', array_merge([tenantId()], $args));
                $msg = 'Product added.';
            }
        } elseif ($action === 'restock') {
            query('UPDATE pos_products SET stock_qty = stock_qty + ? WHERE id=? AND tenant_id=?',
                  [(int)$_POST['qty'], (int)$_POST['id'], tenantId()]);
            $msg = 'Stock updated.';
        } elseif ($action === 'delete') {
            query('UPDATE pos_products SET is_active=0 WHERE id=? AND tenant_id=?', [(int)$_POST['id'], tenantId()]);
            $msg = 'Product retired (kept for past receipts).';
        }
    } catch (Throwable $e) { $err = $e->getMessage(); }
}

$editing  = fetchOne('SELECT * FROM pos_products WHERE id=? AND tenant_id=?', [(int)($_GET['edit'] ?? 0), tenantId()]);
$products = fetchAll('SELECT * FROM pos_products WHERE tenant_id=? ORDER BY is_active DESC, category, display_order, name', [tenantId()]);
?>
<?php if ($msg): ?><div class="alert alert-ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>

<div class="card">
  <h2><?= $editing ? 'Edit product' : 'Add a product' ?></h2>
  <p class="sub">Retail items sold at the counter. Services are managed in the admin dashboard.</p>
  <form method="post">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px">
      <label class="field"><span>Name</span><input type="text" name="name" required value="<?= e($editing['name'] ?? '') ?>"></label>
      <label class="field"><span>Category</span><input type="text" name="category" value="<?= e($editing['category'] ?? 'Retail') ?>"></label>
      <label class="field"><span>SKU</span><input type="text" name="sku" value="<?= e($editing['sku'] ?? '') ?>"></label>
      <label class="field"><span>Barcode</span><input type="text" name="barcode" value="<?= e($editing['barcode'] ?? '') ?>"></label>
      <label class="field"><span>Price</span><input type="number" step="0.01" name="price" required value="<?= e($editing['price'] ?? '0.00') ?>"></label>
      <label class="field"><span>Cost</span><input type="number" step="0.01" name="cost" value="<?= e($editing['cost'] ?? '0.00') ?>"></label>
      <label class="field"><span>Stock on hand</span><input type="number" name="stock_qty" value="<?= (int)($editing['stock_qty'] ?? 0) ?>"></label>
      <label class="field"><span>Low stock alert at</span><input type="number" name="low_stock_at" value="<?= (int)($editing['low_stock_at'] ?? 3) ?>"></label>
    </div>
    <div style="display:flex;gap:20px;margin:6px 0 16px;font-weight:700">
      <label><input type="checkbox" name="is_taxable" <?= ($editing['is_taxable'] ?? 1) ? 'checked' : '' ?>> Taxable</label>
      <label><input type="checkbox" name="is_active" <?= ($editing['is_active'] ?? 1) ? 'checked' : '' ?>> Sell on the register</label>
    </div>
    <button class="btn btn-green" type="submit"><?= $editing ? 'Save changes' : 'Add product' ?></button>
    <?php if ($editing): ?><a class="btn btn-light" href="<?= BASE_PATH ?>/pos/products.php">Cancel</a><?php endif; ?>
  </form>
</div>

<div class="table-wrap">
  <table>
    <thead><tr><th>Product</th><th>Category</th><th>Barcode</th><th class="num">Price</th><th class="num">Stock</th><th>Restock</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): ?>
      <tr style="<?= $p['is_active'] ? '' : 'opacity:.5' ?>">
        <td><strong><?= e($p['name']) ?></strong>
          <?php if ($p['sku']): ?><div style="font-size:12px;color:var(--ink-soft)"><?= e($p['sku']) ?></div><?php endif; ?>
        </td>
        <td><?= e($p['category']) ?></td>
        <td style="font-family:monospace"><?= e($p['barcode'] ?: '—') ?></td>
        <td class="num"><?= money($p['price']) ?></td>
        <td class="num">
          <?= (int)$p['stock_qty'] ?>
          <?php if ($p['stock_qty'] <= $p['low_stock_at']): ?> <span class="pill pill-low">low</span><?php endif; ?>
        </td>
        <td>
          <form method="post" style="display:flex;gap:6px">
            <input type="hidden" name="action" value="restock">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <input type="number" name="qty" value="10" style="width:84px;min-height:38px">
            <button class="btn btn-light btn-sm" type="submit">＋ Add</button>
          </form>
        </td>
        <td style="white-space:nowrap">
          <a class="btn btn-light btn-sm" href="?edit=<?= (int)$p['id'] ?>">Edit</a>
          <?php if ($p['is_active']): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Retire <?= e($p['name']) ?>?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button class="btn btn-red btn-sm" type="submit">Retire</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
