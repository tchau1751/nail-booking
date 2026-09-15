<?php
$pageTitle = 'POS Settings';
$activeNav = 'settings';
$requireRole = 'manager';   // enforced by layout_start before any output
require_once __DIR__ . '/includes/layout_start.php';
require_once __DIR__ . '/includes/rewards.php';

$msg = ''; $err = '';
// Every row this page reads or saves is the signed-in salon's own.
$tid = tenantId();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['action'] ?? '') === 'settings') {
            query('UPDATE pos_settings SET currency_symbol=?, tax_rate=?, tax_label=?, tax_services=?,
                   receipt_header=?, receipt_footer=?, tip_presets=?, supply_fee_enabled=? WHERE tenant_id=?', [
                trim($_POST['currency_symbol']) ?: '$',
                max(0, (float)$_POST['tax_rate']),
                trim($_POST['tax_label']) ?: 'Sales Tax',
                isset($_POST['tax_services']) ? 1 : 0,
                trim($_POST['receipt_header']),
                trim($_POST['receipt_footer']),
                preg_replace('/[^0-9,\.]/', '', $_POST['tip_presets']) ?: '15,18,20,25',
                isset($_POST['supply_fee_enabled']) ? 1 : 0,
                $tid,
            ]);
            $msg = 'Settings saved.';
        } elseif (($_POST['action'] ?? '') === 'store') {
            query('UPDATE pos_settings SET owner_name=?, owner_email=?, owner_phone=?, license_no=?,
                   kiosk_welcome=?, url_yelp=?, url_google=?, url_facebook=?, url_instagram=?,
                   theme=? WHERE tenant_id=?', [
                trim($_POST['owner_name']), trim($_POST['owner_email']), trim($_POST['owner_phone']),
                trim($_POST['license_no']), trim($_POST['kiosk_welcome']),
                trim($_POST['url_yelp']), trim($_POST['url_google']),
                trim($_POST['url_facebook']), trim($_POST['url_instagram']),
                isset(posThemes()[$_POST['theme'] ?? '']) ? $_POST['theme'] : 'black-gold',
                $tid,
            ]);
            query('UPDATE business_settings SET business_name=?, business_phone=?, business_cell=?,
                   business_email=?, business_address=?, business_city=?, business_state=?,
                   business_zip=?, timezone=? WHERE tenant_id=?', [
                trim($_POST['business_name']), trim($_POST['business_phone']),
                trim($_POST['business_cell']), trim($_POST['business_email']),
                trim($_POST['business_address']), trim($_POST['business_city']),
                trim($_POST['business_state']), trim($_POST['business_zip']),
                in_array($_POST['timezone'] ?? '', timezone_identifiers_list(), true)
                    ? $_POST['timezone']
                    : (settings()['timezone'] ?? 'America/New_York'),
                $tid,
            ]);
            $msg = 'Store information saved.';
        } elseif (($_POST['action'] ?? '') === 'hours') {
            // A day that is closed keeps whatever times were last typed, so
            // ticking it back open does not mean retyping them.
            foreach ($_POST['open'] ?? [] as $weekday => $_) {
                $w = (int)$weekday;
                if ($w < 0 || $w > 6) continue;
                query('UPDATE business_hours SET is_open=?, start_time=?, end_time=? WHERE tenant_id=? AND weekday=?', [
                    empty($_POST['closed'][$w]) ? 1 : 0,
                    $_POST['open'][$w] ?: '09:00',
                    $_POST['close'][$w] ?: '19:00',
                    $tid, $w,
                ]);
            }
            $msg = 'Business hours saved.';
        } elseif (($_POST['action'] ?? '') === 'booking') {
            query('UPDATE business_settings SET slot_interval_minutes=?, booking_notice_hours=?,
                   reminder_hours_before=?, sms_sender=?, twilio_account_sid=?,
                   twilio_from_number=? WHERE tenant_id=?', [
                max(5, min(120, (int)$_POST['slot_interval_minutes'])),
                max(0, min(168, (int)$_POST['booking_notice_hours'])),
                max(0, min(168, (int)$_POST['reminder_hours_before'])),
                trim($_POST['sms_sender']),
                trim($_POST['twilio_account_sid']),
                trim($_POST['twilio_from_number']),
                $tid,
            ]);
            // The auth token is a secret: it is never rendered back into the
            // form, so an empty box means "leave it alone" rather than "clear
            // it". Clearing is its own explicit tick.
            if (isset($_POST['twilio_clear'])) {
                query("UPDATE business_settings SET twilio_auth_token='' WHERE tenant_id=?", [$tid]);
            } elseif (trim((string)($_POST['twilio_auth_token'] ?? '')) !== '') {
                query('UPDATE business_settings SET twilio_auth_token=? WHERE tenant_id=?',
                      [trim($_POST['twilio_auth_token']), $tid]);
            }
            $msg = 'Appointment and text settings saved.';
        } elseif (($_POST['action'] ?? '') === 'loyalty') {
            query('UPDATE pos_settings SET loyalty_enabled=?, points_per_dollar=?, point_value_cents=?,
                   points_min_redeem=?, feedback_enabled=? WHERE tenant_id=?', [
                isset($_POST['loyalty_enabled']) ? 1 : 0,
                max(0, (float)$_POST['points_per_dollar']),
                max(0.01, (float)$_POST['point_value_cents']),
                max(0, (int)$_POST['points_min_redeem']),
                isset($_POST['feedback_enabled']) ? 1 : 0,
                $tid,
            ]);
            $msg = 'Rewards settings saved.';
        } elseif (($_POST['action'] ?? '') === 'kiosk') {
            query('UPDATE pos_settings SET kiosk_welcome=?, kiosk_promo_title=?, kiosk_promo_text=?,
                   kiosk_promo_image=?, kiosk_show_wait=?, kiosk_menu_url=?, kiosk_terms=?,
                   birthday_sms_enabled=?, birthday_sms_text=? WHERE tenant_id=?', [
                trim($_POST['kiosk_welcome']), trim($_POST['kiosk_promo_title']),
                trim($_POST['kiosk_promo_text']), trim($_POST['kiosk_promo_image']),
                isset($_POST['kiosk_show_wait']) ? 1 : 0, trim($_POST['kiosk_menu_url']),
                trim($_POST['kiosk_terms']),
                isset($_POST['birthday_sms_enabled']) ? 1 : 0, trim($_POST['birthday_sms_text']),
                $tid,
            ]);
            query('UPDATE pos_settings SET public_show_prices=?, public_show_duration=? WHERE tenant_id=?', [
                isset($_POST['public_show_prices']) ? 1 : 0,
                isset($_POST['public_show_duration']) ? 1 : 0,
                $tid,
            ]);
            $msg = 'Kiosk and birthday settings saved.';
        } elseif (($_POST['action'] ?? '') === 'policy') {
            query('UPDATE pos_consent_templates SET title=?, body=? WHERE id=? AND tenant_id=?',
                  [trim($_POST['title']), trim($_POST['body']), (int)$_POST['id'], $tid]);
            $msg = 'Policy updated. Forms already signed keep their original wording.';
        } elseif (($_POST['action'] ?? '') === 'drawer') {
            $kind = in_array($_POST['kind'], ['open','pay_in','pay_out','close'], true) ? $_POST['kind'] : 'pay_in';
            query('INSERT INTO pos_cash_movements (tenant_id,kind,amount,reason,admin_id) VALUES (?,?,?,?,?)',
                  [$tid, $kind, abs((float)$_POST['amount']), trim($_POST['reason']), $admin['id'] ?? null]);
            $msg = 'Cash movement recorded.';
        }
    } catch (Throwable $e) { $err = $e->getMessage(); }
}

