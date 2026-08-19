<?php
$pageTitle = 'Staff';
$pageSubtitle = 'Nail technicians shown on the calendar and booking form';
$activeNav = 'staff';
require_once __DIR__ . '/includes/layout_start.php';

$pdo = get_db();
$csrf = admin_csrf_token();
$staff = $pdo->query('SELECT * FROM staff ORDER BY sort_order ASC, id ASC')->fetchAll();
?>

<div style="display:flex;justify-content:flex-end;margin-bottom:20px;">
  <button class="btn btn-primary" onclick="openStaffModal()">+ Add Staff Member</button>
</div>

<?php if (empty($staff)): ?>
  <div class="panel"><div class="empty-state">No staff members yet. Add your team so clients can pick a technician and the calendar can show columns per person.</div></div>
<?php else: ?>
  <div class="service-manage-grid">
    <?php foreach ($staff as $s): ?>
      <div class="service-manage-card">
        <div class="service-manage-media" style="background:<?= e($s['color_hex']) ?>;display:flex;align-items:center;justify-content:center;">
          <?php if (!empty($s['photo_url'])): ?>
            <img src="<?= e($s['photo_url']) ?>" alt="<?= e($s['full_name']) ?>" style="width:100%;height:100%;object-fit:cover;">
          <?php else: ?>
            <span style="font-family:var(--a-font-display);font-size:36px;color:#fff;"><?= e(mb_substr($s['full_name'], 0, 1)) ?></span>
          <?php endif; ?>
        </div>
        <div class="service-manage-body">
          <h4><?= e($s['full_name']) ?></h4>
          <div class="service-manage-meta">
            <span><?= e($s['title']) ?></span>
            <span style="display:inline-flex;align-items:center;gap:6px;"><span style="width:12px;height:12px;border-radius:50%;background:<?= e($s['color_hex']) ?>;display:inline-block;"></span><?= e($s['color_hex']) ?></span>
          </div>
          <?php if (!empty($s['bio'])): ?>
            <p style="font-size:12.5px;color:var(--a-ink-faint);line-height:1.5;margin:0 0 14px;"><?= e(mb_strimwidth($s['bio'], 0, 100, '…')) ?></p>
          <?php endif; ?>
          <div class="service-manage-foot">
            <label class="toggle">
              <input type="checkbox" <?= $s['is_active'] ? 'checked' : '' ?> onchange="toggleStaffActive(<?= (int)$s['id'] ?>)">
              <span class="toggle-slider"></span>
            </label>
            <div class="row-actions">
              <button class="icon-btn" title="Edit" data-staff="<?= e(json_encode($s)) ?>" onclick="openStaffModal(JSON.parse(this.dataset.staff))">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
              </button>
              <button class="icon-btn" title="Delete" onclick="deleteStaff(<?= (int)$s['id'] ?>)">
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
<div class="modal-backdrop" id="staff-modal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="staff-modal-title">Add Staff Member</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <div id="staff-alert" style="display:none;background:var(--a-danger-bg);color:var(--a-danger);padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;"></div>
      <input type="hidden" id="st-id">
      <div class="form-field">
        <label>Full Name</label>
        <input type="text" id="st-name" placeholder="Maria Gomez">
      </div>
      <div class="form-row-2">
        <div class="form-field">
          <label>Title</label>
          <input type="text" id="st-title" placeholder="Senior Nail Technician">
        </div>
        <div class="form-field">
          <label>Calendar Color</label>
          <input type="color" id="st-color" value="#b8836a" style="height:44px;padding:4px;">
        </div>
      </div>
      <div class="form-field">
        <label>Photo (optional)</label>
        <input type="text" id="st-photo" placeholder="https://...">
        <div class="form-hint">Paste a link or upload a photo below. Leave blank to show a colored initial instead.</div>
        <div style="margin-top:8px;display:flex;align-items:center;gap:10px;">
          <input type="file" id="st-photo-file" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="handleStaffPhotoUpload(this)">
          <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('st-photo-file').click()">Upload Photo</button>
          <span id="st-photo-upload-status" style="font-size:12.5px;color:var(--a-muted);"></span>
        </div>
      </div>
      <div class="form-field">
        <label>Bio (optional)</label>
        <textarea id="st-bio" rows="3" placeholder="A short professional bio shown on their profile..."></textarea>
      </div>
      <div class="form-field">
        <label>Sort Order</label>
        <input type="number" id="st-sort" placeholder="1">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-modal-close>Cancel</button>
      <button class="btn btn-primary" id="st-submit" onclick="saveStaff()">Save Staff Member</button>
    </div>
  </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($csrf) ?>;

