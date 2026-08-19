<?php
$pageTitle = 'Gift Cards';
$pageSubtitle = 'Issue gift card codes after an in-person sale, and redeem balances at check-in';
$activeNav = 'gift-cards';
require_once __DIR__ . '/includes/layout_start.php';

$pdo = get_db();
$csrf = admin_csrf_token();
$search = trim((string) ($_GET['q'] ?? ''));

$where = '';
$params = [];
if ($search !== '') {
    $where = 'WHERE code LIKE :q OR recipient_name LIKE :q OR purchaser_name LIKE :q';
    $params['q'] = '%' . $search . '%';
}

$stmt = $pdo->prepare("SELECT * FROM gift_cards $where ORDER BY created_at DESC LIMIT 300");
$stmt->execute($params);
$cards = $stmt->fetchAll();

$scanCard = null;
$scanCode = trim((string) ($_GET['redeem'] ?? ''));
if ($scanCode !== '') {
    $scanStmt = $pdo->prepare("SELECT * FROM gift_cards WHERE code = :code AND status = 'active' AND balance > 0");
    $scanStmt->execute(['code' => $scanCode]);
    $scanCard = $scanStmt->fetch() ?: null;
}

function gift_card_status_badge(string $status): string {
    $map = ['active' => 'badge-active', 'redeemed' => 'badge-inactive', 'disabled' => 'badge-inactive'];
    $class = $map[$status] ?? 'badge-inactive';
    return '<span class="badge ' . $class . '">' . e(ucfirst($status)) . '</span>';
}
?>

<div class="panel" style="margin-bottom:20px;">
  <div class="filter-bar">
    <form method="get" style="display:flex;gap:8px;flex:1;">
      <input type="text" name="q" placeholder="Search code, recipient, purchaser..." value="<?= e($search) ?>" style="flex:1;max-width:320px;">
      <button class="btn btn-secondary btn-sm" type="submit">Search</button>
      <?php if ($search !== ''): ?><a href="/studio/gift-cards.php" class="btn btn-ghost btn-sm">Clear</a><?php endif; ?>
    </form>
    <button class="btn btn-primary" onclick="openIssueModal()">+ Issue Gift Card</button>
  </div>

  <div class="panel-body no-pad">
    <?php if (empty($cards)): ?>
      <div class="empty-state">
        <?= $search !== '' ? 'No gift cards match that search.' : 'No gift cards yet — issue one after a customer pays for it in person.' ?>
      </div>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>Code</th><th>Initial</th><th>Balance</th><th>Recipient</th><th>Purchaser</th><th>Status</th><th>Issued</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($cards as $c): ?>
          <tr>
            <td><strong style="font-family:monospace;letter-spacing:0.03em;"><?= e($c['code']) ?></strong></td>
            <td><?= e(format_price((float) $c['initial_amount'])) ?></td>
            <td><strong style="color:var(--a-rose-gold);"><?= e(format_price((float) $c['balance'])) ?></strong></td>
            <td><?= e($c['recipient_name'] ?: '—') ?></td>
            <td><?= e($c['purchaser_name'] ?: '—') ?><?php if (!empty($c['purchaser_phone'])): ?><br><span style="color:var(--a-ink-faint);font-size:12.5px;"><?= e($c['purchaser_phone']) ?></span><?php endif; ?><?php if (!empty($c['purchaser_email'])): ?><br><span style="color:var(--a-ink-faint);font-size:12.5px;"><?= e($c['purchaser_email']) ?></span><?php endif; ?></td>
            <td><?= gift_card_status_badge($c['status']) ?></td>
            <td><?= e(date('M j, Y', strtotime($c['created_at']))) ?></td>
            <td>
              <div class="row-actions">
                <?php if ($c['status'] === 'active' && (float) $c['balance'] > 0): ?>
                  <button class="btn btn-secondary btn-sm" onclick='openRedeemModal(<?= json_encode($c) ?>)'>Redeem</button>
                  <button class="icon-btn" title="Show QR code" onclick='openQrModal(<?= json_encode($c) ?>)'>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM19 14h2v2h-2zM14 19h2v2h-2zM19 19h2v2h-2z"/></svg>
                  </button>
                <?php endif; ?>
                <?php if ($c['status'] !== 'disabled'): ?>
                  <button class="icon-btn" title="Void card" onclick="voidGiftCard(<?= (int) $c['id'] ?>)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 8l8 8M16 8l-8 8"/></svg>
                  </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<!-- Issue modal -->
