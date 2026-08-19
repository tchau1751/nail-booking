<?php
$pageTitle = 'Gallery';
$pageSubtitle = 'Manage the hero, about, and studio gallery photos shown on the public site';
$activeNav = 'gallery';
require_once __DIR__ . '/includes/layout_start.php';

$pdo = get_db();
$csrf = admin_csrf_token();
$business = get_business_settings();
$photos = $pdo->query('SELECT * FROM gallery_images ORDER BY sort_order ASC, id ASC')->fetchAll();
?>

<div class="panel" style="margin-bottom:24px;">
  <h3 style="margin:0 0 16px;">Hero &amp; About Photos</h3>
  <div id="hero-about-alert" style="display:none;background:var(--a-danger-bg);color:var(--a-danger);padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;"></div>

  <div class="form-field" style="margin-bottom:16px;">
    <label>Site Logo (shown in the header)</label>
    <input type="text" id="ha-logo" value="<?= e($business['logo_url'] ?? '') ?>" placeholder="https://...">
    <div style="margin-top:8px;display:flex;align-items:center;gap:10px;">
      <img id="ha-logo-preview" src="<?= e($business['logo_url'] ?? '') ?>" style="height:44px;<?= empty($business['logo_url']) ? 'display:none;' : '' ?>">
      <input type="file" id="ha-logo-file" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="handleHeroAboutUpload(this, 'logo', 'ha-logo')">
      <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('ha-logo-file').click()">Upload Photo</button>
      <span id="ha-logo-status" style="font-size:12.5px;color:var(--a-muted);"></span>
    </div>
    <div class="form-hint">Leave blank to show the plain text/monogram brand mark instead. PNG with a transparent background works best.</div>
  </div>

  <div class="form-row-2">
    <div class="form-field">
      <label>Hero Image (main homepage banner)</label>
      <input type="text" id="ha-hero" value="<?= e($business['hero_image_url'] ?? '') ?>" placeholder="https://...">
      <div style="margin-top:8px;display:flex;align-items:center;gap:10px;">
        <input type="file" id="ha-hero-file" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="handleHeroAboutUpload(this, 'hero', 'ha-hero')">
        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('ha-hero-file').click()">Upload Photo</button>
        <span id="ha-hero-status" style="font-size:12.5px;color:var(--a-muted);"></span>
      </div>
    </div>
    <div class="form-field">
      <label>Hero Card Image (small floating card on hero)</label>
      <input type="text" id="ha-hero-card" value="<?= e($business['hero_card_image_url'] ?? '') ?>" placeholder="https://...">
      <div style="margin-top:8px;display:flex;align-items:center;gap:10px;">
        <input type="file" id="ha-hero-card-file" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="handleHeroAboutUpload(this, 'hero', 'ha-hero-card')">
        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('ha-hero-card-file').click()">Upload Photo</button>
        <span id="ha-hero-card-status" style="font-size:12.5px;color:var(--a-muted);"></span>
      </div>
    </div>
  </div>

  <div class="form-row-2" style="margin-top:16px;">
    <div class="form-field">
      <label>About Image (main)</label>
      <input type="text" id="ha-about" value="<?= e($business['about_image_url'] ?? '') ?>" placeholder="https://...">
      <div style="margin-top:8px;display:flex;align-items:center;gap:10px;">
        <input type="file" id="ha-about-file" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="handleHeroAboutUpload(this, 'about', 'ha-about')">
        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('ha-about-file').click()">Upload Photo</button>
        <span id="ha-about-status" style="font-size:12.5px;color:var(--a-muted);"></span>
      </div>
    </div>
    <div class="form-field">
      <label>About Floating Image (small overlapping photo)</label>
      <input type="text" id="ha-about-float" value="<?= e($business['about_float_image_url'] ?? '') ?>" placeholder="https://...">
      <div style="margin-top:8px;display:flex;align-items:center;gap:10px;">
        <input type="file" id="ha-about-float-file" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="handleHeroAboutUpload(this, 'about', 'ha-about-float')">
        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('ha-about-float-file').click()">Upload Photo</button>
        <span id="ha-about-float-status" style="font-size:12.5px;color:var(--a-muted);"></span>
      </div>
    </div>
  </div>

  <div style="margin-top:20px;">
    <button class="btn btn-primary" id="ha-submit" onclick="saveHeroAbout()">Save Hero &amp; About Photos</button>
  </div>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
  <h3 style="margin:0;">Studio Gallery</h3>
  <div style="display:flex;gap:10px;align-items:center;">
    <span id="bulk-upload-status" style="font-size:12.5px;color:var(--a-muted);"></span>
    <input type="file" id="bulk-upload-files" accept="image/jpeg,image/png,image/webp" multiple style="display:none;" onchange="handleBulkUpload(this)">
    <button class="btn btn-secondary" onclick="document.getElementById('bulk-upload-files').click()">+ Bulk Upload</button>
    <button class="btn btn-primary" onclick="openGalleryModal()">+ Add Photo</button>
  </div>
