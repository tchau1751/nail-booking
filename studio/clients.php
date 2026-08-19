<?php
/* Clients — search, add, delete, stamps, import/export. Single file, no dependencies
   beyond the studio layout. Detects table/column names at runtime. */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle    = 'Clients';
$pageSubtitle = 'Everyone who has booked or visited the studio';
$activeNav    = 'clients';

/* Buffer output so header()/redirect/CSV still work after the layout prints HTML. */
ob_start();
require_once __DIR__ . '/includes/layout_start.php';

$pdo = get_db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if (session_status() === PHP_SESSION_NONE) session_start();

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function tcols(PDO $pdo, $t) {
    try { return array_column($pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_ASSOC), 'Field'); }
    catch (Throwable $e) { return []; }
}
function pick(array $have, array $want) {
    foreach ($want as $w) if (in_array($w, $have, true)) return $w;
    return null;
}
function bail_to($url) { while (ob_get_level()) ob_end_clean(); header('Location: ' . $url); exit; }

$errors  = [];
$notices = [];
if (!empty($_SESSION['cw_flash'])) { $notices[] = $_SESSION['cw_flash']; unset($_SESSION['cw_flash']); }

/* ---------- discover schema ---------- */
$CT = null;
foreach (['clients', 'customers', 'client'] as $t) { if (tcols($pdo, $t)) { $CT = $t; break; } }
if (!$CT) $errors[] = 'No clients table found in this database.';

$cc    = $CT ? tcols($pdo, $CT) : [];
$ID    = pick($cc, ['id', 'client_id', 'ID']);
$NAME  = pick($cc, ['full_name', 'name', 'client_name', 'customer_name']);
$PHONE = pick($cc, ['phone', 'phone_number', 'mobile', 'tel']);
$EMAIL = pick($cc, ['email', 'email_address']);
$STAMP = pick($cc, ['stamp_count', 'stamps', 'loyalty_stamps']);

if ($CT && (!$ID || !$NAME)) {
    $errors[] = 'Clients table found but no id/name column. Columns are: ' . implode(', ', $cc);
}
if ($CT && $ID && !$STAMP) {
    try {
        $pdo->exec("ALTER TABLE `$CT` ADD COLUMN stamp_count INT NOT NULL DEFAULT 0");
        $STAMP = 'stamp_count';
        $notices[] = 'Added the missing stamp_count column to your clients table.';
    } catch (Throwable $e) { $errors[] = 'Could not add stamp_count: ' . $e->getMessage(); }
}

/* visits source — optional, skipped entirely if not found */
$BT = $BLINK = $BSTAT = null;
foreach (['bookings', 'appointments'] as $t) {
    $bc = tcols($pdo, $t);
    if (!$bc) continue;
    $l = pick($bc, ['client_id', 'customer_id']);
    if ($l) { $BT = $t; $BLINK = $l; $BSTAT = pick($bc, ['status']); break; }
}

