<?php
// ============================================================
//  Consent / policy forms. The guest reads on the tablet and
//  signs with a finger; the signature is stored as a PNG data
//  URL against the client, and the wording is snapshotted so a
//  later edit to the template can't change what they signed.
// ============================================================
$pageTitle = 'Consent form';
$activeNav = 'clients';
$requireRole = 'front_desk';
require_once __DIR__ . '/includes/layout_start.php';
require_once __DIR__ . '/includes/salon.php';

$set = posSettings();
$biz = settings();
$tid = tenantId();
$err = ''; $savedId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $clientId = (int)$_POST['client_id'];
        if (!clientFind($clientId)) {
            throw new RuntimeException('Client not found.');
        }
        $sig = $_POST['signature'] ?? '';
        if (strpos($sig, 'data:image/png;base64,') !== 0 || strlen($sig) < 500) {
            throw new RuntimeException('Please sign in the box before saving.');
        }
        if (trim($_POST['signed_name'] ?? '') === '') throw new RuntimeException('Please type the name of the person signing.');

        query('INSERT INTO pos_consents (tenant_id, client_id, form_key, form_title, form_body, signature, signed_name)
               VALUES (?,?,?,?,?,?,?)', [
            $tid, $clientId, $_POST['form_key'], $_POST['form_title'], $_POST['form_body'],
            $sig, trim($_POST['signed_name']),
        ]);
        $savedId = (int)db()->lastInsertId();
    } catch (Throwable $e) { $err = $e->getMessage(); }
}

