<?php
// ============================================================
//  Service photos and turn values.
//
//  Prices, durations and descriptions live in the booking admin —
//  this page owns the two things the salon floor cares about: the
//  picture on the register tile, and what a service is worth in
//  the turns rotation.
// ============================================================
$pageTitle = 'Services';
$activeNav = 'services';
$requireRole = 'manager';   // enforced by layout_start before any output
require_once __DIR__ . '/includes/layout_start.php';

const UPLOAD_DIR = __DIR__ . '/assets/uploads/services';
const MAX_BYTES  = 4 * 1024 * 1024;

$msg = ''; $err = '';

/** Accept only real images, and only ones we can name ourselves. */
function storeServiceImage(array $file, int $serviceId): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    if ($file['error'] !== UPLOAD_ERR_OK)      throw new RuntimeException('Upload failed (code ' . $file['error'] . ').');
    if ($file['size'] > MAX_BYTES)             throw new RuntimeException('That image is over 4 MB — please use a smaller one.');

    // Trust the file's contents, never its name or the browser's mime type.
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) throw new RuntimeException('That file is not an image.');
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'][$info[2]] ?? null;
    if ($ext === null) throw new RuntimeException('Please use a JPG, PNG, GIF or WebP image.');

    if (!is_dir(UPLOAD_DIR) && !@mkdir(UPLOAD_DIR, 0775, true)) {
        throw new RuntimeException('Could not create the upload folder.');
    }
    // Our own filename: an uploaded name could carry .php or path tricks.
    $name = 'service-' . $serviceId . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) {
        throw new RuntimeException('Could not save the image.');
    }
    return BASE_PATH . '/pos/assets/uploads/services/' . $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = (int)($_POST['id'] ?? 0);
        if (!fetchOne('SELECT 1 x FROM services WHERE id=?', [$id])) throw new RuntimeException('Service not found.');

        if (($_POST['action'] ?? '') === 'save') {
            $url = trim($_POST['image_url'] ?? '');
            if (!empty($_FILES['image']['name'])) {
                $url = storeServiceImage($_FILES['image'], $id);
            }
            query('UPDATE services SET image_url=?, turn_value=? WHERE id=?',
                  [$url, max(0, min(9.99, (float)$_POST['turn_value'])), $id]);
            $msg = 'Service updated.';
        } elseif (($_POST['action'] ?? '') === 'clear_image') {
            query("UPDATE services SET image_url='' WHERE id=?", [$id]);
            $msg = 'Photo removed.';
        }
        header('Location: ' . BASE_PATH . '/pos/services.php?m=' . urlencode($msg));
        exit;
    } catch (Throwable $e) { $err = $e->getMessage(); }
}
$msg = $msg ?: ($_GET['m'] ?? '');
$services = fetchAll('SELECT * FROM services ORDER BY display_order, name');
?>
<?php if ($msg): ?><div class="alert alert-ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>

<div class="card">
  <h2>💅 Service photos &amp; turns</h2>
  <p class="sub">
    The photo shows on the register tile and on the kiosk, so guests pick by sight.
    A <strong>turn</strong> is what the service is worth in the rotation — a full set is 1,
    a quick polish change might be 0.5.
    Prices and durations are set in the <a href="<?= BASE_PATH ?>/admin/">booking admin</a>.
  </p>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
<?php foreach ($services as $s): ?>
  <div class="card" style="margin:0">
    <div style="height:150px;border-radius:12px;overflow:hidden;margin-bottom:14px;
                background:<?= $s['image_url'] ? '#000' : 'linear-gradient(135deg,#efe7df,#d9c8b4)' ?>;
                display:flex;align-items:center;justify-content:center">
      <?php if ($s['image_url']): ?>
        <img src="<?= e($s['image_url']) ?>" alt="<?= e($s['name']) ?>"
             style="width:100%;height:100%;object-fit:cover">
      <?php else: ?>
        <span style="font-size:44px;opacity:.5">💅</span>
      <?php endif; ?>
    </div>

    <h2 style="font-size:17px"><?= e($s['name']) ?></h2>
    <p class="sub"><?= money($s['price']) ?> · <?= (int)$s['duration_minutes'] ?> min · <?= e($s['category']) ?></p>

    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
      <label class="field"><span>Upload a photo</span>
        <input type="file" name="image" accept="image/*" style="padding:10px"></label>
      <label class="field"><span>…or paste an image link</span>
        <input type="text" name="image_url" value="<?= e($s['image_url']) ?>" placeholder="https://…"></label>
      <label class="field"><span>Turn value</span>
        <input type="number" name="turn_value" step="0.25" min="0" max="9.99" value="<?= (float)$s['turn_value'] ?>"></label>
      <button class="btn btn-green btn-sm" type="submit">Save</button>
    </form>

    <?php if ($s['image_url']): ?>
      <form method="post" style="display:inline" onsubmit="return confirm('Remove this photo?')">
        <input type="hidden" name="action" value="clear_image">
        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <button class="btn btn-light btn-sm" type="submit" style="margin-top:8px">Remove photo</button>
      </form>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
