<?php
$pageTitle = 'Studio Settings';
$pageSubtitle = 'Business info shown across the public website';
$activeNav = 'settings';
require_once __DIR__ . '/includes/layout_start.php';

$business = get_business_settings();
$csrf = admin_csrf_token();
?>

<div style="display:grid;grid-template-columns:1.3fr 1fr;gap:22px;align-items:start;">
  <div class="panel">
    <div class="panel-header"><h2>Business Information</h2></div>
    <div class="panel-body">
      <div id="settings-alert" style="display:none;background:var(--a-danger-bg);color:var(--a-danger);padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:18px;"></div>
      <div class="form-field">
        <label>Studio Name</label>
        <input type="text" id="s-name" value="<?= e($business['business_name']) ?>">
      </div>
      <div class="form-row-2">
        <div class="form-field">
          <label>Business Email</label>
          <input type="email" id="s-email" value="<?= e($business['business_email']) ?>">
        </div>
        <div class="form-field">
          <label>Business Phone</label>
          <input type="text" id="s-phone" value="<?= e($business['business_phone']) ?>">
        </div>
      </div>
      <div class="form-field">
        <label>Address</label>
        <input type="text" id="s-address" value="<?= e($business['business_address']) ?>">
      </div>
      <div class="form-field">
        <label>Hours Note</label>
        <input type="text" id="s-hours" value="<?= e($business['hours_note']) ?>">
        <div class="form-hint">Shown in the footer, separated by " · " (e.g. "Tue–Sat 9:30am–7pm · Sun 11am–4pm · Mon Closed").</div>
      </div>
      <div class="form-row-2">
        <div class="form-field">
          <label>Instagram URL</label>
          <input type="text" id="s-instagram" value="<?= e($business['instagram_url']) ?>">
        </div>
        <div class="form-field">
          <label>Facebook URL</label>
          <input type="text" id="s-facebook" value="<?= e($business['facebook_url']) ?>">
        </div>
      </div>
      <div class="form-field">
        <label>Booking Notice</label>
        <textarea id="s-notice" rows="2"><?= e($business['booking_notice']) ?></textarea>
        <div class="form-hint">Shown to clients in the "What to expect" box on the booking section.</div>
      </div>
      <div class="form-field">
        <label>Kiosk Mode</label>
        <div style="display:flex;align-items:center;gap:12px;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600;">
            <input type="checkbox" id="s-kiosk-mode" style="width:20px;height:20px;" onchange="document.getElementById('kiosk-mode-label').textContent = this.checked ? 'Interactive Check-In' : 'Display Only';" <?= ($business['kiosk_mode'] ?? 'checkin') === 'checkin' ? 'checked' : '' ?>>
            <span id="kiosk-mode-label"><?= ($business['kiosk_mode'] ?? 'checkin') === 'checkin' ? 'Interactive Check-In' : 'Display Only' ?></span>
          </label>
        </div>
        <div class="form-hint">On = clients check themselves in with the phone keypad. Off = the kiosk just shows the promo banner as a lobby display (no interaction).</div>
      </div>
      <div class="form-field">
        <label>Kiosk Promo Banner</label>
        <textarea id="s-kiosk-promo" rows="3" placeholder="20% off gel manicures this week!"><?= e($business['kiosk_promo_message'] ?? '') ?></textarea>
        <div class="form-hint">Shown on the front-desk kiosk's check-in screen. Leave blank to hide the banner.</div>
      </div>
      <div class="form-field">
        <label>Kiosk Promo Image (optional)</label>
        <div style="display:flex;align-items:center;gap:14px;">
          <img id="kiosk-promo-preview" src="<?= e($business['kiosk_promo_image_url'] ?? '') ?>" style="width:90px;height:90px;border-radius:10px;object-fit:cover;background:#f2f2f2;<?= empty($business['kiosk_promo_image_url']) ? 'display:none;' : '' ?>">
          <div>
            <input type="file" id="kiosk-promo-file" accept="image/*" style="display:none;" onchange="uploadKioskPromoImage()">
            <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('kiosk-promo-file').click()">Upload Photo</button>
            <button type="button" class="btn btn-secondary btn-sm" id="kiosk-promo-remove-btn" onclick="removeKioskPromoImage()" style="<?= empty($business['kiosk_promo_image_url']) ? 'display:none;' : '' ?>">Remove</button>
            <input type="hidden" id="s-kiosk-promo-image" value="<?= e($business['kiosk_promo_image_url'] ?? '') ?>">
          </div>
        </div>
        <div class="form-hint">Shown inside the pink promo banner on the kiosk. Leave empty for a text-only banner.</div>
      </div>
      <div class="form-field">
        <label>"Check My Points" Button</label>
        <div style="display:flex;align-items:center;gap:12px;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600;">
            <input type="checkbox" id="s-kiosk-points-lookup" style="width:20px;height:20px;" onchange="document.getElementById('kiosk-points-lookup-label').textContent = this.checked ? 'On' : 'Off';" <?= !empty($business['kiosk_points_lookup_enabled']) ? 'checked' : '' ?>>
            <span id="kiosk-points-lookup-label"><?= !empty($business['kiosk_points_lookup_enabled']) ? 'On' : 'Off' ?></span>
          </label>
        </div>
        <div class="form-hint">On = clients can tap "Check My Points" on the kiosk's phone-entry screen to see their rewards balance without checking in.</div>
      </div>
      <button class="btn btn-primary" onclick="saveSettings()">Save Changes</button>
    </div>
  </div>

  <div class="panel">
    <div class="panel-header"><h2>Change Password</h2></div>
    <div class="panel-body">
      <div id="password-alert" style="display:none;background:var(--a-danger-bg);color:var(--a-danger);padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:18px;"></div>
      <div class="form-field">
        <label>Current Password</label>
        <input type="password" id="p-current">
      </div>
      <div class="form-field">
        <label>New Password</label>
        <input type="password" id="p-new" minlength="10">
      </div>
      <div class="form-field">
        <label>Confirm New Password</label>
        <input type="password" id="p-confirm" minlength="10">
      </div>
      <button class="btn btn-secondary btn-block" onclick="changePassword()">Update Password</button>
    </div>
  </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($csrf) ?>;