/* ---------- actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $CT && $ID && $NAME) {
    $act  = $_POST['act'] ?? '';
    $back = '?q=' . urlencode($_POST['q'] ?? '');
    try {
        if (function_exists('admin_csrf_verify') && !admin_csrf_verify($_POST['csrf'] ?? null)) {
            throw new Exception('Session expired — please reload the page and try again.');
        }
        if ($act === 'stamp' && $STAMP) {
            $id  = (int)($_POST['id'] ?? 0);
            $dir = $_POST['dir'] ?? '';
            $st  = $pdo->prepare("SELECT COALESCE(`$STAMP`,0) FROM `$CT` WHERE `$ID`=?");
            $st->execute([$id]);
            $cur = (int)$st->fetchColumn();
            $earned = false;
            if ($dir === 'add')      { $new = $cur + 1; if ($new >= 10) { $new = 0; $earned = true; } }
            elseif ($dir === 'sub')  { $new = max(0, $cur - 1); }
            else                     { $new = 0; }
            $pdo->prepare("UPDATE `$CT` SET `$STAMP`=? WHERE `$ID`=?")->execute([$new, $id]);
            $_SESSION['cw_flash'] = $earned
                ? 'Reward earned — card reset to 0.'
                : "Stamps set to $new.";
        } elseif ($act === 'add') {
            $f = [$NAME]; $v = [trim($_POST['name'] ?? '')];
            if ($v[0] === '') throw new Exception('Name is required.');
            if ($PHONE) { $f[] = $PHONE; $v[] = trim($_POST['phone'] ?? ''); }
            if ($EMAIL) { $f[] = $EMAIL; $v[] = trim($_POST['email'] ?? ''); }
            $sql = "INSERT INTO `$CT` (`" . implode('`,`', $f) . "`) VALUES ("
                 . rtrim(str_repeat('?,', count($f)), ',') . ")";
            $pdo->prepare($sql)->execute($v);
            $_SESSION['cw_flash'] = 'Added "' . $v[0] . '".';
        } elseif ($act === 'del') {
            $id = (int)($_POST['id'] ?? 0);
            $st = $pdo->prepare("SELECT `$NAME` FROM `$CT` WHERE `$ID`=?");
            $st->execute([$id]);
            $nm = $st->fetchColumn();
            $pdo->prepare("DELETE FROM `$CT` WHERE `$ID`=?")->execute([$id]);
            $_SESSION['cw_flash'] = 'Deleted "' . $nm . '".';
        } elseif ($act === 'import' && $STAMP && !empty($_FILES['csv']['tmp_name'])) {
            $fh = fopen($_FILES['csv']['tmp_name'], 'r');
            fgetcsv($fh); // header row
            $n = 0;
            while (($r = fgetcsv($fh)) !== false) {
                if (count($r) < 2) continue;
                $nm = trim((string)($r[0] ?? ''));
                $ph = trim((string)($r[1] ?? ''));
                if (!array_key_exists(4, $r)) continue;
                $sv = max(0, min(10, (int)$r[4]));
                if ($PHONE && $ph !== '') {
                    $u = $pdo->prepare("UPDATE `$CT` SET `$STAMP`=? WHERE `$PHONE`=?");
                    $u->execute([$sv, $ph]);
                } else {
                    $u = $pdo->prepare("UPDATE `$CT` SET `$STAMP`=? WHERE `$NAME`=?");
                    $u->execute([$sv, $nm]);
                }
                $n += $u->rowCount();
            }
            fclose($fh);
            $_SESSION['cw_flash'] = "Import finished — $n client(s) updated.";
        }
    } catch (Throwable $e) {
        $_SESSION['cw_flash'] = 'Error: ' . $e->getMessage();
    }
    bail_to($back);
}

/* ---------- fetch rows ---------- */
$q    = trim((string)($_GET['q'] ?? ''));
$rows = [];
if ($CT && $ID && $NAME) {
    $sel   = ["c.`$ID` AS id", "c.`$NAME` AS nm"];
    $sel[] = $PHONE ? "c.`$PHONE` AS ph" : "'' AS ph";
    $sel[] = $EMAIL ? "c.`$EMAIL` AS em" : "'' AS em";
    $sel[] = $STAMP ? "COALESCE(c.`$STAMP`,0) AS stamps" : "0 AS stamps";
    $sel[] = $BT
        ? "(SELECT COUNT(*) FROM `$BT` b WHERE b.`$BLINK`=c.`$ID`"
          . ($BSTAT ? " AND b.`$BSTAT`='completed'" : '') . ") AS visits"
        : "0 AS visits";

    $sql = 'SELECT ' . implode(', ', $sel) . " FROM `$CT` c";
    $par = [];
    if ($q !== '') {
        $w = ["c.`$NAME` LIKE ?"]; $par[] = "%$q%";
        if ($PHONE) { $w[] = "c.`$PHONE` LIKE ?"; $par[] = "%$q%"; }
        if ($EMAIL) { $w[] = "c.`$EMAIL` LIKE ?"; $par[] = "%$q%"; }
        $sql .= ' WHERE ' . implode(' OR ', $w);
    }
    $sql .= " ORDER BY c.`$NAME`";
    try {
        $st = $pdo->prepare($sql);
        $st->execute($par);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $errors[] = 'Query failed: ' . $e->getMessage();
    }
}