<div class="modal-backdrop" id="issue-modal">
  <div class="modal">
    <div class="modal-header">
      <h3>Issue Gift Card</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body" id="issue-form-body">
      <div id="issue-alert" style="display:none;background:var(--a-danger-bg);color:var(--a-danger);padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;"></div>
      <p style="font-size:13px;color:var(--a-ink-faint);margin:0 0 16px;">Only use this after the customer has already paid for the card in person — this just records it and generates a redeemable code.</p>
      <div class="form-field">
        <label>Amount</label>
        <input type="number" id="gc-amount" min="1" step="0.01" placeholder="50.00">
      </div>
      <div class="form-row-2">
        <div class="form-field">
          <label>Recipient Name (optional)</label>
          <input type="text" id="gc-recipient" placeholder="Who it's for">
        </div>
        <div class="form-field">
          <label>Purchaser Name (optional)</label>
          <input type="text" id="gc-purchaser" placeholder="Who bought it">
        </div>
      </div>
      <div class="form-row-2">
        <div class="form-field">
          <label>Purchaser Phone (optional)</label>
          <input type="tel" id="gc-purchaser-phone" placeholder="(801) 555-0123">
        </div>
        <div class="form-field">
          <label>Purchaser Email (optional)</label>
          <input type="email" id="gc-purchaser-email" placeholder="name@example.com">
        </div>
      </div>
      <div class="form-field">
        <label>Notes (optional)</label>
        <textarea id="gc-notes" rows="2"></textarea>
      </div>
    </div>
    <div id="issue-success-body" class="modal-body" style="display:none;text-align:center;">
      <div style="font-size:13.5px;color:var(--a-ink-faint);margin-bottom:10px;">Gift card issued! Give this code to the customer:</div>
      <div style="font-family:monospace;font-size:28px;font-weight:700;letter-spacing:0.05em;color:var(--a-rose-gold);background:var(--a-surface-soft);border-radius:12px;padding:18px;margin-bottom:10px;" id="issue-success-code"></div>
      <div id="issue-success-qr" style="display:flex;justify-content:center;margin-bottom:10px;"></div>
      <div style="font-size:12px;color:var(--a-ink-faint);margin-bottom:10px;">Print or screenshot this QR — scanning it opens the redeem screen instantly.</div>
      <div style="font-size:13px;color:var(--a-ink-faint);margin-bottom:16px;">Value: <strong id="issue-success-amount"></strong></div>
      <div id="issue-send-status" style="display:none;font-size:12.5px;margin-bottom:10px;"></div>
      <div style="display:flex;gap:8px;justify-content:center;">
        <button class="btn btn-secondary btn-sm" id="issue-send-sms-btn" onclick="sendIssuedQr('sms')">Text QR to Purchaser</button>
        <button class="btn btn-secondary btn-sm" id="issue-send-email-btn" onclick="sendIssuedQr('email')">Email QR to Purchaser</button>
      </div>
    </div>
    <div class="modal-footer" id="issue-form-footer">
      <button class="btn btn-secondary" data-modal-close>Cancel</button>
      <button class="btn btn-primary" id="issue-submit" onclick="submitIssue()">Issue Card</button>
    </div>
    <div class="modal-footer" id="issue-success-footer" style="display:none;">
      <button class="btn btn-primary" onclick="window.location.reload()">Done</button>
    </div>
  </div>
</div>

<!-- Redeem modal -->
<div class="modal-backdrop" id="redeem-modal">
  <div class="modal">
    <div class="modal-header">
      <h3>Redeem Gift Card</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <div id="redeem-alert" style="display:none;background:var(--a-danger-bg);color:var(--a-danger);padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;"></div>
      <input type="hidden" id="rd-code">
      <div class="form-field">
        <label>Code</label>
        <input type="text" id="rd-code-display" disabled style="font-family:monospace;">
      </div>
      <div class="form-field">
        <label>Current Balance</label>
        <input type="text" id="rd-balance-display" disabled>
      </div>
      <div class="form-field">
        <label>Amount to Redeem</label>
        <input type="number" id="rd-amount" min="0.01" step="0.01">
      </div>
      <div class="form-field">
        <label>Note (optional)</label>
        <input type="text" id="rd-note" placeholder="e.g. applied toward gel manicure">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-modal-close>Cancel</button>
      <button class="btn btn-primary" id="redeem-submit" onclick="submitRedeem()">Redeem</button>
    </div>
  </div>
</div>