async function saveSettings() {
  const alertBox = document.getElementById('settings-alert');
  alertBox.style.display = 'none';
  const payload = {
    csrf_token: CSRF_TOKEN,
    business_name: document.getElementById('s-name').value.trim(),
    business_email: document.getElementById('s-email').value.trim(),
    business_phone: document.getElementById('s-phone').value.trim(),
    business_address: document.getElementById('s-address').value.trim(),
    hours_note: document.getElementById('s-hours').value.trim(),
    instagram_url: document.getElementById('s-instagram').value.trim(),
    facebook_url: document.getElementById('s-facebook').value.trim(),
    booking_notice: document.getElementById('s-notice').value.trim(),
    kiosk_mode: document.getElementById('s-kiosk-mode').checked ? 'checkin' : 'display',
    kiosk_promo_message: document.getElementById('s-kiosk-promo').value.trim(),
    kiosk_promo_image_url: document.getElementById('s-kiosk-promo-image').value.trim(),
    kiosk_points_lookup_enabled: document.getElementById('s-kiosk-points-lookup').checked ? 1 : 0,
  };
  const res = await postJSON('/studio/actions/save-settings.php', payload);
  if (!res.ok) {
    alertBox.textContent = res.error || 'Could not save settings.';
    alertBox.style.display = 'block';
    return;
  }
  showToast('Studio settings saved.');
}

async function uploadKioskPromoImage() {
  const input = document.getElementById('kiosk-promo-file');
  const file = input.files[0];
  if (!file) return;
  const res = await uploadImage(file, 'kiosk', CSRF_TOKEN);
  if (res.ok) {
    document.getElementById('s-kiosk-promo-image').value = res.url;
    const preview = document.getElementById('kiosk-promo-preview');
    preview.src = res.url;
    preview.style.display = '';
    document.getElementById('kiosk-promo-remove-btn').style.display = '';
  } else {
    showToast(res.error || 'Upload failed.');
  }
  input.value = '';
}

function removeKioskPromoImage() {
  document.getElementById('s-kiosk-promo-image').value = '';
  const preview = document.getElementById('kiosk-promo-preview');
  preview.src = '';
  preview.style.display = 'none';
  document.getElementById('kiosk-promo-remove-btn').style.display = 'none';
}

async function changePassword() {
  const alertBox = document.getElementById('password-alert');
  alertBox.style.display = 'none';
  const payload = {
    csrf_token: CSRF_TOKEN,
    current_password: document.getElementById('p-current').value,
    new_password: document.getElementById('p-new').value,
    confirm_password: document.getElementById('p-confirm').value,
  };
  const res = await postJSON('/studio/actions/change-password.php', payload);
  if (!res.ok) {
    alertBox.textContent = res.error || 'Could not change password.';
    alertBox.style.display = 'block';
    return;
  }
  document.getElementById('p-current').value = '';
  document.getElementById('p-new').value = '';
  document.getElementById('p-confirm').value = '';
  showToast('Password updated.');
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