function openStaffModal(s) {
  document.getElementById('staff-alert').style.display = 'none';
  document.getElementById('staff-modal-title').textContent = s ? 'Edit Staff Member' : 'Add Staff Member';
  document.getElementById('st-id').value = s ? s.id : '';
  document.getElementById('st-name').value = s ? s.full_name : '';
  document.getElementById('st-title').value = s ? s.title : 'Nail Technician';
  document.getElementById('st-color').value = s ? s.color_hex : '#b8836a';
  document.getElementById('st-photo').value = s ? s.photo_url : '';
  document.getElementById('st-bio').value = s ? (s.bio || '') : '';
  document.getElementById('st-sort').value = s ? s.sort_order : 0;
  openModal('staff-modal');
}

async function handleStaffPhotoUpload(input) {
  const file = input.files[0];
  if (!file) return;
  const status = document.getElementById('st-photo-upload-status');
  status.textContent = 'Uploading...';
  const res = await uploadImage(file, 'staff', CSRF_TOKEN);
  if (res.ok) {
    document.getElementById('st-photo').value = res.url;
    status.textContent = 'Uploaded.';
  } else {
    status.textContent = res.error || 'Upload failed.';
  }
  input.value = '';
}

async function saveStaff() {
  const alertBox = document.getElementById('staff-alert');
  alertBox.style.display = 'none';
  const payload = {
    csrf_token: CSRF_TOKEN,
    id: document.getElementById('st-id').value,
    full_name: document.getElementById('st-name').value.trim(),
    title: document.getElementById('st-title').value.trim(),
    color_hex: document.getElementById('st-color').value,
    photo_url: document.getElementById('st-photo').value.trim(),
    bio: document.getElementById('st-bio').value.trim(),
    sort_order: document.getElementById('st-sort').value,
  };
  const btn = document.getElementById('st-submit');
  btn.disabled = true; btn.textContent = 'Saving...';
  try {
    const res = await postJSON('/studio/actions/save-staff.php', payload);
    if (!res.ok) {
      alertBox.textContent = res.error || 'Could not save staff member.';
      alertBox.style.display = 'block';
      btn.disabled = false; btn.textContent = 'Save Staff Member';
      return;
    }
    showToast('Staff member saved.');
    window.location.reload();
  } catch (e) {
    alertBox.textContent = 'Network error — please try again.';
    alertBox.style.display = 'block';
    btn.disabled = false; btn.textContent = 'Save Staff Member';
  }
}

async function toggleStaffActive(id) {
  const res = await postJSON('/studio/actions/toggle-staff-active.php', { id, csrf_token: CSRF_TOKEN });
  if (res.ok) showToast('Staff member updated.');
  else showToast(res.error || 'Could not update.', 'error');
}

async function deleteStaff(id) {
  if (!confirm('Remove this staff member? If they have existing bookings, they will be deactivated instead of deleted.')) return;
  const res = await postJSON('/studio/actions/delete-staff.php', { id, csrf_token: CSRF_TOKEN });
  if (res.ok) { showToast(res.deactivated ? 'Staff member deactivated (has booking history).' : 'Staff member deleted.'); window.location.reload(); }
  else showToast(res.error || 'Could not delete.', 'error');
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
