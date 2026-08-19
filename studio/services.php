<?php
$pageTitle = 'Services';
$pageSubtitle = 'Manage your public-facing service menu';
$activeNav = 'services';
require_once __DIR__ . '/includes/layout_start.php';

$pdo = get_db();
$csrf = admin_csrf_token();
$services = $pdo->query('SELECT * FROM services ORDER BY sort_order ASC, id ASC')->fetchAll();
?>

<div style="display:flex;justify-content:flex-end;margin-bottom:20px;">
  <button class="btn btn-primary" onclick="openServiceModal()">+ Add Service</button>
</div>

<?php if (empty($services)): ?>
  <div class="panel"><div class="empty-state">No services yet. Add your first one to populate the public website menu.</div></div>
<?php else: ?>
  <div class="service-manage-grid">
    <?php foreach ($services as $svc): ?>
      <div class="service-manage-card">
        <div class="service-manage-media">
          <img src="<?= e($svc['image_url']) ?>" alt="<?= e($svc['name']) ?>">
        </div>
        <div class="service-manage-body">
          <h4><?= e($svc['name']) ?></h4>
          <div class="service-manage-meta">
            <span><?= e(format_duration((int)$svc['duration_minutes'])) ?></span>
            <span><?= e(format_price((float)$svc['price'])) ?></span>
          </div>
          <div class="service-manage-foot">
            <label class="toggle">
              <input type="checkbox" <?= $svc['is_active'] ? 'checked' : '' ?> onchange="toggleActive(<?= (int)$svc['id'] ?>)">
              <span class="toggle-slider"></span>
            </label>
            <div class="row-actions">
              <button class="icon-btn" title="Edit" onclick='openServiceModal(<?= json_encode($svc) ?>)'>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
              </button>
              <button class="icon-btn" title="Delete" onclick="deleteService(<?= (int)$svc['id'] ?>)">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6h14z"/></svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Add/Edit modal -->
<div class="modal-backdrop" id="service-modal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="service-modal-title">Add Service</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <div id="service-alert" style="display:none;background:var(--a-danger-bg);color:var(--a-danger);padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;"></div>
      <input type="hidden" id="svc-id">
      <div class="form-field">
        <label>Service Name</label>
        <input type="text" id="svc-name" placeholder="Gel Manicure">
      </div>
      <div class="form-field">
        <label>Description</label>
        <textarea id="svc-description" rows="3" placeholder="A short, appealing description for the public website."></textarea>
      </div>
      <div class="form-row-2">
        <div class="form-field">
          <label>Duration (minutes)</label>
          <input type="number" id="svc-duration" min="5" max="600" placeholder="45">
        </div>
        <div class="form-field">
          <label>Price ($)</label>
          <input type="number" id="svc-price" min="0" step="0.01" placeholder="45.00">
        </div>
      </div>
      <div class="form-field">
        <label>Category</label>
        <input type="text" id="svc-category" placeholder="Manicure">
      </div>
      <div class="form-field">
        <label>Image</label>
        <input type="text" id="svc-image" placeholder="https://...">
        <div class="form-hint">Paste a direct image link, or upload a photo below. Recommended: consistent crop, 900px wide.</div>
        <div style="margin-top:8px;display:flex;align-items:center;gap:10px;">
          <input type="file" id="svc-image-file" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="handleServiceImageUpload(this)">
          <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('svc-image-file').click()">Upload Photo</button>
          <span id="svc-image-upload-status" style="font-size:12.5px;color:var(--a-muted);"></span>
        </div>
      </div>
      <div class="form-field">
        <label>Sort Order</label>
        <input type="number" id="svc-sort" placeholder="1">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-modal-close>Cancel</button>
      <button class="btn btn-primary" id="svc-submit" onclick="saveService()">Save Service</button>
    </div>
  </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($csrf) ?>;

function openServiceModal(svc) {
  document.getElementById('service-alert').style.display = 'none';
  document.getElementById('service-modal-title').textContent = svc ? 'Edit Service' : 'Add Service';
  document.getElementById('svc-id').value = svc ? svc.id : '';
  document.getElementById('svc-name').value = svc ? svc.name : '';
  document.getElementById('svc-description').value = svc ? svc.description : '';
  document.getElementById('svc-duration').value = svc ? svc.duration_minutes : '';
  document.getElementById('svc-price').value = svc ? svc.price : '';
  document.getElementById('svc-category').value = svc ? svc.category : '';
  document.getElementById('svc-image').value = svc ? svc.image_url : '';
  document.getElementById('svc-sort').value = svc ? svc.sort_order : 0;
  openModal('service-modal');
}

async function handleServiceImageUpload(input) {
  const file = input.files[0];
  if (!file) return;
  const status = document.getElementById('svc-image-upload-status');
  status.textContent = 'Uploading...';
  const res = await uploadImage(file, 'services', CSRF_TOKEN);
  if (res.ok) {
    document.getElementById('svc-image').value = res.url;
    status.textContent = 'Uploaded.';
  } else {
    status.textContent = res.error || 'Upload failed.';
  }
  input.value = '';
}

async function saveService() {
  const alertBox = document.getElementById('service-alert');
  alertBox.style.display = 'none';
  const payload = {
    csrf_token: CSRF_TOKEN,
    id: document.getElementById('svc-id').value,
    name: document.getElementById('svc-name').value.trim(),
    description: document.getElementById('svc-description').value.trim(),
    duration_minutes: document.getElementById('svc-duration').value,
    price: document.getElementById('svc-price').value,
    category: document.getElementById('svc-category').value.trim(),
    image_url: document.getElementById('svc-image').value.trim(),
    sort_order: document.getElementById('svc-sort').value,
  };
  const btn = document.getElementById('svc-submit');
  btn.disabled = true; btn.textContent = 'Saving...';
  try {
    const res = await postJSON('/studio/actions/save-service.php', payload);
    if (!res.ok) {
      alertBox.textContent = res.error || 'Could not save service.';
      alertBox.style.display = 'block';
      btn.disabled = false; btn.textContent = 'Save Service';
      return;
    }
    showToast('Service saved.');
    window.location.reload();
  } catch (e) {
    alertBox.textContent = 'Network error — please try again.';
    alertBox.style.display = 'block';
    btn.disabled = false; btn.textContent = 'Save Service';
  }
}

async function toggleActive(id) {
  const res = await postJSON('/studio/actions/toggle-service-active.php', { id, csrf_token: CSRF_TOKEN });
  if (res.ok) showToast('Service updated.');
  else showToast(res.error || 'Could not update.', 'error');
}

async function deleteService(id) {
  if (!confirm('Remove this service? If it has existing bookings, it will be deactivated instead of deleted.')) return;
  const res = await postJSON('/studio/actions/delete-service.php', { id, csrf_token: CSRF_TOKEN });
  if (res.ok) { showToast(res.deactivated ? 'Service deactivated (has booking history).' : 'Service deleted.'); window.location.reload(); }
  else showToast(res.error || 'Could not delete.', 'error');
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