</div>

<?php if (empty($photos)): ?>
  <div class="panel"><div class="empty-state">No gallery photos yet. Add your first one.</div></div>
<?php else: ?>
  <div class="service-manage-grid">
    <?php foreach ($photos as $p): ?>
      <div class="service-manage-card">
        <div class="service-manage-media">
          <img src="<?= e($p['image_url']) ?>" alt="<?= e($p['alt_text']) ?>">
        </div>
        <div class="service-manage-body">
          <h4><?= e($p['alt_text'] ?: 'Untitled photo') ?></h4>
          <div class="service-manage-meta">
            <span>Layout: <?= e($p['layout_class'] ?: 'standard') ?></span>
            <span>Order: <?= (int) $p['sort_order'] ?></span>
          </div>
          <div class="service-manage-foot">
            <div class="row-actions">
              <button class="icon-btn" title="Edit" onclick='openGalleryModal(<?= json_encode($p) ?>)'>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
              </button>
              <button class="icon-btn" title="Delete" onclick="deleteGalleryImage(<?= (int) $p['id'] ?>)">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6h14z"/></svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Add/Edit gallery photo modal -->
<div class="modal-backdrop" id="gallery-modal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="gallery-modal-title">Add Photo</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <div id="gallery-alert" style="display:none;background:var(--a-danger-bg);color:var(--a-danger);padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;"></div>
      <input type="hidden" id="gal-id">
      <div class="form-field">
        <label>Photo</label>
        <input type="text" id="gal-image" placeholder="https://...">
        <div style="margin-top:8px;display:flex;align-items:center;gap:10px;">
          <input type="file" id="gal-image-file" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="handleGalleryImageUpload(this)">
          <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('gal-image-file').click()">Upload Photo</button>
          <span id="gal-image-status" style="font-size:12.5px;color:var(--a-muted);"></span>
        </div>
      </div>
      <div class="form-field">
        <label>Alt Text / Caption</label>
        <input type="text" id="gal-alt" placeholder="Studio detail">
      </div>
      <div class="form-row-2">
        <div class="form-field">
          <label>Layout</label>
          <select id="gal-class">
            <option value="">Standard</option>
            <option value="wide">Wide</option>
            <option value="tall">Tall</option>
          </select>
        </div>
        <div class="form-field">
          <label>Sort Order</label>
          <input type="number" id="gal-sort" placeholder="0">
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-modal-close>Cancel</button>
      <button class="btn btn-primary" id="gal-submit" onclick="saveGalleryImage()">Save Photo</button>
    </div>
  </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($csrf) ?>;
let nextGallerySortOrder = <?= (int) (empty($photos) ? 0 : max(array_column($photos, 'sort_order')) + 1) ?>;

async function handleBulkUpload(input) {
  const files = Array.from(input.files || []);
  if (!files.length) return;
  const status = document.getElementById('bulk-upload-status');
  let uploaded = 0;
  let failed = 0;
  for (const file of files) {
    status.textContent = `Uploading ${uploaded + failed + 1} of ${files.length}...`;
    try {
      const uploadRes = await uploadImage(file, 'gallery', CSRF_TOKEN);
      if (!uploadRes.ok) { failed++; continue; }
      const saveRes = await postJSON('/studio/actions/save-gallery-image.php', {
        csrf_token: CSRF_TOKEN,
        image_url: uploadRes.url,
        alt_text: '',
        layout_class: '',
        sort_order: nextGallerySortOrder++,
      });
      if (saveRes.ok) uploaded++; else failed++;
    } catch (e) {
      failed++;
    }
  }
  input.value = '';
  status.textContent = '';
  if (failed) {
    showToast(`Uploaded ${uploaded} photo${uploaded === 1 ? '' : 's'}, ${failed} failed.`, 'error');
  } else {
    showToast(`Uploaded ${uploaded} photo${uploaded === 1 ? '' : 's'}.`);
  }
  if (uploaded) window.location.reload();
}

