<?php
$pageTitle = 'Promotions';
$pageSubtitle = 'Send a one-off SMS promotion to your clients';
$activeNav = 'promotions';
require_once __DIR__ . '/includes/layout_start.php';

$pdo = get_db();
$csrf = admin_csrf_token();

$clients = $pdo->query("
  SELECT id, full_name, phone
  FROM clients
  WHERE phone IS NOT NULL AND phone <> ''
  ORDER BY full_name ASC
")->fetchAll();

$history = $pdo->query("
  SELECT client_id, phone, status, message, created_at
  FROM client_message_log
  WHERE message_type = 'promotion'
  ORDER BY id DESC
  LIMIT 50
")->fetchAll();

$twilioConfigured = defined('TWILIO_ACCOUNT_SID') && TWILIO_ACCOUNT_SID !== '';
?>

<?php if (!$twilioConfigured): ?>
<div class="panel" style="margin-bottom:20px;background:var(--a-danger-bg);color:var(--a-danger);">
  Twilio isn't configured yet — messages will be logged as "skipped" instead of actually sending. Add your Twilio credentials to <code>config/config.php</code> first.
</div>
<?php endif; ?>

<div class="panel" style="margin-bottom:24px;">
  <h3 style="margin:0 0 6px;">Compose Message</h3>
  <p style="color:var(--a-ink-faint);font-size:13.5px;margin:0 0 16px;">
    "Reply STOP to opt out" is added automatically. Standard SMS is ~160 characters per segment — longer messages send as multiple segments.
  </p>
  <div id="promo-alert" style="display:none;background:var(--a-danger-bg);color:var(--a-danger);padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;"></div>

  <div class="form-field">
    <label>Message</label>
    <textarea id="promo-message" rows="4" placeholder="20% off gel manicures this week only! Book at diamondnaillayton.com" maxlength="480"></textarea>
    <div class="form-hint" id="promo-char-count">0 characters</div>
  </div>

  <div class="form-field">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
      <label style="margin:0;">Recipients (<span id="promo-selected-count">0</span> of <?= count($clients) ?> selected)</label>
      <div style="display:flex;gap:10px;">
        <button type="button" class="btn btn-ghost btn-sm" onclick="toggleAllRecipients(true)">Select All</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="toggleAllRecipients(false)">Clear</button>
      </div>
    </div>
    <?php if (empty($clients)): ?>
      <div class="empty-state">No clients with a phone number on file yet.</div>
    <?php else: ?>
      <div style="max-height:280px;overflow-y:auto;border:1px solid var(--a-border);border-radius:12px;padding:6px;">
        <?php foreach ($clients as $c): ?>
          <label style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;">
            <input type="checkbox" class="promo-recipient" value="<?= (int) $c['id'] ?>" data-phone="<?= e($c['phone']) ?>" onchange="updateSelectedCount()">
            <span><strong><?= e($c['full_name']) ?></strong> <span style="color:var(--a-ink-faint);">— <?= e($c['phone']) ?></span></span>
          </label>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <button class="btn btn-primary" id="promo-submit" onclick="sendPromotion()">Send Promotion</button>
  </div>
</div>

<div class="panel" style="margin-bottom:24px;">
  <h3 style="margin:0 0 6px;">Send Gift Cards</h3>
  <p style="color:var(--a-ink-faint);font-size:13.5px;margin:0 0 16px;">
    Issues a new gift card for each selected client above and texts them the code + QR link. Uses the same recipient list you check off in the panel above.
  </p>
  <div id="promo-gc-alert" style="display:none;background:var(--a-danger-bg);color:var(--a-danger);padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;"></div>
  <div class="form-field" style="max-width:220px;">
    <label>Gift Card Amount ($ each)</label>
    <input type="number" id="promo-gc-amount" min="1" step="1" placeholder="10">
  </div>
  <button class="btn btn-secondary" id="promo-gc-submit" onclick="sendPromoGiftCards()">Issue &amp; Send Gift Cards to Selected</button>
</div>

<div class="panel">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="margin:0;">Recent Sends</h3>
    <?php if (!empty($history)): ?>
      <a href="/studio/actions/export-promotions.php" class="btn btn-secondary btn-sm">Export CSV</a>
    <?php endif; ?>
  </div>
  <?php if (empty($history)): ?>
    <div class="empty-state">No promotions sent yet.</div>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>When</th><th>Phone</th><th>Message</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($history as $h): ?>
          <tr>
            <td><?= e(date('M j, g:i A', strtotime($h['created_at']))) ?></td>
            <td><?= e($h['phone']) ?></td>
            <td><?= e(mb_strimwidth($h['message'], 0, 70, '…')) ?></td>
            <td><span class="badge-status badge-<?= e($h['status']) ?>"><?= e(ucfirst($h['status'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($csrf) ?>;

document.getElementById('promo-message').addEventListener('input', function () {
  document.getElementById('promo-char-count').textContent = this.value.length + ' characters';
});

function toggleAllRecipients(checked) {
  document.querySelectorAll('.promo-recipient').forEach((cb) => { cb.checked = checked; });
  updateSelectedCount();
}
function updateSelectedCount() {
  document.getElementById('promo-selected-count').textContent =
    document.querySelectorAll('.promo-recipient:checked').length;
}

async function sendPromotion() {
  const alertBox = document.getElementById('promo-alert');
  alertBox.style.display = 'none';
  const message = document.getElementById('promo-message').value.trim();
  const clientIds = Array.from(document.querySelectorAll('.promo-recipient:checked')).map((cb) => parseInt(cb.value, 10));

  if (!message) {
    alertBox.textContent = 'Please write a message.';
    alertBox.style.display = 'block';
    return;
  }
  if (clientIds.length === 0) {
    alertBox.textContent = 'Please select at least one recipient.';
    alertBox.style.display = 'block';
    return;
  }
  if (!confirm(`Send this message to ${clientIds.length} client(s)?`)) return;

  const btn = document.getElementById('promo-submit');
  btn.disabled = true; btn.textContent = 'Sending...';
  try {
    const res = await postJSON('/studio/actions/send-promotion.php', { csrf_token: CSRF_TOKEN, message, client_ids: clientIds });
    if (!res.ok) {
      alertBox.textContent = res.error || 'Could not send promotion.';
      alertBox.style.display = 'block';
      btn.disabled = false; btn.textContent = 'Send Promotion';
      return;
    }
    showToast(`Sent: ${res.sent}, failed: ${res.failed}, skipped: ${res.skipped}.`);
    window.location.reload();
  } catch (e) {
    alertBox.textContent = 'Network error — please try again.';
    alertBox.style.display = 'block';
    btn.disabled = false; btn.textContent = 'Send Promotion';
  }
}

async function sendPromoGiftCards() {
  const alertBox = document.getElementById('promo-gc-alert');
  alertBox.style.display = 'none';
  const amount = parseFloat(document.getElementById('promo-gc-amount').value);
  const clientIds = Array.from(document.querySelectorAll('.promo-recipient:checked')).map((cb) => parseInt(cb.value, 10));

  if (!amount || amount <= 0) {
    alertBox.textContent = 'Please enter a valid gift card amount.';
    alertBox.style.display = 'block';
    return;
  }
  if (clientIds.length === 0) {
    alertBox.textContent = 'Please select at least one recipient above.';
    alertBox.style.display = 'block';
    return;
  }
  if (!confirm(`Issue and text a $${amount} gift card to ${clientIds.length} client(s)? This creates a real redeemable gift card for each.`)) return;

  const btn = document.getElementById('promo-gc-submit');
  btn.disabled = true; btn.textContent = 'Sending...';
  try {
    const res = await postJSON('/studio/actions/send-promo-gift-cards.php', { csrf_token: CSRF_TOKEN, amount, client_ids: clientIds });
    if (!res.ok) {
      alertBox.textContent = res.error || 'Could not send gift cards.';
      alertBox.style.display = 'block';
      btn.disabled = false; btn.textContent = 'Issue & Send Gift Cards to Selected';
      return;
    }
    showToast(`Sent: ${res.sent}, failed: ${res.failed}, skipped: ${res.skipped}.`);
  } catch (e) {
    alertBox.textContent = 'Network error — please try again.';
    alertBox.style.display = 'block';
  }
  btn.disabled = false; btn.textContent = 'Issue & Send Gift Cards to Selected';
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
