<?php
// ============================================================
//  Guest-facing check-in display for the tablet by the door.
//
//  Phone number first: a returning guest taps 10 digits and is
//  greeted by name, because the number is the one thing they
//  always know and can enter without a keyboard.
// ============================================================
require_once __DIR__ . '/includes/salon.php';
require_once __DIR__ . '/includes/rewards.php';
requireTillLogin();

$set      = posSettings();
$biz      = settings();
$services = fetchAll('SELECT id,name,duration_minutes,image_url FROM services WHERE tenant_id=? AND is_active=1 ORDER BY display_order, name', [tenantId()]);
$techs    = fetchAll('SELECT id,name FROM technicians WHERE tenant_id=? AND is_active=1 ORDER BY display_order, name', [tenantId()]);
$menuUrl  = $set['kiosk_menu_url'] ?: APP_URL;
$qrFile   = __DIR__ . '/assets/menu-qr.png';
$hasQr    = is_file($qrFile);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no,viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#083c42">
<title>Welcome — <?= e($biz['business_name'] ?? 'Nail Salon') ?></title>
<link rel="manifest" href="<?= BASE_PATH ?>/pos/manifest.php">
<link rel="apple-touch-icon" href="<?= BASE_PATH ?>/pos/assets/icons/icon-180.png">
<style>
:root{
  --teal-1:#083c42; --teal-2:#0d6b71; --teal-3:#14a3a8; --teal-4:#5fdde2;
  --ink:#0a3236; --ink2:#3f6a6e; --gold:#f2b705; --gold-soft:#ffe08a;
  --pink:#ff5d8f; --cream:#ffffff; --pale:#eafcfc; --green:#1fb87a; --line:rgba(255,255,255,.35);
}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
html,body{height:100%;overflow:hidden;}
body{
  font:500 16px/1.45 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
  background:linear-gradient(155deg, var(--teal-1) 0%, var(--teal-2) 45%, var(--teal-3) 100%);
  color:#fff; user-select:none;
}
/* 100dvh so iPad Safari's collapsing toolbars can't clip the keypad;
   the vh line is the fallback for anything older. */
.wrap{
  display:flex; gap:22px;
  height:100vh; height:100dvh;
  padding:calc(22px + env(safe-area-inset-top)) calc(22px + env(safe-area-inset-right))
          calc(22px + env(safe-area-inset-bottom)) calc(22px + env(safe-area-inset-left));
}
.panel{background:rgba(7, 145, 236, 0.97);border:1px solid rgba(235, 226, 234, 0.6);border-radius:28px;
       backdrop-filter:blur(6px);color:var(--ink);box-shadow:0 14px 34px rgba(4,40,44,.28);}

/* ── Left: promo, queue, menu ─────────────────────────────── */
.left{flex:1 1 46%;display:flex;flex-direction:column;gap:18px;min-width:0;}
.promo{flex:1 1 auto;padding:0;overflow:hidden;position:relative;display:flex;
       flex-direction:column;justify-content:center;align-items:center;min-height:0;background:var(--teal-1);}