/* ---------- CSV export ---------- */
if (isset($_GET['export'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="clients-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name', 'Phone', 'Email', 'Visits', 'Stamps']);
    foreach ($rows as $r) fputcsv($out, [$r['nm'], $r['ph'], $r['em'], $r['visits'], $r['stamps']]);
    fclose($out);
    exit;
}

$BP   = defined('BASE_PATH') ? BASE_PATH : '';
$csrf = function_exists('admin_csrf_token') ? admin_csrf_token() : '';
?>
<style>
  .cw-bar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:14px}
  .cw-bar input[type=text]{flex:1;min-width:180px;padding:10px 12px;border:1px solid #ccc;border-radius:6px;font-size:13px}
  .cw-btn{padding:10px 15px;border:0;border-radius:6px;cursor:pointer;font-weight:700;font-size:13px;text-decoration:none;display:inline-block}
  .cw-tbl{width:100%;border-collapse:collapse;font-size:13px}
  .cw-tbl th{background:#f5f5f5;color:#333;padding:11px;text-align:left;border-bottom:2px solid #ddd}
  .cw-tbl td{padding:11px;border-bottom:1px solid #eee}
  .cw-mini{padding:5px 9px;border:0;border-radius:4px;cursor:pointer;font-weight:700;font-size:12px}
  .cw-note{padding:12px 14px;border-radius:8px;margin-bottom:14px;font-size:13px}
  .cw-ok{background:#e8f5e9;color:#1b5e20;border:1px solid #c8e6c9}
  .cw-err{background:#fdecea;color:#8c1d18;border:1px solid #f5c6cb}
  .cw-topbar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:16px}
  .cw-nav{display:flex;gap:8px;flex-wrap:wrap}
  .cw-nav a{padding:6px 12px;border-radius:6px;text-decoration:none;font-weight:700;font-size:11.5px;white-space:nowrap}
  .cw-stat-pill{margin-left:auto;padding:6px 14px;border-radius:999px;background:#eef6fb;color:#1ba0c8;font-weight:800;font-size:12.5px;white-space:nowrap}
  .cw-utility{background:#f7f7f7;border:1px solid #eee;border-radius:8px;padding:12px 14px;margin-bottom:14px;display:flex;flex-direction:column;gap:10px}
  .cw-utility .cw-bar{margin-bottom:0}
</style>

<div class="panel">
  <div class="section-header">
    <h2 class="section-title">Clients</h2>
    <p style="font-size:13px;color:#999;margin:0">Everyone who has booked or visited the studio</p>
  </div>

  <div class="panel-body">

    <div class="cw-topbar">
      <div class="cw-nav">
        <a href="<?= $BP ?>/studio/"                  style="background:#1ba0c8;color:#fff">Bookings</a>
        <a href="<?= $BP ?>/studio/stamp-cards.php"   style="background:#28a745;color:#fff">Stamp Cards</a>
        <a href="<?= $BP ?>/studio/stamp-monitor.php" style="background:#ffc107;color:#000">Monitor</a>
        <a href="<?= $BP ?>/studio/stamp-history.php" style="background:#dc3545;color:#fff">History</a>
      </div>
      <div class="cw-stat-pill"><?= count($rows) ?> <?= $q !== '' ? 'matching' : 'clients' ?></div>
    </div>

    <?php foreach ($notices as $n): ?><div class="cw-note cw-ok"><?= h($n) ?></div><?php endforeach; ?>
    <?php foreach ($errors  as $n): ?><div class="cw-note cw-err"><?= h($n) ?></div><?php endforeach; ?>

    <form class="cw-bar" method="get">
      <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search name, phone or email...">
      <button class="cw-btn" style="background:#1ba0c8;color:#fff">Search</button>
      <?php if ($q !== ''): ?><a class="cw-btn" style="background:#6c757d;color:#fff" href="?">Clear</a><?php endif; ?>
      <a class="cw-btn" style="background:#28a745;color:#fff" href="?export=1&amp;q=<?= urlencode($q) ?>">Export CSV</a>
    </form>

    <div class="cw-utility">
      <form class="cw-bar" method="post" enctype="multipart/form-data">
        <input type="hidden" name="act" value="import">
        <input type="hidden" name="q" value="<?= h($q) ?>">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="file" name="csv" accept=".csv" required style="font-size:13px">
        <button class="cw-btn" style="background:#6c757d;color:#fff">Import CSV (updates stamps)</button>
      </form>

      <form class="cw-bar" method="post">
        <input type="hidden" name="act" value="add">
        <input type="hidden" name="q" value="<?= h($q) ?>">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="text" name="name" placeholder="New client name *" required>
        <?php if ($PHONE): ?><input type="text" name="phone" placeholder="Phone"><?php endif; ?>
        <?php if ($EMAIL): ?><input type="text" name="email" placeholder="Email"><?php endif; ?>
        <button class="cw-btn" style="background:#28a745;color:#fff">+ Add Client</button>
      </form>
    </div>

    <?php if (!$rows): ?>
      <div style="padding:36px;text-align:center;color:#999">No clients to show.</div>
    <?php else: ?>
    <div style="overflow-x:auto">
      <table class="cw-tbl">
        <thead><tr>
          <th>Name</th><th>Contact</th><th style="text-align:center">Visits</th>
          <th style="text-align:center">Stamps</th><th style="text-align:center">Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><strong><?= h($r['nm']) ?></strong></td>
            <td style="font-size:12px;color:#666">
              <?php if ($r['ph']): ?><div><?= h($r['ph']) ?></div><?php endif; ?>
              <?php if ($r['em']): ?><div style="color:#999"><?= h($r['em']) ?></div><?php endif; ?>
              <?php if (!$r['ph'] && !$r['em']): ?>&mdash;<?php endif; ?>
            </td>
            <td style="text-align:center"><?= $BT ? (int)$r['visits'] : '&mdash;' ?></td>
            <td style="text-align:center">
              <div style="width:74px;height:16px;background:#e9e9e9;border:1px solid #ccc;border-radius:4px;overflow:hidden;margin:0 auto">
                <div style="width:<?= min(100, (int)$r['stamps'] * 10) ?>%;height:100%;background:#28a745"></div>
              </div>
              <div style="font-weight:700;font-size:11px;margin-top:3px"><?= (int)$r['stamps'] ?>/10</div>
            </td>
            <td style="text-align:center;white-space:nowrap">
              <form method="post" style="display:inline">
                <input type="hidden" name="act" value="stamp">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="q" value="<?= h($q) ?>">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <button class="cw-mini" style="background:#28a745;color:#fff" name="dir" value="add">+</button>
                <button class="cw-mini" style="background:#ffc107;color:#000" name="dir" value="sub">&minus;</button>
                <button class="cw-mini" style="background:#dc3545;color:#fff" name="dir" value="reset"
                        onclick="return confirm('Reset stamps to 0?')">Reset</button>
              </form>
              <form method="post" style="display:inline"
                    onsubmit="return confirm('Delete this client permanently?')">
                <input type="hidden" name="act" value="del">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="q" value="<?= h($q) ?>">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <button class="cw-mini" style="background:#5a3535;color:#fff">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>