async function handleHeroAboutUpload(input, subdir, targetFieldId) {
  const file = input.files[0];
  if (!file) return;
  const statusEl = document.getElementById(targetFieldId + '-status');
  statusEl.textContent = 'Uploading...';
  const res = await uploadImage(file, subdir, CSRF_TOKEN);
  if (res.ok) {
    document.getElementById(targetFieldId).value = res.url;
    const preview = document.getElementById(targetFieldId + '-preview');
    if (preview) { preview.src = res.url; preview.style.display = ''; }
    statusEl.textContent = 'Uploaded.';
  } else {
    statusEl.textContent = res.error || 'Upload failed.';
  }
  input.value = '';
}

async function saveHeroAbout() {
  const alertBox = document.getElementById('hero-about-alert');
  alertBox.style.display = 'none';
  const payload = {
    csrf_token: CSRF_TOKEN,
    logo_url: document.getElementById('ha-logo').value.trim(),
    hero_image_url: document.getElementById('ha-hero').value.trim(),
    hero_card_image_url: document.getElementById('ha-hero-card').value.trim(),
    about_image_url: document.getElementById('ha-about').value.trim(),
    about_float_image_url: document.getElementById('ha-about-float').value.trim(),
  };
  const btn = document.getElementById('ha-submit');
  btn.disabled = true; btn.textContent = 'Saving...';
  try {
    const res = await postJSON('/studio/actions/save-hero-about.php', payload);
    if (!res.ok) {
      alertBox.textContent = res.error || 'Could not save.';
      alertBox.style.display = 'block';
    } else {
      showToast('Hero & about photos saved.');
    }
  } catch (e) {
    alertBox.textContent = 'Network error — please try again.';
    alertBox.style.display = 'block';
  }
  btn.disabled = false; btn.textContent = 'Save Hero & About Photos';
}

function openGalleryModal(photo) {
  document.getElementById('gallery-alert').style.display = 'none';
  document.getElementById('gallery-modal-title').textContent = photo ? 'Edit Photo' : 'Add Photo';
  document.getElementById('gal-id').value = photo ? photo.id : '';
  document.getElementById('gal-image').value = photo ? photo.image_url : '';
  document.getElementById('gal-alt').value = photo ? photo.alt_text : '';
  document.getElementById('gal-class').value = photo ? photo.layout_class : '';
  document.getElementById('gal-sort').value = photo ? photo.sort_order : 0;
  document.getElementById('gal-image-status').textContent = '';
  openModal('gallery-modal');
}

async function handleGalleryImageUpload(input) {
  const file = input.files[0];
  if (!file) return;
  const status = document.getElementById('gal-image-status');
  status.textContent = 'Uploading...';
  const res = await uploadImage(file, 'gallery', CSRF_TOKEN);
  if (res.ok) {
    document.getElementById('gal-image').value = res.url;
    status.textContent = 'Uploaded.';
  } else {
    status.textContent = res.error || 'Upload failed.';
  }
  input.value = '';
}

async function saveGalleryImage() {
  const alertBox = document.getElementById('gallery-alert');
  alertBox.style.display = 'none';
  const payload = {
    csrf_token: CSRF_TOKEN,
    id: document.getElementById('gal-id').value,
    image_url: document.getElementById('gal-image').value.trim(),
    alt_text: document.getElementById('gal-alt').value.trim(),
    layout_class: document.getElementById('gal-class').value,
    sort_order: document.getElementById('gal-sort').value,
  };
  if (!payload.image_url) {
    alertBox.textContent = 'Please add a photo (upload or paste a URL).';
    alertBox.style.display = 'block';
    return;
  }
  const btn = document.getElementById('gal-submit');
  btn.disabled = true; btn.textContent = 'Saving...';
  try {
    const res = await postJSON('/studio/actions/save-gallery-image.php', payload);
    if (!res.ok) {
      alertBox.textContent = res.error || 'Could not save photo.';
      alertBox.style.display = 'block';
      btn.disabled = false; btn.textContent = 'Save Photo';
      return;
    }
    showToast('Photo saved.');
    window.location.reload();
  } catch (e) {
    alertBox.textContent = 'Network error — please try again.';
    alertBox.style.display = 'block';
    btn.disabled = false; btn.textContent = 'Save Photo';
  }
}

async function deleteGalleryImage(id) {
  if (!confirm('Remove this photo from the gallery?')) return;
  const res = await postJSON('/studio/actions/delete-gallery-image.php', { id, csrf_token: CSRF_TOKEN });
  if (res.ok) { showToast('Photo deleted.'); window.location.reload(); }
  else showToast(res.error || 'Could not delete.', 'error');
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