.promo-img{position:absolute;inset:0;background-size:cover;background-position:center;opacity:.6;}
.promo-fade{position:absolute;inset:0;background:radial-gradient(120% 90% at 50% 50%,rgba(213, 229, 231, 0.32) 0%,rgba(8,60,66,.88) 100%);}
.promo-body{position:relative;padding:30px 32px;text-align:center;display:flex;flex-direction:column;align-items:center;}
.promo-kicker{font-size:13px;letter-spacing:.22em;text-transform:uppercase;color:var(--gold);font-weight:800;}
.promo-title{font-family:Georgia,"Times New Roman",serif;font-size:clamp(30px,4.2vw,52px);
             line-height:1.05;margin:8px 0 10px;font-style:italic;color:#fff;}
.promo-text{font-size:clamp(15px,1.6vw,20px);color:rgba(139, 111, 18, 0.88);max-width:36ch;}
.promo-empty{position:relative;padding:34px;text-align:center;color:rgba(20, 123, 219, 0.55);}

.queue{padding:20px 24px;display:flex;gap:16px;}
.qbox{flex:1;background:var(--pale);border-radius:18px;padding:16px 18px;text-align:center;}
.qbox .n{font-size:clamp(30px,4vw,46px);font-weight:800;color:var(--teal-2);line-height:1;}
.qbox .l{font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:var(--ink2);
         margin-top:8px;font-weight:700;}

.menu{padding:18px 24px;display:flex;align-items:center;gap:18px;}
.menu img{width:96px;height:96px;border-radius:14px;background:#fff;padding:7px;flex:none;
         border:1px solid var(--pale);}
.menu .m-t{font-weight:800;font-size:16px;color:var(--ink);}
.menu .m-s{font-size:13px;color:var(--ink2);margin-top:4px;word-break:break-all;}
.terms{padding:0 26px 4px;font-size:11px;line-height:1.5;color:rgba(226, 17, 17, 0.55);}

/* ── Right: greeting and keypad ───────────────────────────── */
.right{flex:1 1 54%;display:flex;flex-direction:column;padding:26px 30px;min-width:0;}
.hello{font-size:13px;letter-spacing:.2em;text-transform:uppercase;color:var(--teal-2);font-weight:800;}
.salon{font-family:Georgia,serif;font-size:clamp(24px,3.2vw,38px);margin:6px 0 4px;color:var(--ink);}
.ask{color:var(--ink2);font-size:clamp(14px,1.6vw,18px);margin-bottom:14px;}

.display{background:var(--pale);border:2px solid var(--teal-4);border-radius:999px;
         padding:10px 22px;text-align:center;font-size:clamp(20px,3.2vw,30px);font-weight:800;
         letter-spacing:.05em;min-height:56px;width:fit-content;min-width:230px;max-width:80%;
         margin:0 auto;display:flex;align-items:center;justify-content:center;
         color:var(--ink);}
.display.placeholder{color:rgba(10,50,54,.32);}

.pad{flex:1;display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:16px 0;min-height:0;
     align-items:center;justify-items:center;}
.pad button{
  width:clamp(52px,9vw,92px);height:clamp(52px,9vw,92px);
  border:none;background:var(--pale);color:var(--ink);
  border-radius:50%;font-size:clamp(20px,3vw,30px);font-weight:800;cursor:pointer;
  display:flex;align-items:center;justify-content:center;transition:transform .06s, background .12s;
  box-shadow:0 4px 10px rgba(10,50,54,.14);
}
.pad button:active{transform:scale(.94);background:var(--teal-2);color:#fff;}
.pad .fn{font-size:clamp(17px,2.4vw,24px);color:var(--pink);background:#fff;}

.next{min-height:58px;border:none;border-radius:999px;background:var(--green);color:#fff;
      font-size:clamp(16px,2vw,20px);font-weight:800;cursor:pointer;
      display:flex;align-items:center;justify-content:center;gap:10px;
      box-shadow:0 6px 14px rgba(9, 124, 139, 0.35);
      transition:transform .08s;}
.next:active{transform:scale(.97);}
.next[disabled]{opacity:.32;pointer-events:none;box-shadow:none;}
/* The lone "Next" under the phone keypad sits alone with nothing to
   share a row with, so give it a smaller, centered pill instead of
   stretching edge-to-edge. */
#phoneNext{max-width:280px;width:100%;margin:4px auto 0;}

/* ── Steps after the number ───────────────────────────────── */
.step{display:none;flex-direction:column;height:100%;}
.step.on{display:flex;}
.greet{font-family:Georgia,serif;font-size:clamp(26px,3.6vw,42px);margin-bottom:6px;color:var(--ink);}
.sub{color:var(--ink2);margin-bottom:16px;font-size:clamp(14px,1.6vw,18px);}
.choices{flex:1;overflow-y:auto;-webkit-overflow-scrolling:touch;display:grid;
         grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;align-content:start;padding-right:4px;}
.choice{border:2px solid var(--pale);background:var(--pale);color:var(--ink);border-radius:18px;
        padding:16px;font-size:16px;font-weight:700;cursor:pointer;text-align:left;min-height:64px;}
.choice.sel{background:var(--teal-2);color:#fff;border-color:var(--teal-2);}
.choice small{display:block;font-weight:500;opacity:.7;margin-top:3px;}
/* Services with a photo: picture behind, name on a scrim so it stays legible. */
.choice.photo{background-size:cover;background-position:center;position:relative;
              min-height:110px;display:flex;align-items:flex-end;padding:0;overflow:hidden;}
.choice.photo::before{content:'';position:absolute;inset:0;
              background:linear-gradient(180deg,rgba(0,0,0,.1) 35%,rgba(0,0,0,.8) 100%);}
.choice.photo > span{position:relative;padding:14px 16px;width:100%;text-shadow:0 1px 4px rgba(0,0,0,.7);color:#fff;}
.choice.photo.sel{border-color:var(--teal-4);box-shadow:inset 0 0 0 4px var(--teal-4);}
.choice.photo.sel > span{color:#fff;}
.row{display:flex;gap:12px;margin-top:14px;}
.row .next{flex:1;}
.back{min-height:74px;padding:0 26px;border:2px solid var(--pale);background:#fff;color:var(--ink);
      border-radius:999px;font-size:17px;font-weight:700;cursor:pointer;}
.name-input{width:100%;min-height:74px;font-size:24px;padding:0 20px;border-radius:40px;
            border:2px solid var(--teal-4);background:var(--pale);color:var(--ink);text-align:center;}
.name-input::placeholder{color:rgba(10,50,54,.32);}
.dob{margin-top:18px;}
.dob-label{font-size:15px;font-weight:700;margin-bottom:10px;color:var(--ink);}
.dob-label span{font-weight:500;color:var(--ink2);font-size:13px;}
.dob-row{display:flex;gap:12px;}
.dob-sel{flex:1;min-height:64px;font-size:19px;padding:0 16px;border-radius:16px;
         border:2px solid var(--teal-4);background:var(--pale);color:var(--ink);appearance:none;
         background-image:linear-gradient(45deg,transparent 50%,var(--teal-2) 50%),
                          linear-gradient(135deg,var(--teal-2) 50%,transparent 50%);
         background-position:calc(100% - 22px) 50%, calc(100% - 15px) 50%;
         background-size:7px 7px, 7px 7px;background-repeat:no-repeat;}
.dob-sel option{background:#fff;color:var(--ink);}

.reward{background:rgba(242,183,5,.16);border:1px solid var(--gold);color:var(--ink);border-radius:16px;
        padding:14px 18px;margin-bottom:14px;font-weight:700;}
.stampdots{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px;}
.dot{width:22px;height:22px;border-radius:50%;border:2px solid var(--gold);}
.dot.on{background:var(--gold);}

.done{text-align:center;margin:auto;padding:20px;}
.done .big{font-size:clamp(56px,9vw,96px);line-height:1;}
.err{background:rgba(214,69,69,.92);border-radius:14px;padding:12px 16px;font-weight:700;margin-bottom:12px;color:#fff;}
.staff{position:fixed;bottom:6px;right:10px;font-size:11px;color:rgba(255,255,255,.35);text-decoration:none;}

/* ── Fitting real tablets ─────────────────────────────────
   Landscape iPad is 1080x810 (10.2"), 1180x820 (Air), 1366x1024 (12.9").
   Portrait is the same numbers flipped, so the two panels have to stack
   and the keypad has to stay the biggest thing on screen either way. */

/* Portrait: promo shrinks to a header strip, keypad keeps the room. */
@media (orientation:portrait){
  .wrap{flex-direction:column;gap:14px;}
  .left{flex:0 0 auto;gap:12px;}
  .promo{min-height:150px;max-height:22vh;}
  .promo-body{padding:18px 22px;}
  .promo-title{font-size:clamp(24px,5vw,36px);}
  .queue{padding:14px 16px;gap:12px;}
  .menu{padding:12px 18px;}
  .menu img{width:74px;height:74px;}
  .terms{display:none;}          /* the desk has the printed version */
  .right{flex:1 1 auto;padding:20px 22px;}
}

/* Short landscape (810px tall and under): trim the chrome, not the buttons. */
@media (orientation:landscape) and (max-height:840px){
  .wrap{gap:16px;padding:16px;}
  .left{gap:12px;}
  .queue{padding:14px 16px;}
  .menu{padding:12px 18px;}
  .menu img{width:78px;height:78px;}
  .right{padding:20px 22px;}
  .pad{gap:10px;margin:12px 0;}
  .display{min-height:64px;padding:12px 16px;}
  .next,.back{min-height:64px;}
  .promo-body{padding:22px 24px;}
}

/* Phones and small tablets fall back to a single scrolling column. */
@media (max-width:820px){
  html,body{overflow:auto;}
  .wrap{flex-direction:column;height:auto;min-height:100vh;min-height:100dvh;}
  .left{flex:none;}
  .promo{min-height:170px;}
  .pad button{min-height:62px;}
}
</style>
</head>
<body>
<div class="wrap">

  <!-- ── LEFT ─────────────────────────────────────────────── -->
  <div class="left">
    <div class="panel promo">
      <?php if ($set['kiosk_promo_image']): ?>
        <div class="promo-img" style="background-image:url('<?= e($set['kiosk_promo_image']) ?>')"></div>
        <div class="promo-fade"></div>
      <?php endif; ?>
      <div class="promo-body">
        <div class="promo-kicker"><?= e($biz['business_name'] ?? '') ?></div>
        <div class="promo-title"><?= e($set['kiosk_promo_title']) ?></div>
        <div class="promo-text"><?= e($set['kiosk_promo_text']) ?></div>
      </div>
    </div>

    <?php if ((int)$set['kiosk_show_wait'] === 1): ?>
    <!-- Counts only. No predicted wait: a number the salon can't keep
         turns into an argument at the desk. -->
    <div class="panel queue">
      <div class="qbox"><div class="n" id="qWaiting">—</div><div class="l">Guests waiting</div></div>
      <div class="qbox"><div class="n" id="qFree">—</div><div class="l">Techs available</div></div>
    </div>
    <?php endif; ?>

    <div class="panel menu">
      <?php if ($hasQr): ?>
        <img src="<?= BASE_PATH ?>/pos/assets/menu-qr.png" alt="Scan for our menu">
      <?php endif; ?>
      <div>
        <div class="m-t">📱 Our menu &amp; online booking</div>
        <div class="m-s"><?= $hasQr ? 'Scan the code with your camera' : e($menuUrl) ?></div>
      </div>
    </div>
    <div class="terms"><?= e($set['kiosk_terms']) ?></div>
  </div>

  <!-- ── RIGHT ────────────────────────────────────────────── -->
  <div class="panel right">

    <!-- Step 1: phone -->
    <div class="step on" id="stepPhone">
      <div class="hello">Hello, welcome to</div>
      <div class="salon"><?= e($biz['business_name'] ?? 'our salon') ?></div>
      <div class="ask">Please enter your mobile number to check in</div>
      <div id="phoneErr"></div>
      <div class="display placeholder" id="phoneDisplay">(000) 000-0000</div>
      <div class="pad" id="pad">
        <button type="button" data-k="1">1</button>
        <button type="button" data-k="2">2</button>
        <button type="button" data-k="3">3</button>
        <button type="button" data-k="4">4</button>
        <button type="button" data-k="5">5</button>
        <button type="button" data-k="6">6</button>
        <button type="button" data-k="7">7</button>
        <button type="button" data-k="8">8</button>
        <button type="button" data-k="9">9</button>
        <button type="button" class="fn" data-k="clear">↺</button>
        <button type="button" data-k="0">0</button>
        <button type="button" class="fn" data-k="del">⌫</button>
      </div>
      <button class="next" id="phoneNext" disabled>Next →</button>
    </div>

    <!-- Step 2: name (new guests only) -->
    <div class="step" id="stepName">
      <div class="greet">Nice to meet you!</div>
      <div class="sub">We haven't seen this number before. What's your name?</div>
      <input class="name-input" id="nameInput" placeholder="First and last name" autocomplete="off">

      <!-- Optional, and only the day and month: enough for a birthday
           treat, without asking a stranger for their full date of birth. -->
      <div class="dob">
        <div class="dob-label">🎂 Birthday <span>optional — for a treat on your day</span></div>
        <div class="dob-row">
          <select id="dobMonth" class="dob-sel">
            <option value="">Month</option>
            <?php foreach (['January','February','March','April','May','June','July',
                            'August','September','October','November','December'] as $i => $m): ?>
              <option value="<?= $i + 1 ?>"><?= $m ?></option>
            <?php endforeach; ?>
          </select>
          <select id="dobDay" class="dob-sel">
            <option value="">Day</option>
            <?php for ($d = 1; $d <= 31; $d++): ?><option value="<?= $d ?>"><?= $d ?></option><?php endfor; ?>
          </select>
        </div>
      </div>

      <div style="flex:1"></div>
      <div class="row">
        <button class="back" data-back="stepPhone">← Back</button>
        <button class="next" id="nameNext">Next →</button>
      </div>
    </div>

    <!-- Step 2.5: party size — walk-ins checking in together (e.g. 3
         friends all getting a pedicure) become one linked queue ticket
         instead of each person re-entering a phone number. -->
    <div class="step" id="stepParty">
      <div class="greet">How many in your party today?</div>
      <div class="sub">Include yourself and anyone checking in with you</div>
      <div class="choices" id="partyList">
        <button class="choice sel" type="button" data-party="1">Just me</button>
        <button class="choice" type="button" data-party="2">2 people</button>
        <button class="choice" type="button" data-party="3">3 people</button>
        <button class="choice" type="button" data-party="4">4 people</button>
        <button class="choice" type="button" data-party="5">5 people</button>
      </div>
      <div class="row">
        <button class="back" data-back="stepPhone">← Back</button>
        <button class="next" id="partyNext">Next →</button>
      </div>
    </div>

    <!-- Step 3: service -->
    <div class="step" id="stepService">
      <div class="greet" id="serviceGreet">What are you here for?</div>
      <div class="sub">Tap one, or skip and decide with your technician</div>
      <div id="rewardBanner"></div>
      <div class="choices" id="serviceList">
        <?php foreach ($services as $s): ?>
          <button class="choice <?= $s['image_url'] ? 'photo' : '' ?>" type="button" data-service="<?= (int)$s['id'] ?>"
                  <?= $s['image_url'] ? 'style="background-image:url(\'' . e($s['image_url']) . '\')"' : '' ?>>
            <span><?= e($s['name']) ?><small><?= (int)$s['duration_minutes'] ?> min</small></span>
          </button>
        <?php endforeach; ?>
      </div>
      <div class="row">
        <button class="back" data-back="stepParty">← Back</button>
        <button class="next" id="serviceNext">Next →</button>
      </div>
    </div>

    <!-- Step 4: technician -->
    <div class="step" id="stepTech">
      <div class="greet">Anyone you'd like to see?</div>
      <div class="sub">We'll match you with whoever is free if you don't mind</div>
      <div class="choices" id="techList">
        <button class="choice sel" type="button" data-tech="">No preference<small>Whoever is free next</small></button>
        <?php foreach ($techs as $t): ?>
          <button class="choice" type="button" data-tech="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></button>
        <?php endforeach; ?>
      </div>
      <div class="row">
        <button class="back" data-back="stepService">← Back</button>
        <button class="next" id="techNext">Check in ✓</button>
      </div>
    </div>

    <!-- Step 5: done -->
    <div class="step" id="stepDone">
      <div class="done">
        <div class="big">💅</div>
        <div class="greet" id="doneName">Thank you!</div>
        <div class="sub" id="doneMsg"></div>
        <div id="doneReward"></div>
        <button class="next" id="doneAgain" style="max-width:320px;margin:20px auto 0">Done</button>
      </div>
    </div>

  </div>
</div>
<a class="staff" href="<?= BASE_PATH ?>/pos/queue.php">staff</a>

<script>
(function () {
  'use strict';
  var API  = '<?= BASE_PATH ?>/pos/api/kiosk.php';
  var CSRF = '<?= e(posCsrfToken()) ?>';
  var digits = '', client = null, chosenService = '', chosenTech = '', partySize = 1;
  var idleTimer = null;

  function $(id) { return document.getElementById(id); }

  function post(action, data) {
    var b = new FormData();
    b.append('action', action);
    b.append('_csrf', CSRF);
    Object.keys(data || {}).forEach(function (k) { if (data[k] !== null && data[k] !== '') b.append(k, data[k]); });
    return fetch(API, { method: 'POST', body: b, credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (j) { return r.ok ? j : Promise.reject(j); }); });
  }

  function step(id) {
    [].forEach.call(document.querySelectorAll('.step'), function (s) { s.classList.remove('on'); });
    $(id).classList.add('on');
    resetIdle();
  }

  // A guest who walks off mid-entry shouldn't leave their number on screen.
  function resetIdle() {
    clearTimeout(idleTimer);
    idleTimer = setTimeout(reset, 90000);
  }

  function reset() {
    digits = ''; client = null; chosenService = ''; chosenTech = ''; partySize = 1;
    paintPhone();
    $('phoneErr').innerHTML = '';
    $('nameInput').value = '';
    [].forEach.call(document.querySelectorAll('#serviceList .choice'), function (c) { c.classList.remove('sel'); });
    [].forEach.call(document.querySelectorAll('#techList .choice'), function (c, i) { c.classList.toggle('sel', i === 0); });
    [].forEach.call(document.querySelectorAll('#partyList .choice'), function (c, i) { c.classList.toggle('sel', i === 0); });
    step('stepPhone');
  }

  /* ── Phone entry ─────────────────────────────────────── */
  function pretty(d) {
    if (d.length <= 3) return d;
    if (d.length <= 6) return '(' + d.slice(0, 3) + ') ' + d.slice(3);
    return '(' + d.slice(0, 3) + ') ' + d.slice(3, 6) + '-' + d.slice(6, 10);
  }
  function paintPhone() {
    var el = $('phoneDisplay');
    el.textContent = digits ? pretty(digits) : '(000) 000-0000';
    el.classList.toggle('placeholder', !digits);
    $('phoneNext').disabled = digits.length < 10;
  }

  $('pad').addEventListener('click', function (ev) {
    var b = ev.target.closest('button');
    if (!b) return;
    var k = b.getAttribute('data-k');
    if (k === 'del') digits = digits.slice(0, -1);
    else if (k === 'clear') digits = '';
    else if (digits.length < 10) digits += k;
    paintPhone();
    resetIdle();
  });

  $('phoneNext').addEventListener('click', function () {
    post('lookup', { phone: digits }).then(function (r) {
      if (!r.known) { step('stepName'); setTimeout(function () { $('nameInput').focus(); }, 200); return; }
      client = r.client;
      if (r.already_here) {
        $('doneName').textContent = 'You\'re already checked in, ' + client.first_name + '!';
        $('doneMsg').textContent = 'Please take a seat — we\'ll be with you shortly.';
        $('doneReward').innerHTML = '';
        step('stepDone');
        setTimeout(reset, 8000);
        return;
      }
      $('serviceGreet').textContent = 'Welcome back, ' + client.first_name + '!';
      var html = '';
      if (client.has_reward) {
        html = '<div class="reward">🎉 You have a free ' + client.reward + ' waiting — just mention it at the desk.</div>';
      } else if (client.per_card) {
        var dots = '';
        for (var i = 0; i < client.per_card; i++) dots += '<div class="dot' + (i < client.stamps ? ' on' : '') + '"></div>';
        html = '<div class="reward">Stamp card: ' + client.stamps + ' of ' + client.per_card +
               '<div class="stampdots">' + dots + '</div></div>';
      }
      $('rewardBanner').innerHTML = html;
      step('stepParty');
    }).catch(function (e) {
      $('phoneErr').innerHTML = '<div class="err">' + (e && e.error ? e.error : 'Something went wrong.') + '</div>';
    });
  });

  /* ── Name (new guests) ───────────────────────────────── */
  $('nameNext').addEventListener('click', function () {
    var n = $('nameInput').value.trim();
    if (n.length < 2) { $('nameInput').focus(); return; }
    $('serviceGreet').textContent = 'Thanks, ' + n.split(' ')[0] + '!';
    $('rewardBanner').innerHTML = '';
    step('stepParty');
  });

  /* ── Party size ──────────────────────────────────────── */
  $('partyList').addEventListener('click', function (ev) {
    var c = ev.target.closest('.choice');
    if (!c) return;
    [].forEach.call(this.querySelectorAll('.choice'), function (x) { x.classList.remove('sel'); });
    c.classList.add('sel');
    partySize = parseInt(c.getAttribute('data-party'), 10) || 1;
    resetIdle();
  });
  $('partyNext').addEventListener('click', function () { step('stepService'); });

  /* ── Service and technician ──────────────────────────── */
  $('serviceList').addEventListener('click', function (ev) {
    var c = ev.target.closest('.choice');
    if (!c) return;
    var already = c.classList.contains('sel');
    [].forEach.call(this.querySelectorAll('.choice'), function (x) { x.classList.remove('sel'); });
    if (!already) { c.classList.add('sel'); chosenService = c.getAttribute('data-service'); }
    else chosenService = '';
    resetIdle();
  });
  $('serviceNext').addEventListener('click', function () { step('stepTech'); });

  $('techList').addEventListener('click', function (ev) {
    var c = ev.target.closest('.choice');
    if (!c) return;
    [].forEach.call(this.querySelectorAll('.choice'), function (x) { x.classList.remove('sel'); });
    c.classList.add('sel');
    chosenTech = c.getAttribute('data-tech') || '';
    resetIdle();
  });

  $('techNext').addEventListener('click', function () {
    var btn = this;
    btn.disabled = true;
    post('checkin', {
      phone: digits,
      name: client ? client.full_name : $('nameInput').value.trim(),
      service_id: chosenService,
      technician_id: chosenTech,
      party_size: partySize,
      dob_month: client ? '' : $('dobMonth').value,
      dob_day:   client ? '' : $('dobDay').value
    }).then(function (r) {
      btn.disabled = false;
      $('doneName').textContent = partySize > 1
        ? 'Thank you — party of ' + partySize + '!'
        : 'Thank you, ' + r.first_name + '!';
      if (r.duplicate) {
        $('doneMsg').textContent = 'You were already checked in — please take a seat.';
      } else {
        $('doneMsg').textContent = r.ahead > 0
          ? (r.ahead === 1 ? 'There is 1 guest ahead of you. Please take a seat.'
                           : 'There are ' + r.ahead + ' guests ahead of you. Please take a seat.')
          : 'We\'ll be right with you.';
      }
      var extra = '';
      if (r.card && r.card.has_reward) extra = '<div class="reward">🎉 Don\'t forget your free ' + r.card.reward + '!</div>';
      else if (r.points) extra = '<div class="sub">⭐ ' + r.points + ' reward points on your account</div>';
      $('doneReward').innerHTML = extra;
      step('stepDone');
      refreshQueue();
      setTimeout(reset, 12000);
    }).catch(function (e) {
      btn.disabled = false;
      alert(e && e.error ? e.error : 'Sorry, something went wrong. Please see the front desk.');
    });
  });

  $('doneAgain').addEventListener('click', reset);
  [].forEach.call(document.querySelectorAll('[data-back]'), function (b) {
    b.addEventListener('click', function () { step(b.getAttribute('data-back')); });
  });

  /* ── Live queue figures ──────────────────────────────── */
  function refreshQueue() {
    if (!$('qWaiting')) return;
    fetch(API + '?action=status', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (r) {
        $('qWaiting').textContent = r.waiting;
        $('qFree').textContent = r.free;
      })
      .catch(function () {});
  }
  refreshQueue();
  setInterval(refreshQueue, 30000);
  paintPhone();
  resetIdle();
})();
</script>
</body>
</html>
