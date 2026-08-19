<?php
$pageTitle = 'Menu';
$pageSubtitle = 'Full price menu shown on the public "Menu" page';
$activeNav = 'menu';
require_once __DIR__ . '/includes/layout_start.php';

$pdo = get_db();
$csrf = admin_csrf_token();

$categories = $pdo->query('SELECT * FROM menu_categories ORDER BY sort_order ASC, id ASC')->fetchAll();
$itemsByCategory = [];
if (!empty($categories)) {
    $items = $pdo->query('SELECT * FROM menu_items ORDER BY category_id ASC, sort_order ASC, id ASC')->fetchAll();
    foreach ($items as $item) {
        $itemsByCategory[(int) $item['category_id']][] = $item;
    }
}
?>

<div style="display:flex;justify-content:flex-end;margin-bottom:20px;">
  <button class="btn btn-primary" onclick="openCategoryModal()">+ Add Category</button>
</div>

<?php if (empty($categories)): ?>
  <div class="panel"><div class="empty-state">No menu categories yet. Add your first one to start building the public price menu.</div></div>
<?php else: ?>
  <?php foreach ($categories as $ci => $cat): ?>
    <div class="panel" style="margin-bottom:20px;">
      <div class="panel-header" style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h2 style="margin:0;"><?= e($cat['name']) ?></h2>
          <?php if (!empty($cat['subtitle'])): ?><div style="font-size:12.5px;color:var(--a-ink-faint);"><?= e($cat['subtitle']) ?></div><?php endif; ?>
        </div>
        <div class="row-actions">
          <button class="icon-btn" title="Move up" <?= $ci === 0 ? 'disabled' : '' ?> onclick="reorderCategory(<?= (int) $cat['id'] ?>, 'up')">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
          </button>
          <button class="icon-btn" title="Move down" <?= $ci === count($categories) - 1 ? 'disabled' : '' ?> onclick="reorderCategory(<?= (int) $cat['id'] ?>, 'down')">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
          </button>
          <button class="icon-btn" title="Edit category" onclick='openCategoryModal(<?= json_encode($cat) ?>)'>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
          </button>
          <button class="icon-btn" title="Delete category" onclick="deleteCategory(<?= (int) $cat['id'] ?>)">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6h14z"/></svg>
          </button>
          <button class="btn btn-secondary btn-sm" onclick="openItemModal(<?= (int) $cat['id'] ?>)">+ Add Item</button>
        </div>
      </div>
      <div class="panel-body no-pad">
        <?php $items = $itemsByCategory[(int) $cat['id']] ?? []; ?>
        <?php if (empty($items)): ?>
          <div class="empty-state">No items in this category yet.</div>
        <?php else: ?>
          <table class="table">
            <thead><tr><th>Item</th><th>Price</th><th>Description</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($items as $ii => $item): ?>
                <tr>
                  <td><?= e($item['name']) ?></td>
                  <td><?= e($item['price_label'] ?: '—') ?></td>
                  <td style="max-width:320px;font-size:12.5px;color:var(--a-ink-faint);"><?= e($item['description'] ? mb_substr($item['description'], 0, 90) . (mb_strlen($item['description']) > 90 ? '…' : '') : '—') ?></td>
                  <td>
                    <div class="row-actions">
                      <button class="icon-btn" title="Move up" <?= $ii === 0 ? 'disabled' : '' ?> onclick="reorderItem(<?= (int) $item['id'] ?>, 'up')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                      </button>
                      <button class="icon-btn" title="Move down" <?= $ii === count($items) - 1 ? 'disabled' : '' ?> onclick="reorderItem(<?= (int) $item['id'] ?>, 'down')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                      </button>
                      <button class="icon-btn" title="Edit" onclick='openItemModal(<?= (int) $cat['id'] ?>, <?= json_encode($item) ?>)'>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                      </button>
                      <button class="icon-btn" title="Delete" onclick="deleteItem(<?= (int) $item['id'] ?>)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6h14z"/></svg>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<!-- Category modal -->
<div class="modal-backdrop" id="category-modal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="category-modal-title">Add Category</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <div id="category-alert" style="display:none;background:var(--a-danger-bg);color:var(--a-danger);padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;"></div>
      <input type="hidden" id="cat-id">
      <div class="form-field">
        <label>Category Name</label>
        <input type="text" id="cat-name" placeholder="Nail Enhancement">
      </div>
      <div class="form-field">
        <label>Subtitle (optional)</label>
        <input type="text" id="cat-subtitle" placeholder="(Under 6)">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-modal-close>Cancel</button>
      <button class="btn btn-primary" id="category-submit" onclick="saveCategory()">Save Category</button>
    </div>
  </div>
</div>