<!-- QR modal -->
<div class="modal-backdrop" id="qr-modal">
  <div class="modal">
    <div class="modal-header">
      <h3>Gift Card QR Code</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body" style="text-align:center;">
      <div style="font-family:monospace;font-size:20px;font-weight:700;letter-spacing:0.05em;color:var(--a-rose-gold);margin-bottom:14px;" id="qr-code-display"></div>
      <div id="qr-code-canvas" style="display:flex;justify-content:center;"></div>
      <div style="font-size:12px;color:var(--a-ink-faint);margin:12px 0 18px;">Scan with a phone or iPad camera to jump straight to the redeem screen.</div>
      <div id="qr-send-status" style="display:none;font-size:12.5px;margin-bottom:10px;"></div>
      <div class="form-field" style="text-align:left;">
        <label>Phone</label>
        <input type="tel" id="qr-send-phone" placeholder="(801) 555-0123">
      </div>
      <button class="btn btn-secondary btn-sm" style="width:100%;margin-bottom:12px;" onclick="sendQrFromModal('sms')">Text QR</button>
      <div class="form-field" style="text-align:left;">
        <label>Email</label>
        <input type="email" id="qr-send-email" placeholder="name@example.com">
      </div>
      <button class="btn btn-secondary btn-sm" style="width:100%;" onclick="sendQrFromModal('email')">Email QR</button>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-modal-close>Close</button>
    </div>
  </div>
</div>

<script src="/studio/assets/js/qrcode.min.js"></script>
<script>
const CSRF_TOKEN = <?= json_encode($csrf) ?>;
const REDEEM_BASE_URL = <?= json_encode('https://diamondnaillayton.com/studio/gift-cards.php?redeem=') ?>;
const SCAN_CARD = <?= $scanCard ? json_encode($scanCard) : 'null' ?>;

function renderQr(containerEl, code) {
  containerEl.innerHTML = '';
  new QRCode(containerEl, {
    text: REDEEM_BASE_URL + encodeURIComponent(code),
    width: 180,
    height: 180,
    correctLevel: QRCode.CorrectLevel.M,
  });
}

let QR_MODAL_CODE = null;

function openQrModal(card) {
  QR_MODAL_CODE = card.code;
  document.getElementById('qr-code-display').textContent = card.code;
  renderQr(document.getElementById('qr-code-canvas'), card.code);
  document.getElementById('qr-send-phone').value = card.purchaser_phone || '';
  document.getElementById('qr-send-email').value = card.purchaser_email || '';
  document.getElementById('qr-send-status').style.display = 'none';
  openModal('qr-modal');
}

async function sendGiftCardQr(code, channel, destination, statusEl, btnLabel) {
  if (!destination) {
    statusEl.textContent = channel === 'sms' ? 'Enter a phone number first.' : 'Enter an email address first.';
    statusEl.style.color = 'var(--a-danger)';
    statusEl.style.display = 'block';
    return;
  }
  statusEl.textContent = 'Sending...';
  statusEl.style.color = 'var(--a-ink-faint)';
  statusEl.style.display = 'block';
  try {
    const res = await postJSON('/studio/actions/send-gift-card-qr.php', { csrf_token: CSRF_TOKEN, code, channel, destination });
    if (!res.ok) {
      statusEl.textContent = res.error || 'Could not send.';
      statusEl.style.color = 'var(--a-danger)';
      return;
    }
    statusEl.textContent = (channel === 'sms' ? 'Texted' : 'Emailed') + ' to ' + destination + '.';
    statusEl.style.color = 'var(--a-rose-gold)';
  } catch (e) {
    statusEl.textContent = 'Network error — please try again.';
    statusEl.style.color = 'var(--a-danger)';
  }
}

function sendQrFromModal(channel) {
  const statusEl = document.getElementById('qr-send-status');
  const destination = channel === 'sms'
    ? document.getElementById('qr-send-phone').value.trim()
    : document.getElementById('qr-send-email').value.trim();
  sendGiftCardQr(QR_MODAL_CODE, channel, destination, statusEl);
}

function sendIssuedQr(channel) {
  const statusEl = document.getElementById('issue-send-status');
  const destination = channel === 'sms'
    ? document.getElementById('gc-purchaser-phone').value.trim()
    : document.getElementById('gc-purchaser-email').value.trim();
  sendGiftCardQr(document.getElementById('issue-success-code').textContent, channel, destination, statusEl);
}

if (SCAN_CARD) {
  window.addEventListener('DOMContentLoaded', () => {
    openRedeemModal(SCAN_CARD);
  });
}