$s = fetchOne('SELECT * FROM pos_settings WHERE tenant_id=? ORDER BY id LIMIT 1', [$tid]);
$biz = settings();
$moves = fetchAll('SELECT m.*, u.name AS who FROM pos_cash_movements m
                   LEFT JOIN admin_users u ON u.id=m.admin_id
                   WHERE m.tenant_id=?
                   ORDER BY m.id DESC LIMIT 20', [$tid]);
$policies = fetchAll('SELECT * FROM pos_consent_templates WHERE tenant_id=? ORDER BY id', [$tid]);
$hours = fetchAll('SELECT * FROM business_hours WHERE tenant_id=? ORDER BY weekday', [$tid]);
?>
<?php if ($msg): ?><div class="alert alert-ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>

<div class="card">
  <h2>🏪 Store &amp; owner</h2>
  <p class="sub">Shown on receipts, the kiosk and every policy or consent form. The review links are
     what the thank-you text sends guests to.</p>
  <form method="post">
    <input type="hidden" name="action" value="store">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px">
      <label class="field"><span>Salon name</span><input type="text" name="business_name" value="<?= e($biz['business_name'] ?? '') ?>"></label>
      <label class="field"><span>Owner name</span><input type="text" name="owner_name" value="<?= e($s['owner_name']) ?>"></label>
      <label class="field"><span>Owner email</span><input type="text" name="owner_email" value="<?= e($s['owner_email']) ?>"></label>
      <label class="field"><span>Owner phone</span><input type="text" name="owner_phone" value="<?= e($s['owner_phone']) ?>"></label>
      <label class="field"><span>Shop phone</span><input type="text" name="business_phone" value="<?= e($biz['business_phone'] ?? '') ?>"></label>
      <label class="field"><span>Cell phone</span><input type="text" name="business_cell" value="<?= e($biz['business_cell'] ?? '') ?>" placeholder="After hours"></label>
      <label class="field"><span>Salon email</span><input type="text" name="business_email" value="<?= e($biz['business_email'] ?? '') ?>"></label>
      <label class="field"><span>Licence / certification no.</span><input type="text" name="license_no" value="<?= e($s['license_no']) ?>" placeholder="State licence number"></label>
    </div>

    <label class="field"><span>Street address</span>
      <input type="text" name="business_address" value="<?= e($biz['business_address'] ?? '') ?>" placeholder="6830 Stockton Blvd. #200"></label>
    <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:14px">
      <label class="field"><span>City</span><input type="text" name="business_city" value="<?= e($biz['business_city'] ?? '') ?>"></label>
      <label class="field"><span>State</span><input type="text" name="business_state" value="<?= e($biz['business_state'] ?? '') ?>" maxlength="40" placeholder="CA"></label>
      <label class="field"><span>ZIP</span><input type="text" name="business_zip" value="<?= e($biz['business_zip'] ?? '') ?>" maxlength="20"></label>
    </div>

    <h3 style="font-size:15px;margin:14px 0 8px">Where guests leave reviews</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px">
      <label class="field"><span>Yelp</span><input type="url" name="url_yelp" value="<?= e($s['url_yelp'] ?? '') ?>" placeholder="https://www.yelp.com/biz/…"></label>
      <label class="field"><span>Google review</span><input type="url" name="url_google" value="<?= e($s['url_google'] ?? '') ?>" placeholder="https://g.page/…/review"></label>
      <label class="field"><span>Facebook</span><input type="url" name="url_facebook" value="<?= e($s['url_facebook'] ?? '') ?>" placeholder="https://www.facebook.com/…"></label>
      <label class="field"><span>Instagram</span><input type="url" name="url_instagram" value="<?= e($s['url_instagram'] ?? '') ?>" placeholder="https://www.instagram.com/…"></label>
    </div>

    <h3 style="font-size:15px;margin:18px 0 8px">Timezone</h3>
    <p class="sub" style="margin:0 0 10px">Every closing time, appointment and report is read in this
       zone. Change it and today's figures shift with it.</p>
    <label class="field" style="max-width:360px">
      <select name="timezone">
        <?php
        $zoneNow = $biz['timezone'] ?? 'America/New_York';
        $zones = ['America/New_York' => 'Eastern', 'America/Chicago' => 'Central',
                  'America/Denver' => 'Mountain', 'America/Phoenix' => 'Arizona',
                  'America/Los_Angeles' => 'Pacific', 'America/Anchorage' => 'Alaska',
                  'Pacific/Honolulu' => 'Hawaii'];
        if (!isset($zones[$zoneNow])) $zones[$zoneNow] = $zoneNow;
        foreach ($zones as $zid => $label): ?>
          <option value="<?= e($zid) ?>" <?= $zoneNow === $zid ? 'selected' : '' ?>>
            <?= e($label) ?> — <?= e($zid) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <h3 style="font-size:15px;margin:18px 0 8px">Colour theme</h3>
    <p class="sub" style="margin:0 0 10px">Changes the till, not the receipt. Red still means a void
       and green still means paid in every scheme.</p>
    <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px">
      <?php $themeNow = $s['theme'] ?? 'black-gold';
      foreach (posThemes() as $key => $t): ?>
        <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:pointer;
                      border:2px solid <?= $themeNow === $key ? 'var(--ink)' : 'var(--line)' ?>;
                      border-radius:var(--radius);background:var(--card)">
          <input type="radio" name="theme" value="<?= e($key) ?>" <?= $themeNow === $key ? 'checked' : '' ?>>
          <span style="font-weight:700"><?= e($t[0]) ?></span>
          <span style="display:flex;border-radius:6px;overflow:hidden;border:1px solid var(--line)">
            <?php foreach ($t[1] as $swatch): ?>
              <span style="width:22px;height:22px;background:<?= e($swatch) ?>"></span>
            <?php endforeach; ?>
          </span>
        </label>
      <?php endforeach; ?>
    </div>

    <button class="btn btn-green" type="submit">Save store information</button>
  </form>
</div>

<div class="card">
  <h2>🕒 Business hours</h2>
  <p class="sub">The booking site offers appointments inside these hours, and the kiosk uses them to
     tell a walk-in whether the shop is still open. Untick a day to close it — the times stay put so
     ticking it back open does not mean retyping them.</p>
  <form method="post">
    <input type="hidden" name="action" value="hours">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Day</th><th style="width:110px">Open?</th><th>From</th><th>To</th></tr></thead>
        <tbody>
        <?php
        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        foreach ($hours as $h):
          $w = (int)$h['weekday']; ?>
          <tr>
            <td><strong><?= e($dayNames[$w] ?? $w) ?></strong></td>
            <td><label style="font-weight:700">
              <input type="checkbox" name="closed[<?= $w ?>]" <?= $h['is_open'] ? '' : 'checked' ?>>
              Closed</label></td>
            <td><input type="time" name="open[<?= $w ?>]" value="<?= e(substr((string)$h['start_time'], 0, 5)) ?>"></td>
            <td><input type="time" name="close[<?= $w ?>]" value="<?= e(substr((string)$h['end_time'], 0, 5)) ?>"></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <button class="btn btn-green" type="submit" style="margin-top:14px">Save business hours</button>
  </form>
</div>

<div class="card">
  <h2>📅 Appointments &amp; text messages</h2>
  <p class="sub">How the booking site offers times, and how reminders go out. These were only
     reachable from the old booking admin — they belong with the rest of the salon's settings.</p>
  <form method="post">
    <input type="hidden" name="action" value="booking">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px">
      <label class="field"><span>Slot interval (minutes)</span>
        <input type="number" name="slot_interval_minutes" min="5" max="120" step="5"
               value="<?= (int)($biz['slot_interval_minutes'] ?? 30) ?>"></label>
      <label class="field"><span>Earliest booking (hours ahead)</span>
        <input type="number" name="booking_notice_hours" min="0" max="168"
               value="<?= (int)($biz['booking_notice_hours'] ?? 2) ?>"></label>
      <label class="field"><span>Reminder text (hours before)</span>
        <input type="number" name="reminder_hours_before" min="0" max="168"
               value="<?= (int)($biz['reminder_hours_before'] ?? 24) ?>"></label>
      <label class="field"><span>Text sender name</span>
        <input type="text" name="sms_sender" maxlength="30"
               value="<?= e($biz['sms_sender'] ?? '') ?>"></label>
    </div>

    <h3 style="font-size:15px;margin:18px 0 6px">Twilio</h3>
    <p class="sub" style="margin:0 0 10px">What the confirmations and reminders are sent through.
       Filled in here, these override whatever is in <code>config/config.php</code> — which is the
       right place for them, because that file is in version control and these are not meant to be.</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px">
      <label class="field"><span>Account SID</span>
        <input type="text" name="twilio_account_sid" autocomplete="off"
               value="<?= e($biz['twilio_account_sid'] ?? '') ?>" placeholder="AC…"></label>
      <label class="field"><span>From number</span>
        <input type="text" name="twilio_from_number" autocomplete="off"
               value="<?= e($biz['twilio_from_number'] ?? '') ?>" placeholder="+12025551234"></label>
      <label class="field"><span>Auth token</span>
        <input type="password" name="twilio_auth_token" autocomplete="new-password"
               placeholder="<?= !empty($biz['twilio_auth_token']) ? '•••••••• stored — leave blank to keep' : 'not set' ?>"></label>
    </div>
    <?php if (!empty($biz['twilio_auth_token'])): ?>
      <label style="display:block;font-weight:700;margin:6px 0 14px">
        <input type="checkbox" name="twilio_clear"> Clear the stored auth token
      </label>
    <?php endif; ?>
    <p class="sub" style="margin:6px 0 14px">The token is never printed back into this page. An empty
       box leaves it as it is; clearing it is the tick above.</p>

    <button class="btn btn-green" type="submit">Save appointment settings</button>
  </form>
</div>

<div class="card">
  <h2 id="rewards">⭐ Rewards &amp; feedback</h2>
  <p class="sub">Points are earned on services and retail, never on tax, tips or gift card purchases.</p>
  <form method="post">
    <input type="hidden" name="action" value="loyalty">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px">
      <label class="field"><span>Points per <?= e($s['currency_symbol']) ?>1 spent</span>
        <input type="number" step="0.1" name="points_per_dollar" value="<?= (float)$s['points_per_dollar'] ?>"></label>
      <label class="field"><span>Each point is worth (cents)</span>
        <input type="number" step="0.01" name="point_value_cents" value="<?= (float)$s['point_value_cents'] ?>"></label>
      <label class="field"><span>Minimum points to redeem</span>
        <input type="number" name="points_min_redeem" value="<?= (int)$s['points_min_redeem'] ?>"></label>
    </div>
    <p class="sub">
      At these settings, <?= (int)$s['points_min_redeem'] ?> points is worth
      <?= money(pointsToMoney((int)$s['points_min_redeem'])) ?>, earned on about
      <?= money((float)$s['points_per_dollar'] > 0 ? (int)$s['points_min_redeem'] / (float)$s['points_per_dollar'] : 0) ?> of spend.
    </p>
    <div style="display:flex;gap:22px;flex-wrap:wrap;font-weight:700;margin-bottom:16px">
      <label><input type="checkbox" name="loyalty_enabled" <?= $s['loyalty_enabled'] ? 'checked' : '' ?>> Award points</label>
      <label><input type="checkbox" name="feedback_enabled" <?= $s['feedback_enabled'] ? 'checked' : '' ?>> Create a feedback request at checkout</label>
    </div>
    <button class="btn btn-green" type="submit">Save rewards settings</button>
  </form>
</div>

<div class="card">
  <h2>🪟 Kiosk display &amp; birthday texts</h2>
  <p class="sub">The screen guests see at the door, and the automatic birthday message.</p>
  <form method="post">
    <input type="hidden" name="action" value="kiosk">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px">
      <label class="field"><span>Welcome line</span><input type="text" name="kiosk_welcome" value="<?= e($s['kiosk_welcome']) ?>"></label>
      <label class="field"><span>Promo headline</span><input type="text" name="kiosk_promo_title" value="<?= e($s['kiosk_promo_title']) ?>"></label>
      <label class="field"><span>Promo text</span><input type="text" name="kiosk_promo_text" value="<?= e($s['kiosk_promo_text']) ?>"></label>
      <label class="field"><span>Promo image URL</span><input type="text" name="kiosk_promo_image" value="<?= e($s['kiosk_promo_image']) ?>" placeholder="Optional background photo"></label>
      <label class="field"><span>Menu / booking link for the QR</span><input type="text" name="kiosk_menu_url" value="<?= e($s['kiosk_menu_url']) ?>" placeholder="<?= e(APP_URL) ?>"></label>
    </div>

    <hr style="border:none;border-top:1px solid var(--line);margin:8px 0 18px">
    <h3 style="font-size:16px;margin-bottom:8px">🌐 Online booking page</h3>
    <p class="sub">Most salons quote in person, because the real price depends on length,
       art and repairs. Both are hidden from the public booking page by default.</p>
    <div style="display:flex;gap:22px;flex-wrap:wrap;font-weight:700;margin-bottom:16px">
      <label><input type="checkbox" name="public_show_prices" <?= !empty($s['public_show_prices']) ? 'checked' : '' ?>> Show prices online</label>
      <label><input type="checkbox" name="public_show_duration" <?= !empty($s['public_show_duration']) ? 'checked' : '' ?>> Show how long each service takes</label>
    </div>
    <label class="field"><span>Small print shown on the kiosk</span><textarea name="kiosk_terms" rows="3"><?= e($s['kiosk_terms']) ?></textarea></label>
    <label style="display:block;font-weight:700;margin-bottom:16px">
      <input type="checkbox" name="kiosk_show_wait" <?= $s['kiosk_show_wait'] ? 'checked' : '' ?>>
      Show how many guests are waiting
    </label>

    <hr style="border:none;border-top:1px solid var(--line);margin:8px 0 18px">
    <h3 id="birthdays" style="font-size:16px;margin-bottom:8px">🎂 Automatic birthday texts</h3>
    <p class="sub">Guests can add their birthday at the kiosk — day and month only, never the year.
       One text per guest per year, and only to those who opted in.</p>
    <label class="field"><span>Message</span>
      <textarea name="birthday_sms_text" rows="3"><?= e($s['birthday_sms_text']) ?></textarea></label>
    <p class="sub" style="margin-top:-6px"><code>{name}</code>, <code>{salon}</code> and <code>{points}</code> are filled in per guest. Keep the STOP line.</p>
    <label style="display:block;font-weight:700;margin-bottom:16px">
      <input type="checkbox" name="birthday_sms_enabled" <?= $s['birthday_sms_enabled'] ? 'checked' : '' ?>>
      Send birthday texts automatically
    </label>
    <p class="sub">
      This needs a daily scheduled task on the server — one task covers every salon on it:<br>
      <code>D:\xampp\php\php.exe <?= e(str_replace('/', '\\', dirname(__DIR__))) ?>\cron\send_birthday_sms.php</code><br>
      Test it safely first — <a href="?birthdays=today#birthdays">see who would get a text today (sends nothing)</a>.
    </p>
    <?php if (isset($_GET['birthdays'])): $birthdaysDue = birthdayTextsDue(); ?>
      <div class="alert alert-ok" style="margin-bottom:16px">
        <?php if (!$birthdaysDue): ?>
          No guest is due a birthday text today.
        <?php else: ?>
          <strong><?= count($birthdaysDue) ?> guest<?= count($birthdaysDue) === 1 ? '' : 's' ?> due a text today<?= birthdayTextsOn() ? '' : ', once birthday texts are switched on' ?>:</strong>
          <ul style="margin:6px 0 0 18px">
            <?php foreach ($birthdaysDue as $guest): ?>
              <li><?= e($guest['full_name']) ?> · <?= e($guest['phone']) ?> — “<?= e($guest['body']) ?>”</li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <button class="btn btn-green" type="submit">Save kiosk &amp; birthday settings</button>
  </form>
</div>

<div class="card">
  <h2>📜 Policies &amp; consent forms</h2>
  <p class="sub">The wording guests read and sign on the tablet. Editing a policy never changes a form
     that has already been signed — those keep the text as it stood that day.</p>
  <?php foreach ($policies as $p): ?>
    <details style="border:1px solid var(--line);border-radius:12px;padding:12px 14px;margin-bottom:10px">
      <summary style="cursor:pointer;font-weight:700"><?= e($p['title']) ?></summary>
      <form method="post" style="margin-top:14px">
        <input type="hidden" name="action" value="policy">
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <label class="field"><span>Title</span><input type="text" name="title" value="<?= e($p['title']) ?>"></label>
        <label class="field"><span>Body</span><textarea name="body" rows="10"><?= e($p['body']) ?></textarea></label>
        <button class="btn btn-green btn-sm" type="submit">Save</button>
        <a class="btn btn-light btn-sm" href="<?= BASE_PATH ?>/pos/consent.php?form=<?= e($p['form_key']) ?>">Have someone sign it</a>
      </form>
    </details>
  <?php endforeach; ?>
</div>

<div class="card">
  <h2>Register settings</h2>
  <p class="sub">Tax, currency and receipt wording. Nail services are untaxed in most states — leave the service switch off unless yours taxes them.</p>
  <form method="post">
    <input type="hidden" name="action" value="settings">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px">
      <label class="field"><span>Currency symbol</span><input type="text" name="currency_symbol" value="<?= e($s['currency_symbol']) ?>"></label>
      <label class="field"><span>Tax rate (%)</span><input type="number" step="0.001" name="tax_rate" value="<?= e($s['tax_rate']) ?>"></label>
      <label class="field"><span>Tax label</span><input type="text" name="tax_label" value="<?= e($s['tax_label']) ?>"></label>
      <label class="field"><span>Tip presets (%)</span><input type="text" name="tip_presets" value="<?= e($s['tip_presets']) ?>"></label>
      <label class="field"><span>Receipt header</span><input type="text" name="receipt_header" value="<?= e($s['receipt_header']) ?>"></label>
      <label class="field"><span>Receipt footer</span><input type="text" name="receipt_footer" value="<?= e($s['receipt_footer']) ?>"></label>
    </div>
    <label style="display:block;font-weight:700;margin:6px 0 10px">
      <input type="checkbox" name="tax_services" <?= $s['tax_services'] ? 'checked' : '' ?>> Charge tax on services too
    </label>
    <label style="display:block;font-weight:700;margin:0 0 6px">
      <input type="checkbox" name="supply_fee_enabled" <?= !empty($s['supply_fee_enabled']) ? 'checked' : '' ?>>
      Deduct a supply fee from technician payouts
    </label>
    <p class="sub" style="margin:0 0 16px">
      Off, and payroll ignores it entirely. On, and each technician's own rate — set on the
      <a href="<?= BASE_PATH ?>/pos/payroll.php">Payroll</a> page — is charged on the service revenue
      they took in and withheld from what they are paid. It never changes what the guest is charged.
    </p>
    <button class="btn btn-green" type="submit">Save settings</button>
  </form>
</div>

<?php if (hasRole('owner')): ?>
<div class="card">
  <h2>Clearing test data</h2>
  <p class="sub">Practice tickets from before opening day, or a full reset back to a clean set of
     books. Owner only, and it asks you to type the words before it does anything.</p>
  <a class="btn btn-red" href="<?= BASE_PATH ?>/pos/reset.php">Open clear test data →</a>
</div>
<?php endif; ?>

<div class="card">
  <h2>Cash drawer</h2>
  <p class="sub">Record the opening float and any money in or out, so the Reports page can tell you what should be in the till.</p>
  <form method="post" class="toolbar" style="margin:0">
    <input type="hidden" name="action" value="drawer">
    <label class="field"><span>Type</span>
      <select name="kind">
        <option value="open">Opening float</option>
        <option value="pay_in">Pay in</option>
        <option value="pay_out">Pay out</option>
        <option value="close">Cash removed / closed out</option>
      </select>
    </label>
    <label class="field"><span>Amount</span><input type="number" step="0.01" name="amount" required></label>
    <label class="field" style="flex:1;min-width:200px"><span>Reason</span><input type="text" name="reason" placeholder="e.g. supply run"></label>
    <button class="btn" type="submit">Record</button>
  </form>

  <div class="table-wrap" style="margin-top:16px">
    <table>
      <thead><tr><th>When</th><th>Type</th><th class="num">Amount</th><th>Reason</th><th>By</th></tr></thead>
      <tbody>
      <?php foreach ($moves as $m): ?>
        <tr>
          <td><?= date('m/d g:i A', strtotime($m['created_at'])) ?></td>
          <td><?= e(str_replace('_', ' ', $m['kind'])) ?></td>
          <td class="num"><?= in_array($m['kind'], ['open','pay_in'], true) ? '' : '−' ?><?= money($m['amount']) ?></td>
          <td><?= e($m['reason']) ?></td>
          <td><?= e($m['who'] ?: '—') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$moves): ?><tr><td colspan="5" style="color:var(--ink-soft)">Nothing recorded yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <h2>Tablet tips</h2>
  <ul style="margin-left:20px;line-height:1.9">
    <li>Open <code><?= e(APP_URL) ?>/pos/</code> in Chrome or Safari on the tablet, then <strong>Add to Home Screen</strong> — it runs full-screen with no address bar.</li>
    <li>The tablet must be on the same Wi-Fi as this PC. Use the PC's LAN address, e.g. <code>http://192.168.1.20/<?= e(SUBFOLDER) ?>/pos/</code>.</li>
    <li>A USB or Bluetooth barcode scanner works with no setup: tap the search box and scan — it types the code and presses Enter.</li>
    <li>Receipts print through the browser, so any printer Windows can see will work, including 80mm thermal.</li>
  </ul>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
