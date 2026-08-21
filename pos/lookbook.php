<?php
// ============================================================
//  Polish brands and the design book.
//
//  Two set-up lists that belong to the salon floor rather than the
//  books: which polish lines the shop actually carries, and the
//  photos a guest flips through while deciding what they want.
// ============================================================
$pageTitle = 'Polish & designs';
$activeNav = 'lookbook';
$requireRole = 'manager';   // enforced by layout_start before any output
require_once __DIR__ . '/includes/layout_start.php';

const DESIGN_DIR   = __DIR__ . '/assets/uploads/designs';
const DESIGN_MAX   = 20;                    // the reference till's ceiling, and plenty
const DESIGN_BYTES = 4 * 1024 * 1024;

$msg = ''; $err = '';

/** Accept only real images, and only ones we can name ourselves. */
function storeDesignImage(array $file): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Choose an image first.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload failed (code ' . $file['error'] . ').');
    if ($file['size'] > DESIGN_BYTES)     throw new RuntimeException('That image is over 4 MB — please use a smaller one.');

    // Trust the file's contents, never its name or the browser's mime type.
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) throw new RuntimeException('That file is not an image.');
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png',
            IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'][$info[2]] ?? null;
    if ($ext === null) throw new RuntimeException('Please use a JPG, PNG, GIF or WebP image.');

    if (!is_dir(DESIGN_DIR) && !@mkdir(DESIGN_DIR, 0775, true)) {
        throw new RuntimeException('Could not create the upload folder.');
    }
    $name = 'design-' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], DESIGN_DIR . '/' . $name)) {
        throw new RuntimeException('Could not save the image.');
    }
    return BASE_PATH . '/pos/assets/uploads/designs/' . $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'brands') {
            $on = array_map('intval', (array)($_POST['on'] ?? []));
            db()->exec('UPDATE pos_polish_brands SET is_active = 0');
            foreach ($on as $id) query('UPDATE pos_polish_brands SET is_active=1 WHERE id=?', [$id]);
            $msg = 'Polish brands saved.';

        } elseif ($action === 'brand_add') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') throw new RuntimeException('Type the brand name.');
            $next = (int)fetchOne('SELECT COALESCE(MAX(display_order),0)+1 v FROM pos_polish_brands')['v'];
            query('INSERT IGNORE INTO pos_polish_brands (name, is_active, display_order) VALUES (?,1,?)',
                  [mb_substr($name, 0, 80), $next]);
            $msg = 'Added ' . $name . '.';

        } elseif ($action === 'brand_del') {
            query('DELETE FROM pos_polish_brands WHERE id=?', [(int)$_POST['id']]);
            $msg = 'Brand removed.';

        } elseif ($action === 'design_add') {
            $have = (int)fetchOne('SELECT COUNT(*) v FROM pos_nail_designs')['v'];
            if ($have >= DESIGN_MAX) {
                throw new RuntimeException('That is ' . DESIGN_MAX . ' designs already — remove one first.');
            }
            $url  = storeDesignImage($_FILES['image'] ?? []);
            $next = (int)fetchOne('SELECT COALESCE(MAX(display_order),0)+1 v FROM pos_nail_designs')['v'];
            query('INSERT INTO pos_nail_designs (image_url, caption, display_order) VALUES (?,?,?)',
                  [$url, mb_substr(trim((string)($_POST['caption'] ?? '')), 0, 160), $next]);
            $msg = 'Design added.';

        } elseif ($action === 'design_del') {
            $d = fetchOne('SELECT * FROM pos_nail_designs WHERE id=?', [(int)$_POST['id']]);
            if ($d) {
                // Take the file with the row; an orphaned upload folder only
                // grows and nothing will ever point at it again.
                $path = DESIGN_DIR . '/' . basename((string)$d['image_url']);
                if (is_file($path)) @unlink($path);
                query('DELETE FROM pos_nail_designs WHERE id=?', [(int)$d['id']]);
            }
            $msg = 'Design removed.';
        }
        header('Location: ' . BASE_PATH . '/pos/lookbook.php?m=' . urlencode($msg));
        exit;
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}
if (!$msg && isset($_GET['m'])) $msg = (string)$_GET['m'];