function openIssueModal() {
  document.getElementById('issue-alert').style.display = 'none';
  document.getElementById('issue-form-body').style.display = 'block';
  document.getElementById('issue-success-body').style.display = 'none';
  document.getElementById('issue-form-footer').style.display = 'flex';
  document.getElementById('issue-success-footer').style.display = 'none';
  document.getElementById('gc-amount').value = '';
  document.getElementById('gc-recipient').value = '';
  document.getElementById('gc-purchaser').value = '';
  document.getElementById('gc-purchaser-phone').value = '';
  document.getElementById('gc-purchaser-email').value = '';
  document.getElementById('gc-notes').value = '';
  document.getElementById('issue-send-status').style.display = 'none';
  const btn = document.getElementById('issue-submit');
  btn.disabled = false; btn.textContent = 'Issue Card';
  openModal('issue-modal');
}

async function submitIssue() {
  const alertBox = document.getElementById('issue-alert');
  alertBox.style.display = 'none';
  const amount = parseFloat(document.getElementById('gc-amount').value);
  if (!amount || amount <= 0) {
    alertBox.textContent = 'Please enter a valid amount.';
    alertBox.style.display = 'block';
    return;
  }
  const payload = {
    csrf_token: CSRF_TOKEN,
    amount,
    recipient_name: document.getElementById('gc-recipient').value.trim(),
    purchaser_name: document.getElementById('gc-purchaser').value.trim(),
    purchaser_phone: document.getElementById('gc-purchaser-phone').value.trim(),
    purchaser_email: document.getElementById('gc-purchaser-email').value.trim(),
    notes: document.getElementById('gc-notes').value.trim(),
  };
  const btn = document.getElementById('issue-submit');
  btn.disabled = true; btn.textContent = 'Issuing...';
  try {
    const res = await postJSON('/studio/actions/save-gift-card.php', payload);
    if (!res.ok) {
      alertBox.textContent = res.error || 'Could not issue gift card.';
      alertBox.style.display = 'block';
      btn.disabled = false; btn.textContent = 'Issue Card';
      return;
    }
    document.getElementById('issue-form-body').style.display = 'none';
    document.getElementById('issue-form-footer').style.display = 'none';
    document.getElementById('issue-success-code').textContent = res.code;
    document.getElementById('issue-success-amount').textContent = '$' + Number(amount).toFixed(2);
    renderQr(document.getElementById('issue-success-qr'), res.code);
    document.getElementById('issue-success-body').style.display = 'block';
    document.getElementById('issue-success-footer').style.display = 'flex';
  } catch (e) {
    alertBox.textContent = 'Network error — please try again.';
    alertBox.style.display = 'block';
    btn.disabled = false; btn.textContent = 'Issue Card';
  }
}

function openRedeemModal(card) {
  document.getElementById('redeem-alert').style.display = 'none';
  document.getElementById('rd-code').value = card.code;
  document.getElementById('rd-code-display').value = card.code;
  document.getElementById('rd-balance-display').value = '$' + Number(card.balance).toFixed(2);
  document.getElementById('rd-amount').value = card.balance;
  document.getElementById('rd-amount').max = card.balance;
  document.getElementById('rd-note').value = '';
  const btn = document.getElementById('redeem-submit');
  btn.disabled = false; btn.textContent = 'Redeem';
  openModal('redeem-modal');
}

async function submitRedeem() {
  const alertBox = document.getElementById('redeem-alert');
  alertBox.style.display = 'none';
  const amount = parseFloat(document.getElementById('rd-amount').value);
  if (!amount || amount <= 0) {
    alertBox.textContent = 'Please enter a valid amount.';
    alertBox.style.display = 'block';
    return;
  }
  const payload = {
    csrf_token: CSRF_TOKEN,
    code: document.getElementById('rd-code').value,
    amount,
    note: document.getElementById('rd-note').value.trim(),
  };
  const btn = document.getElementById('redeem-submit');
  btn.disabled = true; btn.textContent = 'Redeeming...';
  try {
    const res = await postJSON('/studio/actions/redeem-gift-card.php', payload);
    if (!res.ok) {
      alertBox.textContent = res.error || 'Could not redeem gift card.';
      alertBox.style.display = 'block';
      btn.disabled = false; btn.textContent = 'Redeem';
      return;
    }
    showToast(`Redeemed $${res.amount_applied.toFixed(2)}. Remaining balance: $${res.remaining_balance.toFixed(2)}.`);
    window.location.reload();
  } catch (e) {
    alertBox.textContent = 'Network error — please try again.';
    alertBox.style.display = 'block';
    btn.disabled = false; btn.textContent = 'Redeem';
  }
}

async function voidGiftCard(id) {
  if (!confirm('Void this gift card? It will no longer be redeemable.')) return;
  const res = await postJSON('/studio/actions/void-gift-card.php', { id, csrf_token: CSRF_TOKEN });
  if (res.ok) { showToast('Gift card voided.'); window.location.reload(); }
  else showToast(res.error || 'Could not void.', 'error');
}
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
