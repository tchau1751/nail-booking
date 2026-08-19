<?php
$pageTitle = 'Discounts';
$pageSubtitle = 'Named discount rules staff can apply to a booking';
$activeNav = 'discounts';
require_once __DIR__ . '/includes/layout_start.php';

$pdo = get_db();
$csrf = admin_csrf_token();
$discounts = $pdo->query('SELECT * FROM discounts ORDER BY created_at DESC')->fetchAll();
?>

<div style="display:flex;justify-content:flex-end;margin-bottom:20px;">
  <button class="btn btn-primary" onclick="openDiscountModal()">+ Add Discount</button>
</div>

<?php if (empty($discounts)): ?>
  <div class="panel"><div class="empty-state">No discounts yet. Add one so staff can apply it during booking or check-in.</div></div>
<?php else: ?>
  <div class="service-manage-grid">
    <?php foreach ($discounts as $d): ?>
      <div class="panel" style="padding:22px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px;">
          <h4 style="margin:0;font-size:16px;"><?= e($d['name']) ?></h4>
          <span class="badge <?= $d['is_active'] ? 'badge-active' : 'badge-inactive' ?>" style="cursor:pointer;" onclick="toggleDiscountActive(<?= (int) $d['id'] ?>)"><?= $d['is_active'] ? 'Active' : 'Inactive' ?></span>
        </div>
        <div style="font-size:12.5px;color:var(--a-ink-faint);margin-bottom:14px;">Type: <?= $d['type'] === 'flat' ? 'Flat Discount' : 'Percentage Off' ?></div>
        <div style="margin-bottom:16px;">
          <span style="font-family:var(--a-font-display);font-size:30px;font-weight:700;color:var(--a-rose-gold);"><?= $d['type'] === 'flat' ? '$' . number_format((float) $d['amount'], 2) : (int) $d['amount'] . '%' ?></span>
          <span style="font-size:13px;color:var(--a-ink-faint);"> off ticket</span>
        </div>
        <div style="display:flex;gap:10px;border-top:1px solid var(--a-border);padding-top:14px;">
          <button class="btn btn-secondary btn-sm" onclick='openDiscountModal(<?= json_encode($d) ?>)'>Edit</button>
          <button class="btn btn-sm" style="background:var(--a-danger);color:#fff;" onclick="deleteDiscount(<?= (int) $d['id'] ?>)">Delete</button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Add/Edit modal -->
<div class="modal-backdrop" id="discount-modal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="discount-modal-title">Add Discount</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <div id="discount-alert" style="display:none;background:var(--a-danger-bg);color:var(--a-danger);padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;"></div>
      <input type="hidden" id="disc-id">
      <div class="form-field">
        <label>Name</label>
        <input type="text" id="disc-name" placeholder="Birthday $5 Off">
      </div>
      <div class="form-row-2">
        <div class="form-field">
          <label>Type</label>
          <select id="disc-type">
            <option value="flat">Flat Discount ($)</option>
            <option value="percentage">Percentage Off (%)</option>
          </select>
        </div>
        <div class="form-field">
          <label>Amount</label>
          <input type="number" id="disc-amount" min="0" step="0.01" placeholder="5.00">
        </div>
      </div>
      <div class="form-field">
        <label class="toggle" style="display:flex;align-items:center;gap:10px;">
          <input type="checkbox" id="disc-active" checked>
          <span class="toggle-slider"></span>
          <span style="font-size:13.5px;">Active</span>
        </label>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-modal-close>Cancel</button>
      <button class="btn btn-primary" id="disc-submit" onclick="saveDiscount()">Save Discount</button>
    </div>
  </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($csrf) ?>;

function openDiscountModal(d) {
  document.getElementById('discount-alert').style.display = 'none';
  document.getElementById('discount-modal-title').textContent = d ? 'Edit Discount' : 'Add Discount';
  document.getElementById('disc-id').value = d ? d.id : '';
  document.getElementById('disc-name').value = d ? d.name : '';
  document.getElementById('disc-type').value = d ? d.type : 'flat';
  document.getElementById('disc-amount').value = d ? d.amount : '';
  document.getElementById('disc-active').checked = d ? !!Number(d.is_active) : true;
  openModal('discount-modal');
}

async function saveDiscount() {
  const alertBox = document.getElementById('discount-alert');
  alertBox.style.display = 'none';
  const payload = {
    csrf_token: CSRF_TOKEN,
    id: document.getElementById('disc-id').value,
    name: document.getElementById('disc-name').value.trim(),
    type: document.getElementById('disc-type').value,
    amount: document.getElementById('disc-amount').value,
    is_active: document.getElementById('disc-active').checked ? 1 : 0,
  };
  const btn = document.getElementById('disc-submit');
  btn.disabled = true; btn.textContent = 'Saving...';
  try {
    const res = await postJSON('/studio/actions/save-discount.php', payload);
    if (!res.ok) {
      alertBox.textContent = res.error || 'Could not save discount.';
      alertBox.style.display = 'block';
      btn.disabled = false; btn.textContent = 'Save Discount';
      return;
    }
    showToast('Discount saved.');
    window.location.reload();
  } catch (e) {
    alertBox.textContent = 'Network error — please try again.';
    alertBox.style.display = 'block';
    btn.disabled = false; btn.textContent = 'Save Discount';
  }
}

async function toggleDiscountActive(id) {
  const res = await postJSON('/studio/actions/toggle-discount-active.php', { id, csrf_token: CSRF_TOKEN });
  if (res.ok) window.location.reload();
  else showToast(res.error || 'Could not update.', 'error');
}

async function deleteDiscount(id) {
  if (!confirm('Delete this discount? Bookings that already used it keep their applied amount.')) return;
  const res = await postJSON('/studio/actions/delete-discount.php', { id, csrf_token: CSRF_TOKEN });
  if (res.ok) { showToast('Discount deleted.'); window.location.reload(); }
  else showToast(res.error || 'Could not delete.', 'error');
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