$brands  = fetchAll('SELECT * FROM pos_polish_brands ORDER BY display_order, name');
$designs = fetchAll('SELECT * FROM pos_nail_designs ORDER BY display_order, id');
?>
<?php if ($msg): ?><div class="alert alert-ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>

<div class="card">
  <h2>💅 Polish brands</h2>
  <p class="sub">Which lines the shop actually carries. Ticking a brand does not change a price —
     it narrows the colour conversation at the chair to what is really on the wall.</p>
  <form method="post">
    <input type="hidden" name="action" value="brands">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin-bottom:14px">
      <?php foreach ($brands as $b): ?>
        <label style="display:flex;align-items:center;gap:10px;padding:12px 14px;font-weight:700;
                      border:1px solid var(--line);border-radius:var(--radius);background:var(--card)">
          <input type="checkbox" name="on[]" value="<?= (int)$b['id'] ?>" <?= $b['is_active'] ? 'checked' : '' ?>>
          <span style="flex:1"><?= e($b['name']) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <?php if (!$brands): ?><div class="empty" style="padding:20px">No brands yet — add one below.</div><?php endif; ?>
    <button class="btn btn-green" type="submit">Save brands</button>
  </form>

  <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
  <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
    <form method="post" style="display:flex;gap:10px;align-items:flex-end">
      <input type="hidden" name="action" value="brand_add">
      <label class="field" style="margin:0"><span>Add a brand</span>
        <input type="text" name="name" maxlength="80" placeholder="Nugenesis" required></label>
      <button class="btn" type="submit" style="margin-bottom:12px">Add</button>
    </form>
  </div>
  <?php if ($brands): ?>
    <p class="sub" style="margin-top:12px">Remove a brand you will never carry:</p>
    <div style="display:flex;flex-wrap:wrap;gap:8px">
      <?php foreach ($brands as $b): ?>
        <form method="post" style="display:inline"
              onsubmit="return confirm('Remove <?= e($b['name']) ?> from the list?')">
          <input type="hidden" name="action" value="brand_del">
          <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
          <button class="btn btn-light btn-sm" type="submit"><?= e($b['name']) ?> ✕</button>
        </form>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>🎨 Nail designs</h2>
  <p class="sub">The book a guest flips through while they decide.
     <?= count($designs) ?> of <?= DESIGN_MAX ?> used.</p>

  <?php if ($designs): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px;margin-bottom:18px">
      <?php foreach ($designs as $d): ?>
        <div style="border:1px solid var(--line);border-radius:var(--radius);overflow:hidden;background:var(--card)">
          <img src="<?= e($d['image_url']) ?>" alt="<?= e($d['caption']) ?>"
               style="display:block;width:100%;height:150px;object-fit:cover">
          <div style="padding:10px;display:flex;align-items:center;gap:8px">
            <span style="flex:1;font-size:13px;font-weight:700"><?= e($d['caption'] ?: '—') ?></span>
            <form method="post" style="display:inline" onsubmit="return confirm('Remove this design?')">
              <input type="hidden" name="action" value="design_del">
              <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
              <button class="btn btn-red btn-sm" type="submit">✕</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty" style="padding:30px">No designs yet.</div>
  <?php endif; ?>

  <?php if (count($designs) < DESIGN_MAX): ?>
    <form method="post" enctype="multipart/form-data"
          style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="action" value="design_add">
      <label class="field" style="margin:0"><span>Photo</span>
        <input type="file" name="image" accept="image/*" required></label>
      <label class="field" style="margin:0;min-width:220px"><span>Caption</span>
        <input type="text" name="caption" maxlength="160" placeholder="Ombre french, almond"></label>
      <button class="btn btn-green" type="submit" style="margin-bottom:12px">Upload design</button>
    </form>
    <p class="sub" style="margin-top:8px">JPG, PNG, GIF or WebP, up to 4 MB.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>📣 Special offer</h2>
  <p class="sub">The offer panel guests see on the sign-in tablet — headline, wording and a picture —
     is set with the rest of the kiosk screen, so it sits on the
     <a href="<?= BASE_PATH ?>/pos/settings.php">Settings</a> page under <strong>Kiosk &amp; birthday</strong>
     rather than being split across two places.</p>
  <a class="btn btn-light" href="<?= BASE_PATH ?>/pos/settings.php">Open kiosk settings →</a>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
