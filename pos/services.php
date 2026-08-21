<?php
// ============================================================
//  The service menu.
//
//  Name, price, minutes, category, turn value and the photo on the
//  tile — all of it, in one table you can work down in a sitting.
//  This used to be split across two applications: the booking admin
//  owned the prices and the till owned the photos, so setting up a
//  menu meant doing half of it in each.
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
        $action = $_POST['action'] ?? '';

        if ($action === 'menu') {
            // The whole price list in one save. Fixing thirty prices one card
            // at a time is thirty round trips nobody makes.
            foreach ($_POST['price'] ?? [] as $sid => $_) {
                $sid = (int)$sid;
                if (!fetchOne('SELECT 1 x FROM services WHERE id=?', [$sid])) continue;
                $name = trim((string)($_POST['name'][$sid] ?? ''));
                if ($name === '') throw new RuntimeException('A service cannot be left without a name.');
                query('UPDATE services SET name=?, category=?, price=?, duration_minutes=?,
                       turn_value=?, is_active=?, display_order=? WHERE id=?', [
                    mb_substr($name, 0, 160),
                    mb_substr(trim((string)($_POST['category'][$sid] ?? '')) ?: 'Other', 0, 80),
                    max(0, (float)($_POST['price'][$sid] ?? 0)),
                    max(0, min(600, (int)($_POST['duration'][$sid] ?? 30))),
                    max(0, min(9.99, (float)($_POST['turn'][$sid] ?? 1))),
                    empty($_POST['off'][$sid]) ? 1 : 0,
                    max(0, min(9999, (int)($_POST['order'][$sid] ?? 0))),
                    $sid,
                ]);
            }
            $msg = 'Menu saved.';

        } elseif ($action === 'add') {
            $name = trim((string)($_POST['new_name'] ?? ''));
            if ($name === '') throw new RuntimeException('Give the service a name.');
            $order = (int)fetchOne('SELECT COALESCE(MAX(display_order),0)+1 v FROM services')['v'];
            query('INSERT INTO services (name, category, price, duration_minutes, turn_value,
                                         display_order, is_active, description)
                   VALUES (?,?,?,?,?,?,1,"")', [
                mb_substr($name, 0, 160),
                mb_substr(trim((string)($_POST['new_category'] ?? '')) ?: 'Other', 0, 80),
                max(0, (float)($_POST['new_price'] ?? 0)),
                max(0, min(600, (int)($_POST['new_duration'] ?? 30))),
                max(0, min(9.99, (float)($_POST['new_turn'] ?? 1))),
                $order,
            ]);
            $msg = 'Added ' . $name . '.';

        } else {
            $id = (int)($_POST['id'] ?? 0);
            if (!fetchOne('SELECT 1 x FROM services WHERE id=?', [$id])) throw new RuntimeException('Service not found.');

            if ($action === 'save') {
                $url = trim($_POST['image_url'] ?? '');
                if (!empty($_FILES['image']['name'])) {
                    $url = storeServiceImage($_FILES['image'], $id);
                }
                query('UPDATE services SET image_url=? WHERE id=?', [$url, $id]);
                $msg = 'Photo updated.';
            } elseif ($action === 'clear_image') {
                query("UPDATE services SET image_url='' WHERE id=?", [$id]);
                $msg = 'Photo removed.';
            }
        }
        header('Location: ' . BASE_PATH . '/pos/services.php?m=' . urlencode($msg));
        exit;
    } catch (Throwable $e) { $err = $e->getMessage(); }
}
$msg = $msg ?: ($_GET['m'] ?? '');
$services = fetchAll('SELECT * FROM services ORDER BY display_order, name');
$categories = array_values(array_filter(array_unique(array_column($services, 'category'))));
sort($categories);
?>
<?php if ($msg): ?><div class="alert alert-ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>

<datalist id="catList">
  <?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?>
</datalist>

<div class="card">
  <h2>💵 The menu</h2>
  <p class="sub">
    Name, price, minutes and category for every service — the booking site offers these times and
    the register rings these prices. <strong>Category</strong> is the tab a service appears under at
    the till. A <strong>turn</strong> is what it is worth in the rotation: a full set is 1, a quick
    polish change 0.5, a two-hour lash set 2. <strong>Order</strong> sorts the tiles.
    Untick <strong>On</strong> to take something off the menu without deleting it — old tickets and
    bookings still point at it.
  </p>
  <form method="post">
    <input type="hidden" name="action" value="menu">
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th style="min-width:180px">Service</th><th style="width:130px">Category</th>
          <th class="num" style="width:100px">Price</th><th class="num" style="width:90px">Minutes</th>
          <th class="num" style="width:80px">Turn</th><th class="num" style="width:80px">Order</th>
          <th style="width:56px">On</th>
        </tr></thead>
        <tbody>
        <?php foreach ($services as $s): $i = (int)$s['id']; ?>
          <tr<?= $s['is_active'] ? '' : ' style="opacity:.5"' ?>>
            <td><input type="text" name="name[<?= $i ?>]" value="<?= e($s['name']) ?>" maxlength="160" style="width:100%"></td>
            <td><input type="text" name="category[<?= $i ?>]" value="<?= e($s['category']) ?>" list="catList" maxlength="80" style="width:100%"></td>
            <td class="num"><input type="number" name="price[<?= $i ?>]" value="<?= (float)$s['price'] ?>" step="0.01" min="0" style="width:90px"></td>
            <td class="num"><input type="number" name="duration[<?= $i ?>]" value="<?= (int)$s['duration_minutes'] ?>" step="5" min="0" max="600" style="width:80px"></td>
            <td class="num"><input type="number" name="turn[<?= $i ?>]" value="<?= (float)$s['turn_value'] ?>" step="0.25" min="0" max="9.99" style="width:70px"></td>
            <td class="num"><input type="number" name="order[<?= $i ?>]" value="<?= (int)$s['display_order'] ?>" step="1" min="0" style="width:70px"></td>
            <td style="text-align:center"><input type="checkbox" name="off[<?= $i ?>]" value="1" <?= $s['is_active'] ? '' : 'checked' ?> title="Tick to take it off the menu"></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <button class="btn btn-green btn-lg" type="submit" style="margin-top:14px">Save the menu</button>
  </form>
</div>

<div class="card">
  <h2>➕ Add a service</h2>
  <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;align-items:end">
    <input type="hidden" name="action" value="add">
    <label class="field" style="margin:0"><span>Name</span>
      <input type="text" name="new_name" maxlength="160" required placeholder="Gel Pedicure"></label>
    <label class="field" style="margin:0"><span>Category</span>
      <input type="text" name="new_category" list="catList" maxlength="80" placeholder="Pedicure"></label>
    <label class="field" style="margin:0"><span>Price</span>
      <input type="number" name="new_price" step="0.01" min="0" value="0"></label>
    <label class="field" style="margin:0"><span>Minutes</span>
      <input type="number" name="new_duration" step="5" min="0" max="600" value="30"></label>
    <label class="field" style="margin:0"><span>Turn</span>
      <input type="number" name="new_turn" step="0.25" min="0" max="9.99" value="1"></label>
    <button class="btn btn-green" type="submit" style="margin-bottom:12px">Add</button>
  </form>
</div>

<div class="card">
  <h2>📷 Photos</h2>
  <p class="sub">The photo shows on the register tile and on the kiosk, so guests pick by sight.</p>
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