// Viewing a signed copy.
if (isset($_GET['view']) || $savedId) {
    $id = $savedId ?: (int)$_GET['view'];
    $c  = fetchOne('SELECT c.*, cl.full_name, cl.phone FROM pos_consents c
                    JOIN pos_clients cl ON cl.id=c.client_id WHERE c.id=? AND c.tenant_id=?', [$id, $tid]);
    if (!$c) { echo '<div class="alert alert-err">Form not found.</div>';
               require __DIR__ . '/includes/layout_end.php'; exit; }
    ?>
    <?php if ($savedId): ?><div class="alert alert-ok no-print">Signed and stored.</div><?php endif; ?>
    <div class="toolbar no-print">
      <a class="btn btn-light" href="<?= BASE_PATH ?>/pos/client.php?id=<?= (int)$c['client_id'] ?>">← Back to client</a>
      <button class="btn btn-blue" type="button" onclick="window.print()">🖨 Print</button>
    </div>
    <div class="card" style="max-width:760px;margin:0 auto">
      <div style="text-align:center;border-bottom:1px solid var(--line);padding-bottom:14px;margin-bottom:18px">
        <h2 style="font-family:Georgia,serif;font-size:24px"><?= e($biz['business_name'] ?? '') ?></h2>
        <div style="font-size:13px;color:var(--ink-soft)">
          <?= e($set['owner_name']) ?><?= $set['owner_name'] ? ', Owner · ' : '' ?><?= e($set['owner_phone']) ?>
        </div>
      </div>
      <h2><?= e($c['form_title']) ?></h2>
      <p class="sub">Signed by <?= e($c['signed_name']) ?> on <?= date('F j, Y \a\t g:i A', strtotime($c['signed_at'])) ?></p>
      <div style="white-space:pre-wrap;line-height:1.7;margin:18px 0"><?= e($c['form_body']) ?></div>
      <div style="border-top:1px solid var(--line);padding-top:16px">
        <img src="<?= e($c['signature']) ?>" alt="Signature" style="max-width:340px;display:block">
        <div style="border-top:1px solid var(--ink);width:340px;margin-top:4px;padding-top:6px;font-size:13px">
          <?= e($c['signed_name']) ?> — <?= e($c['full_name']) ?>
        </div>
      </div>
    </div>
    <?php
    require __DIR__ . '/includes/layout_end.php';
    exit;
}

// Signing flow.
$clientId = (int)($_GET['client'] ?? 0);
$client   = clientFind($clientId);
$formKey  = $_GET['form'] ?? 'general';
$tpl      = fetchOne('SELECT * FROM pos_consent_templates WHERE tenant_id=? AND form_key=? AND is_active=1', [$tid, $formKey])
         ?: fetchOne('SELECT * FROM pos_consent_templates WHERE tenant_id=? AND is_active=1 ORDER BY id LIMIT 1', [$tid]);
$all      = fetchAll('SELECT * FROM pos_consent_templates WHERE tenant_id=? AND is_active=1 ORDER BY id', [$tid]);

if (!$client) {
    $recent = fetchAll('SELECT * FROM pos_clients WHERE tenant_id=? AND is_active=1
                        ORDER BY last_visit IS NULL, last_visit DESC, id DESC LIMIT 30', [$tid]);
    ?>
    <div class="card">
      <h2>Who is signing?</h2>
      <p class="sub">Pick a client, or <a href="<?= BASE_PATH ?>/pos/clients.php">add them first</a>.</p>
      <?php foreach ($recent as $r): ?>
        <a class="btn btn-light" style="margin:0 8px 8px 0"
           href="?client=<?= (int)$r['id'] ?>&form=<?= e($formKey) ?>"><?= e($r['full_name']) ?></a>
      <?php endforeach; ?>
    </div>
    <?php require __DIR__ . '/includes/layout_end.php'; exit;
}
if (!$tpl) {
    echo '<div class="alert alert-err">There are no policy forms set up yet — add them under Settings.</div>';
    require __DIR__ . '/includes/layout_end.php'; exit;
}
?>
<?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>

<div class="toolbar no-print">
  <a class="btn btn-light" href="<?= BASE_PATH ?>/pos/client.php?id=<?= $clientId ?>">← <?= e($client['full_name']) ?></a>
  <?php foreach ($all as $t): ?>
    <a class="btn <?= $t['form_key'] === $tpl['form_key'] ? '' : 'btn-light' ?>"
       href="?client=<?= $clientId ?>&form=<?= e($t['form_key']) ?>"><?= e($t['title']) ?></a>
  <?php endforeach; ?>
</div>

<div class="card" style="max-width:760px;margin:0 auto">
  <div style="text-align:center;border-bottom:1px solid var(--line);padding-bottom:14px;margin-bottom:18px">
    <h2 style="font-family:Georgia,serif;font-size:24px"><?= e($biz['business_name'] ?? '') ?></h2>
    <div style="font-size:13px;color:var(--ink-soft)">
      <?= e($set['owner_name']) ?><?= $set['owner_name'] ? ', Owner' : '' ?>
      <?= $set['owner_phone'] ? ' · ' . e($set['owner_phone']) : '' ?>
      <?= $set['owner_email'] ? ' · ' . e($set['owner_email']) : '' ?>
      <?= $set['license_no'] ? ' · Licence ' . e($set['license_no']) : '' ?>
    </div>
  </div>

  <h2><?= e($tpl['title']) ?></h2>
  <p class="sub">For <?= e($client['full_name']) ?> · <?= e(formatPhone($client['phone'])) ?></p>
  <div style="white-space:pre-wrap;line-height:1.7;margin:18px 0;max-height:320px;overflow-y:auto;
              padding:16px;background:#fbf9f8;border-radius:12px"><?= e($tpl['body']) ?></div>

  <form method="post" id="consentForm">
    <input type="hidden" name="client_id" value="<?= $clientId ?>">
    <input type="hidden" name="form_key" value="<?= e($tpl['form_key']) ?>">
    <input type="hidden" name="form_title" value="<?= e($tpl['title']) ?>">
    <input type="hidden" name="form_body" value="<?= e($tpl['body']) ?>">
    <input type="hidden" name="signature" id="sigData">

    <label class="field"><span>Name of the person signing</span>
      <input type="text" name="signed_name" value="<?= e($client['full_name']) ?>" required></label>

    <span class="field" style="display:block"><span style="display:block;font-size:12px;font-weight:700;
        color:var(--ink-soft);text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px">Signature</span></span>
    <canvas id="sigPad" style="width:100%;height:200px;border:2px dashed var(--line);border-radius:12px;
            background:#fff;touch-action:none;display:block"></canvas>
    <div style="display:flex;gap:10px;margin:12px 0 18px">
      <button class="btn btn-light" type="button" id="sigClear">Clear</button>
    </div>

    <button class="btn btn-green btn-lg" type="submit">Save signed form</button>
  </form>
</div>

<script>
(function () {
  var canvas = document.getElementById('sigPad');
  var ctx = canvas.getContext('2d');
  var drawing = false, dirty = false;

  // Match the backing store to the CSS size so the line isn't blurry or offset.
  function size() {
    var r = canvas.getBoundingClientRect(), dpr = window.devicePixelRatio || 1;
    var data = dirty ? canvas.toDataURL() : null;
    canvas.width = r.width * dpr;
    canvas.height = r.height * dpr;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.lineWidth = 2.5; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#2a1d18';
    if (data) { var img = new Image(); img.onload = function () { ctx.drawImage(img, 0, 0, r.width, r.height); }; img.src = data; }
  }
  size();
  window.addEventListener('resize', size);

  function pos(ev) {
    var r = canvas.getBoundingClientRect();
    var p = ev.touches ? ev.touches[0] : ev;
    return { x: p.clientX - r.left, y: p.clientY - r.top };
  }
  function start(ev) { ev.preventDefault(); drawing = true; dirty = true; var p = pos(ev); ctx.beginPath(); ctx.moveTo(p.x, p.y); }
  function move(ev)  { if (!drawing) return; ev.preventDefault(); var p = pos(ev); ctx.lineTo(p.x, p.y); ctx.stroke(); }
  function end()     { drawing = false; }

  canvas.addEventListener('mousedown', start); canvas.addEventListener('touchstart', start, { passive: false });
  canvas.addEventListener('mousemove', move);  canvas.addEventListener('touchmove', move, { passive: false });
  window.addEventListener('mouseup', end);     canvas.addEventListener('touchend', end);

  document.getElementById('sigClear').addEventListener('click', function () {
    ctx.clearRect(0, 0, canvas.width, canvas.height); dirty = false;
  });

  document.getElementById('consentForm').addEventListener('submit', function (ev) {
    if (!dirty) { ev.preventDefault(); alert('Please sign in the box first.'); return; }
    document.getElementById('sigData').value = canvas.toDataURL('image/png');
  });
})();
</script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
