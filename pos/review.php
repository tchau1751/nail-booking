<?php
// ============================================================
//  Public feedback page. The guest opens this from a text on
//  their own phone, so there is no login — the random token is
//  the only key, and it reveals nothing but a first name.
// ============================================================
require_once __DIR__ . '/includes/pos.php';

$token = preg_replace('/[^a-f0-9]/', '', $_GET['t'] ?? '');
$row   = strlen($token) === 32
    ? fetchOne('SELECT f.*, c.full_name, t.name AS tech_name
                FROM pos_feedback f
                LEFT JOIN pos_clients c ON c.id=f.client_id
                LEFT JOIN technicians t ON t.id=f.technician_id
                WHERE f.token=?', [$token])
    : null;

$saved = false; $err = '';
if ($row && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int)($_POST['rating'] ?? 0);
    if ($rating < 1 || $rating > 5) {
        $err = 'Please choose a rating.';
    } elseif ($row['responded_at']) {
        $err = 'This form has already been answered — thank you!';
    } else {
        query('UPDATE pos_feedback SET rating=?, comment=?, responded_at=NOW() WHERE id=?',
              [$rating, mb_substr(trim($_POST['comment'] ?? ''), 0, 2000), $row['id']]);
        $saved = true;
    }
}
$biz = settings();
$set = posSettings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>How did we do? — <?= e($biz['business_name'] ?? 'Nail Salon') ?></title>
<link rel="stylesheet" href="<?= BASE_PATH ?>/pos/assets/pos.css">
<style>
  body{background:linear-gradient(160deg,#3a2a24,#5c4237);min-height:100vh;display:flex;
       align-items:center;justify-content:center;padding:20px;}
  .box{background:#fff;border-radius:22px;max-width:480px;width:100%;padding:30px 26px;
       box-shadow:0 18px 50px rgba(0,0,0,.3);}
  h1{font-family:Georgia,serif;font-size:26px;margin-bottom:6px;}
  .stars{display:flex;gap:8px;justify-content:center;margin:22px 0;}
  .stars input{display:none;}
  .stars label{font-size:44px;cursor:pointer;filter:grayscale(1);opacity:.35;transition:.15s;}
  .stars label:hover,.stars label.on{filter:none;opacity:1;transform:scale(1.08);}
</style>
</head>
<body>
<div class="box">
<?php if (!$row): ?>
  <h1>Link not found</h1>
  <p style="color:var(--ink-soft)">That feedback link isn't valid. If you'd still like to tell us how we did,
     please call us<?= !empty($biz['business_phone']) ? ' on ' . e($biz['business_phone']) : '' ?>.</p>
<?php elseif ($saved || $row['responded_at']): ?>
  <div style="text-align:center">
    <div style="font-size:56px">💖</div>
    <h1>Thank you!</h1>
    <p style="color:var(--ink-soft)">Your feedback goes straight to <?= e($set['owner_name'] ?: 'the owner') ?>.
       We really appreciate you taking the time.</p>
  </div>
<?php else: ?>
  <h1>How did we do<?= $row['full_name'] ? ', ' . e(explode(' ', $row['full_name'])[0]) : '' ?>?</h1>
  <p style="color:var(--ink-soft)">
    Thank you for visiting <?= e($biz['business_name'] ?? 'us') ?><?= $row['tech_name'] ? ' and seeing ' . e($row['tech_name']) : '' ?>.
    It takes ten seconds.
  </p>
  <?php if ($err): ?><div class="alert alert-err" style="margin-top:14px"><?= e($err) ?></div><?php endif; ?>
  <form method="post">
    <div class="stars" id="stars">
      <?php for ($i = 5; $i >= 1; $i--): ?>
        <input type="radio" name="rating" id="s<?= $i ?>" value="<?= $i ?>">
        <label for="s<?= $i ?>" data-v="<?= $i ?>">⭐</label>
      <?php endfor; ?>
    </div>
    <label class="field"><span>Anything you'd like to tell us?</span>
      <textarea name="comment" rows="4" placeholder="Optional"></textarea></label>
    <button class="btn btn-green btn-lg" type="submit" style="width:100%">Send feedback</button>
  </form>
  <script>
    // Stars render right-to-left in the DOM; light up everything up to the pick.
    var labels = [].slice.call(document.querySelectorAll('#stars label'));
    labels.forEach(function (l) {
      l.addEventListener('click', function () {
        var v = +l.getAttribute('data-v');
        labels.forEach(function (x) { x.classList.toggle('on', +x.getAttribute('data-v') <= v); });
      });
    });
  </script>
<?php endif; ?>
</div>
</body>
</html>
