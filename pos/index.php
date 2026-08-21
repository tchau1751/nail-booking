<?php
$pageTitle = 'Register';
$activeNav = 'register';
$fullBleed = true;
require_once __DIR__ . '/includes/layout_start.php';
require_once __DIR__ . '/includes/salon.php';
require_once __DIR__ . '/includes/rewards.php';

// Deep links from the queue board and client pages preload the ticket.
$preload = '';
if (isset($_GET['checkin'])) $preload = 'load_checkin:' . (int)$_GET['checkin'];
if (isset($_GET['client']))  $preload = 'attach_client:' . (int)$_GET['client'];

$services    = fetchAll('SELECT * FROM services WHERE is_active=1 ORDER BY display_order, name');
$products    = fetchAll('SELECT * FROM pos_products WHERE is_active=1 ORDER BY display_order, name');
$technicians = fetchAll('SELECT id,name FROM technicians WHERE is_active=1 ORDER BY display_order, name');
$today       = date('Y-m-d');
$appointments = fetchAll(
    "SELECT a.id, a.full_name, a.phone, a.start_time, a.status, s.name AS service_name, s.price
     FROM appointments a JOIN services s ON s.id = a.service_id
     WHERE a.appointment_date = ? AND a.status IN ('pending','confirmed')
     ORDER BY a.start_time", [$today]);
$waiting = waitingList();

$catalog = [];
foreach ($services as $s) {
    // No duration on the till tiles: ringing up needs the name and the price,
    // and the minutes only crowd the tile. Booking still uses the duration.
    $catalog[] = ['kind'=>'service','id'=>(int)$s['id'],'name'=>$s['name'],
                  'cat'=>'Services','meta'=>'','price'=>(float)$s['price'],
                  'stock'=>null,'img'=>$s['image_url'] ?? ''];
}
foreach ($products as $p) {
    $catalog[] = ['kind'=>'product','id'=>(int)$p['id'],'name'=>$p['name'],
                  'cat'=>$p['category'] ?: 'Retail','meta'=>$p['sku'],'price'=>(float)$p['price'],
                  'stock'=>(int)$p['stock_qty'],'img'=>''];
}
$categories = array_values(array_unique(array_column($catalog, 'cat')));
$tipPresets = array_filter(array_map('trim', explode(',', posSettings()['tip_presets'])), 'strlen');
?>

<!-- ── CATALOG ────────────────────────────────────────────── -->
<section class="catalog">
  <div class="catalog-head">
    <input type="search" id="search" placeholder="Search or scan barcode…" autocomplete="off" enterkeyhint="done">
    <button class="btn btn-light btn-sm" id="btnClearSearch" type="button">Clear</button>
  </div>
  <div class="tabs" id="tabs">
    <button class="tab active" data-cat="__all">All</button>
    <?php foreach ($categories as $c): ?>
      <button class="tab" data-cat="<?= e($c) ?>"><?= e($c) ?></button>
    <?php endforeach; ?>
    <?php if ($waiting): ?>
      <button class="tab" data-cat="__queue">🪑 Waiting (<?= count($waiting) ?>)</button>
    <?php endif; ?>
    <?php if ($appointments): ?>
      <button class="tab" data-cat="__appts">📅 Today (<?= count($appointments) ?>)</button>
    <?php endif; ?>
  </div>

  <div class="tiles" id="tiles">
    <button class="tile custom" data-cat="__all" data-name="custom item" type="button" id="tileCustom">
      <div class="t-name">＋ Custom amount</div>
      <div class="t-meta">Add-on or off-menu work · manager approves</div>
    </button>
    <?php foreach ($catalog as $it):
      $out = $it['kind'] === 'product' && $it['stock'] !== null && $it['stock'] <= 0; ?>
      <button type="button"
        class="tile <?= $it['kind'] ?> <?= $out ? 'out' : '' ?> <?= $it['img'] ? 'has-img' : '' ?>"
        data-cat="<?= e($it['cat']) ?>"
        data-name="<?= e(mb_strtolower($it['name'] . ' ' . $it['meta'])) ?>"
        data-kind="<?= $it['kind'] ?>" data-id="<?= $it['id'] ?>">
        <?php if ($it['img']): ?>
          <span class="t-img" style="background-image:url('<?= e($it['img']) ?>')"></span>
        <?php endif; ?>
        <div class="t-name"><?= e($it['name']) ?></div>
        <?php if ($it['meta'] !== '' || $it['stock'] !== null): // services have neither, so no empty line ?>
          <div class="t-meta">
            <?= e($it['meta']) ?>
            <?php if ($it['stock'] !== null): ?> · <?= $out ? 'Out of stock' : $it['stock'] . ' in stock' ?><?php endif; ?>
          </div>
        <?php endif; ?>
        <div class="t-price"><?= money($it['price']) ?></div>
      </button>
    <?php endforeach; ?>

    <?php foreach ($waiting as $w): ?>
      <button type="button" class="tile service" data-cat="__queue"
        data-name="<?= e(mb_strtolower($w['guest_name'] . ' ' . ($w['service_name'] ?? ''))) ?>"
        data-kind="checkin" data-id="<?= (int)$w['id'] ?>">
        <div class="t-name"><?= e($w['guest_name']) ?></div>
        <div class="t-meta">
          <?= $w['status'] === 'in_service' ? 'with ' . e($w['assigned_tech']) : 'waiting ' . (int)$w['waited_min'] . ' min' ?>
          <?php if ($w['service_name']): ?> · <?= e($w['service_name']) ?><?php endif; ?>
        </div>
        <div class="t-price"><?= $w['service_price'] !== null ? money($w['service_price']) : '—' ?></div>
      </button>
    <?php endforeach; ?>

    <?php foreach ($appointments as $a): ?>
      <button type="button" class="tile service" data-cat="__appts"
        data-name="<?= e(mb_strtolower($a['full_name'] . ' ' . $a['service_name'])) ?>"
        data-kind="appointment" data-id="<?= (int)$a['id'] ?>">
        <div class="t-name"><?= e($a['full_name']) ?></div>
        <div class="t-meta"><?= date('g:i A', strtotime($a['start_time'])) ?> · <?= e($a['service_name']) ?></div>
        <div class="t-price"><?= money($a['price']) ?></div>
      </button>
    <?php endforeach; ?>
  </div>
</section>

<!-- ── TICKET ─────────────────────────────────────────────── -->
<aside class="ticket">
  <!-- The client's points, profile link and Remove sit on the same row as the
       guest name instead of on a bar of their own: one row back for the
       ticket lines. Styling lives in pos.css so the [hidden] toggle that
       pos.js flips still works — an inline display would have beaten it. -->
  <div class="ticket-head">
    <div class="who">
      <span id="custName">Walk-in</span>
      <div class="sub" id="custSub">No technician</div>
    </div>
    <span id="clientBar" hidden>
      <span id="clientPoints"></span>
      <a id="clientLink" href="#" target="_blank" rel="noopener">profile</a>
      <button class="btn btn-light btn-sm" type="button" id="btnDetachClient">Remove</button>
    </span>
    <button class="btn btn-light btn-sm" type="button" id="btnFindClient">👥 Client</button>
    <button class="btn btn-light btn-sm" type="button" id="btnCustomer">Edit</button>
  </div>

  <div class="lines" id="lines">
    <div class="empty">Tap a service or product to start a ticket.</div>
  </div>

  <div class="totals">
    <div class="row"><span>Subtotal</span><b id="tSubtotal">—</b></div>
    <div class="row" id="rowDiscount" hidden><span>Discount</span><b id="tDiscount">—</b></div>
    <div class="row" id="rowTax" hidden><span id="tTaxLabel">Tax</span><b id="tTax">—</b></div>
    <div class="row" id="rowTip" hidden><span>Tip</span><b id="tTip">—</b></div>
    <div class="row" id="rowGift" hidden><span>Gift card</span><b id="tGift">—</b></div>
    <div class="row" id="rowPoints" hidden><span>Points</span><b id="tPoints">—</b></div>
    <!-- Sized by pos.css, not inline: an inline font-size cannot be overridden
         by the stylesheet's screen-size rules. -->
    <div class="row grand"><span id="grandLabel">Total</span><span id="tTotal">—</span></div>
  </div>

  <div class="ticket-actions">
    <button class="btn btn-light" type="button" id="btnDiscount">％ Discount</button>
    <button class="btn btn-light" type="button" id="btnTip">💛 Tip</button>
    <button class="btn btn-light" type="button" id="btnGift">🎁 Gift card</button>
    <button class="btn btn-light" type="button" id="btnPoints">⭐ Points</button>
    <button class="btn btn-light" type="button" id="btnClear">🗑 Clear</button>
    <button class="btn btn-light" type="button" id="btnHold" title="Park this ticket in the browser">⏸ Hold</button>
    <button class="btn btn-green btn-lg pay" type="button" id="btnPay" disabled>Charge —</button>
  </div>
</aside>

<!-- ── Modals ─────────────────────────────────────────────── -->
<div class="modal" id="mPad">
  <div class="modal-box">
    <h3 id="padTitle">Amount</h3>
    <div class="amount-display" id="padDisplay">0.00</div>
    <div id="padExtra"></div>
    <div class="pad">
      <button type="button" data-k="1">1</button><button type="button" data-k="2">2</button><button type="button" data-k="3">3</button>
      <button type="button" data-k="4">4</button><button type="button" data-k="5">5</button><button type="button" data-k="6">6</button>
      <button type="button" data-k="7">7</button><button type="button" data-k="8">8</button><button type="button" data-k="9">9</button>
      <button type="button" data-k="00">00</button><button type="button" data-k="0">0</button><button type="button" data-k="del">⌫</button>
    </div>
    <div class="modal-actions">
      <button class="btn btn-light" type="button" data-close>Cancel</button>
      <button class="btn btn-green" type="button" id="padOk">Done</button>
    </div>
  </div>
</div>

<div class="modal" id="mCustomer">
  <div class="modal-box">
    <h3>Ticket details</h3>
    <label class="field"><span>Customer name</span><input type="text" id="cName" placeholder="Walk-in"></label>
    <label class="field"><span>Phone</span><input type="text" id="cPhone" inputmode="tel" placeholder="(555) 123-4567"></label>
    <label class="field"><span>Default technician</span>
      <select id="cTech">
        <option value="">— none —</option>
        <?php foreach ($technicians as $t): ?>
          <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <p class="sub" style="margin:-6px 0 12px;font-size:13px">Each service is credited to whoever is picked on its own line — this only covers retail and anything unassigned.</p>
    <label class="field"><span>Note</span><input type="text" id="cNote" placeholder="Optional"></label>
    <div class="modal-actions">
      <button class="btn btn-light" type="button" data-close>Cancel</button>
      <button class="btn btn-green" type="button" id="cSave">Save</button>
    </div>
  </div>
</div>

<!-- Every service has to be credited to somebody, so this opens the moment a
     service tile is tapped and again if the line is tapped to reassign it. -->
<div class="modal" id="mTech">
  <div class="modal-box">
    <h3 id="techTitle">Choose technician</h3>
    <div class="chips" id="techChoices"></div>
    <div class="modal-actions">
      <button class="btn btn-light" type="button" data-close>Cancel</button>
    </div>
  </div>
</div>

<div class="modal" id="mClient">
  <div class="modal-box">
    <h3>Find a client</h3>
    <label class="field"><span>Name or phone</span>
      <input type="search" id="clientSearch" placeholder="Start typing…" autocomplete="off"></label>
    <div id="clientResults" style="max-height:280px;overflow-y:auto"></div>
    <hr style="border:none;border-top:1px solid var(--line);margin:16px 0">
    <h3 style="font-size:16px">New client</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <label class="field"><span>Name</span><input type="text" id="newClientName"></label>
      <label class="field"><span>Phone</span><input type="text" id="newClientPhone" inputmode="tel"></label>
    </div>
    <div class="modal-actions">
      <button class="btn btn-light" type="button" data-close>Cancel</button>
      <button class="btn btn-green" type="button" id="newClientGo">Create &amp; attach</button>
    </div>
  </div>
</div>

<div class="modal" id="mGift">
  <div class="modal-box">
    <h3>Gift cards</h3>
    <h4 style="font-size:14px;color:var(--ink-soft);margin-bottom:8px">Pay with a card</h4>
    <div style="display:flex;gap:10px;align-items:flex-end">
      <label class="field" style="flex:1"><span>Card code</span>
        <input type="text" id="giftCode" placeholder="ABCD-1234-EFGH-5678" autocomplete="off"></label>
      <button class="btn btn-green" type="button" id="giftApply" style="margin-bottom:12px">Apply</button>
    </div>
    <div id="giftApplied"></div>
    <hr style="border:none;border-top:1px solid var(--line);margin:16px 0">
    <h4 style="font-size:14px;color:var(--ink-soft);margin-bottom:8px">Sell a new card</h4>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <label class="field"><span>Amount</span><input type="number" id="giftAmount" step="0.01" inputmode="decimal" placeholder="50.00"></label>
      <label class="field"><span>For (optional)</span><input type="text" id="giftRecipient" placeholder="Recipient name"></label>
    </div>
    <div class="chips" id="giftPresets">
      <button class="chip" type="button" data-amt="25">$25</button>
      <button class="chip" type="button" data-amt="50">$50</button>
      <button class="chip" type="button" data-amt="75">$75</button>
      <button class="chip" type="button" data-amt="100">$100</button>
    </div>
    <div class="modal-actions">
      <button class="btn btn-light" type="button" data-close>Close</button>
      <button class="btn btn-gold" type="button" id="giftSell">Add card to ticket</button>
    </div>
  </div>
</div>

<div class="modal" id="mPoints">
  <div class="modal-box">
    <h3>Redeem points</h3>
    <div id="pointsInfo" class="alert alert-ok" style="font-weight:600"></div>
    <label class="field"><span>Points to redeem</span>
      <input type="number" id="pointsInput" inputmode="numeric" step="1" min="0"></label>
    <div class="chips" id="pointsChips"></div>
    <div class="modal-actions">
      <button class="btn btn-light" type="button" data-close>Cancel</button>
      <button class="btn btn-light" type="button" id="pointsNone">Redeem none</button>
      <button class="btn btn-green" type="button" id="pointsGo">Apply</button>
    </div>
  </div>
</div>

<div class="modal" id="mPay">
  <div class="modal-box">
    <h3>Take payment</h3>
    <div class="amount-display" id="payDue">0.00</div>
    <div class="chips" id="payMethods">
      <button class="chip active" type="button" data-m="cash">💵 Cash</button>
      <button class="chip" type="button" data-m="card">💳 Card</button>
      <button class="chip" type="button" data-m="gift">🎁 Gift card</button>
      <button class="chip" type="button" data-m="other">• Other</button>
    </div>
    <label class="field"><span>Amount tendered</span><input type="number" id="payAmount" step="0.01" inputmode="decimal"></label>
    <div class="chips" id="quickCash"></div>
    <label class="field"><span>Reference (last 4, auth code…)</span><input type="text" id="payRef" placeholder="Optional"></label>
    <div id="payMsg"></div>
    <div class="modal-actions">
      <button class="btn btn-light" type="button" data-close>Cancel</button>
      <button class="btn btn-green btn-lg" type="button" id="payGo">Complete sale</button>
    </div>
  </div>
</div>

<div class="modal" id="mDone">
  <div class="modal-box" style="text-align:center">
    <h3 style="font-size:24px">✅ Sale complete</h3>
    <div class="amount-display" id="doneChange" style="text-align:center">Change $0.00</div>
    <div class="modal-actions">
      <a class="btn btn-blue btn-lg" id="doneReceipt" target="_blank" rel="noopener">🧾 Receipt</a>
      <button class="btn btn-green btn-lg" type="button" id="doneNew">New ticket</button>
    </div>
  </div>
</div>

<script>
  window.POS = {
    api: '<?= BASE_PATH ?>/pos/api/cart.php',
    base: '<?= BASE_PATH ?>',
    currency: '<?= e(posSettings()['currency_symbol']) ?>',
    tipPresets: <?= json_encode(array_map('floatval', array_values($tipPresets))) ?>,
    minRedeem: <?= (int)(posSettings()['points_min_redeem'] ?? 100) ?>,
    techs: <?= json_encode(array_map(function ($t) {
      return ['id' => (int)$t['id'], 'name' => $t['name']];
    }, $technicians)) ?>,
    preload: <?= json_encode($preload) ?>
  };
</script>
<script src="<?= BASE_PATH ?>/pos/assets/pos.js?v=<?= @filemtime(__DIR__ . '/assets/pos.js') ?>"></script>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