<!-- Item modal -->
<div class="modal-backdrop" id="item-modal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="item-modal-title">Add Item</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <div id="item-alert" style="display:none;background:var(--a-danger-bg);color:var(--a-danger);padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;"></div>
      <input type="hidden" id="item-id">
      <input type="hidden" id="item-category-id">
      <div class="form-field">
        <label>Item Name</label>
        <input type="text" id="item-name" placeholder="Gel Polish With Manicure">
      </div>
      <div class="form-field">
        <label>Price (optional — leave blank for a plain note line)</label>
        <input type="text" id="item-price" placeholder="$35 or $45+ or $5 / a finger nail">
      </div>
      <div class="form-field">
        <label>Description (optional)</label>
        <textarea id="item-description" rows="3" placeholder="Shown below the item name."></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-modal-close>Cancel</button>
      <button class="btn btn-primary" id="item-submit" onclick="saveItem()">Save Item</button>
    </div>
  </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($csrf) ?>;

function openCategoryModal(cat) {
  document.getElementById('category-alert').style.display = 'none';
  document.getElementById('category-modal-title').textContent = cat ? 'Edit Category' : 'Add Category';
  document.getElementById('cat-id').value = cat ? cat.id : '';
  document.getElementById('cat-name').value = cat ? cat.name : '';
  document.getElementById('cat-subtitle').value = cat ? (cat.subtitle || '') : '';
  openModal('category-modal');
}

async function saveCategory() {
  const alertBox = document.getElementById('category-alert');
  alertBox.style.display = 'none';
  const name = document.getElementById('cat-name').value.trim();
  if (!name) {
    alertBox.textContent = 'Please enter a category name.';
    alertBox.style.display = 'block';
    return;
  }
  const payload = {
    csrf_token: CSRF_TOKEN,
    id: document.getElementById('cat-id').value,
    name,
    subtitle: document.getElementById('cat-subtitle').value.trim(),
  };
  const btn = document.getElementById('category-submit');
  btn.disabled = true; btn.textContent = 'Saving...';
  const res = await postJSON('/studio/actions/save-menu-category.php', payload);
  if (!res.ok) {
    alertBox.textContent = res.error || 'Could not save category.';
    alertBox.style.display = 'block';
    btn.disabled = false; btn.textContent = 'Save Category';
    return;
  }
  showToast('Category saved.');
  window.location.reload();
}

async function deleteCategory(id) {
  if (!confirm('Delete this category and all its items? This cannot be undone.')) return;
  const res = await postJSON('/studio/actions/delete-menu-category.php', { id, csrf_token: CSRF_TOKEN });
  if (res.ok) { showToast('Category deleted.'); window.location.reload(); }
  else showToast(res.error || 'Could not delete.', 'error');
}

async function reorderCategory(id, direction) {
  const res = await postJSON('/studio/actions/reorder-menu-category.php', { id, direction, csrf_token: CSRF_TOKEN });
  if (res.ok) window.location.reload();
  else showToast(res.error || 'Could not reorder.', 'error');
}

function openItemModal(categoryId, item) {
  document.getElementById('item-alert').style.display = 'none';
  document.getElementById('item-modal-title').textContent = item ? 'Edit Item' : 'Add Item';
  document.getElementById('item-id').value = item ? item.id : '';
  document.getElementById('item-category-id').value = categoryId;
  document.getElementById('item-name').value = item ? item.name : '';
  document.getElementById('item-price').value = item ? (item.price_label || '') : '';
  document.getElementById('item-description').value = item ? (item.description || '') : '';
  openModal('item-modal');
}

async function saveItem() {
  const alertBox = document.getElementById('item-alert');
  alertBox.style.display = 'none';
  const name = document.getElementById('item-name').value.trim();
  if (!name) {
    alertBox.textContent = 'Please enter an item name.';
    alertBox.style.display = 'block';
    return;
  }
  const payload = {
    csrf_token: CSRF_TOKEN,
    id: document.getElementById('item-id').value,
    category_id: document.getElementById('item-category-id').value,
    name,
    price_label: document.getElementById('item-price').value.trim(),
    description: document.getElementById('item-description').value.trim(),
  };
  const btn = document.getElementById('item-submit');
  btn.disabled = true; btn.textContent = 'Saving...';
  const res = await postJSON('/studio/actions/save-menu-item.php', payload);
  if (!res.ok) {
    alertBox.textContent = res.error || 'Could not save item.';
    alertBox.style.display = 'block';
    btn.disabled = false; btn.textContent = 'Save Item';
    return;
  }
  showToast('Item saved.');
  window.location.reload();
}

async function deleteItem(id) {
  if (!confirm('Delete this menu item?')) return;
  const res = await postJSON('/studio/actions/delete-menu-item.php', { id, csrf_token: CSRF_TOKEN });
  if (res.ok) { showToast('Item deleted.'); window.location.reload(); }
  else showToast(res.error || 'Could not delete.', 'error');
}

async function reorderItem(id, direction) {
  const res = await postJSON('/studio/actions/reorder-menu-item.php', { id, direction, csrf_token: CSRF_TOKEN });
  if (res.ok) window.location.reload();
  else showToast(res.error || 'Could not reorder.', 'error');
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
