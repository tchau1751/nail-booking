/* ============================================================
   Diamond Nail POS — register screen.
   The server owns the cart; this file only sends taps and paints
   whatever the server sends back.
   ============================================================ */
(function () {
  'use strict';

  var api = POS.api, cur = POS.currency;
  var state = null;                 // last ticket payload from the server
  var lastTech = null;              // pre-selects the picker; a ticket is usually one person
  var HOLD_KEY = 'pos_held_ticket';

  var $ = function (id) { return document.getElementById(id); };
  function fmt(n) { return cur + (Math.round(n * 100) / 100).toFixed(2); }

  function post(action, data) {
    var body = new FormData();
    body.append('action', action);
    Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
    return fetch(api, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (j) { return r.ok ? j : Promise.reject(j); }); })
      .catch(function (err) {
        if (err && err.error) { toast(err.error); return Promise.reject(err); }
        toast('Network error — is XAMPP still running?');
        return Promise.reject(err);
      });
  }

  /**
   * Runs a call that a manager has to bless. If the server says the till needs
   * approval, the manager taps their own PIN in and the call is retried once —
   * no signing the technician out and back in mid-ticket.
   */
  function withManagerApproval(run) {
    return run().catch(function (err) {
      if (!err || !err.needs_manager) return Promise.reject(err);
      var pin = window.prompt('Manager PIN to approve this:');
      if (!pin) return Promise.reject(err);
      var body = new FormData();
      body.append('pin', pin);
      return fetch(POS.base + '/pos/api/approve.php', { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json().then(function (j) { return r.ok ? j : Promise.reject(j); }); })
        .then(function (j) { toast('Approved by ' + j.by); return run(); })
        .catch(function (e) { toast((e && e.error) || 'Approval failed.'); return Promise.reject(e); });
    });
  }

  /**
   * Ask who is doing the work. Resolves with a technician id, or rejects if
   * the till is dismissed — the caller then leaves the ticket untouched.
   * Modelled on the shop's old POS: pick, and it commits on the tap.
   */
  function chooseTech(title, currentId) {
    return new Promise(function (resolve, reject) {
      $('techTitle').textContent = title || 'Choose technician';
      $('techChoices').innerHTML = POS.techs.map(function (t) {
        return '<button class="chip' + (String(t.id) === String(currentId) ? ' active' : '') +
               '" type="button" data-tech="' + t.id + '">' + t.name + '</button>';
      }).join('') || '<div class="empty">No active technicians — add one under Staff.</div>';

      var box = $('mTech');
      function cleanup() {
        box.removeEventListener('click', onClick);
        box.classList.remove('open');
      }
      function onClick(ev) {
        var c = ev.target.closest('[data-tech]');
        if (c) { cleanup(); resolve(parseInt(c.getAttribute('data-tech'), 10)); return; }
        // Cancel, the backdrop, or Esc — all mean "changed my mind".
        if (ev.target.hasAttribute('data-close') || ev.target === box) { cleanup(); reject(); }
      }
      box.addEventListener('click', onClick);
      box.classList.add('open');
    });
  }

  function toast(msg) {
    var t = $('posToast');
    if (!t) {
      t = document.createElement('div');
      t.id = 'posToast';
      t.style.cssText = 'position:fixed;left:50%;bottom:28px;transform:translateX(-50%);background:#3a2a24;' +
        'color:#fff;padding:14px 22px;border-radius:12px;font-weight:700;z-index:200;box-shadow:0 8px 24px rgba(0,0,0,.3)';
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.style.display = 'block';
    clearTimeout(t._h);
    t._h = setTimeout(function () { t.style.display = 'none'; }, 2600);
  }

  /* ── Painting ─────────────────────────────────────────── */
  function render(s) {
    state = s;
    if (!lastTech && s.meta && s.meta.technician_id) lastTech = s.meta.technician_id;
    var box = $('lines');
    box.innerHTML = '';
    if (!s.lines.length) {
      box.innerHTML = '<div class="empty">Tap a service or product to start a ticket.</div>';
    }
    s.lines.forEach(function (l) {
      var row = document.createElement('div');
      row.className = 'line';
      row.innerHTML =
        '<div class="l-main"><div class="l-name"></div><div class="l-sub"></div></div>' +
        '<div class="stepper"><button type="button" data-act="dec">−</button>' +
        '<span class="qty"></span><button type="button" data-act="inc">＋</button></div>' +
        '<div class="l-total"></div><button class="l-del" type="button" data-act="del">✕</button>';
      row.querySelector('.l-name').textContent = l.name;
      row.querySelector('.l-sub').textContent = fmt(l.price) + ' each' +
        (l.discount > 0 ? ' · −' + fmt(l.discount) : '') +
        (l.tip > 0 ? ' · tip ' + fmt(l.tip) : '');
      // Only services are somebody's work. Retail belongs to the shop.
      if (l.type === 'service') {
        var tb = document.createElement('button');
        tb.type = 'button';
        tb.className = 'l-tech' + (l.needs_tech ? ' missing' : '');
        tb.setAttribute('data-act', 'tech');
        tb.textContent = l.technician || '⚠ Choose technician';
        row.querySelector('.l-main').appendChild(tb);
      }
      row.querySelector('.qty').textContent = l.qty;
      row.querySelector('.l-total').textContent = fmt(l.total);
      row.addEventListener('click', function (ev) {
        var act = ev.target.getAttribute('data-act');
        if (act === 'inc') post('set_qty', { key: l.key, qty: l.qty + 1 }).then(render);
        if (act === 'dec') post('set_qty', { key: l.key, qty: l.qty - 1 }).then(render);
        if (act === 'del') post('remove', { key: l.key }).then(render);
        if (act === 'tech') {
          chooseTech('Who did ' + l.name + '?', l.technician_id)
            .then(function (id) { return post('set_line_tech', { key: l.key, technician_id: id }); })
            .then(render)
            .catch(function () {});
        }
      });
      box.appendChild(row);
    });

    var t = s.totals;
    $('tSubtotal').textContent = fmt(t.subtotal);
    $('tDiscount').textContent = '−' + fmt(t.discount);
    $('rowDiscount').hidden = t.discount <= 0;
    $('tTaxLabel').textContent = t.tax_label + ' (' + t.tax_rate + '%)';
    $('tTax').textContent = fmt(t.tax);
    $('rowTax').hidden = t.tax <= 0;
    $('tTip').textContent = fmt(t.tip);
    $('rowTip').hidden = t.tip <= 0;
    $('tGift').textContent = '−' + fmt(t.gift);
    $('rowGift').hidden = t.gift <= 0;
    $('tPoints').textContent = '−' + fmt(t.points_value);
    $('rowPoints').hidden = t.points_value <= 0;

    // Once a gift card or points are on the ticket, the big number is what
    // is still owed — that is the number the guest cares about.
    var tendered = t.gift + t.points_value;
    $('grandLabel').textContent = tendered > 0 ? 'Still due' : 'Total';
    $('tTotal').textContent = fmt(tendered > 0 ? t.due : t.total);

    var unassigned = (s.meta.missing_tech || []).length;
    $('btnPay').disabled = s.count < 1 || unassigned > 0;
    if (s.count < 1) $('btnPay').textContent = 'Charge —';
    else if (unassigned > 0) $('btnPay').textContent = unassigned === 1
      ? 'Choose a technician first'
      : 'Choose technicians first (' + unassigned + ')';
    else if (t.due <= 0) $('btnPay').textContent = 'Finish — paid in full';
    else $('btnPay').textContent = 'Charge ' + fmt(t.due);

    // Client bar
    var cl = s.meta.client;
    $('clientBar').hidden = !cl;
    // With a client attached, "Client" (which opens the find-a-client search)
    // is dead weight and the header row needs the width — Remove puts it back.
    $('btnFindClient').hidden = !!cl;
    if (cl) {
      $('clientPoints').textContent = '⭐ ' + cl.points + ' pts'   // the cash value shows in the Points dialog;
      $('clientLink').href = POS.base + '/pos/client.php?id=' + cl.id;
    }
    $('btnPoints').disabled = !cl || cl.points < POS.minRedeem;

    $('custName').textContent = s.meta.customer_name || 'Walk-in';
    var techSel = $('cTech');
    var techName = '';
    for (var i = 0; i < techSel.options.length; i++) {
      if (techSel.options[i].value === String(s.meta.technician_id || '')) techName = techSel.options[i].text;
    }
    $('custSub').textContent = (techName && s.meta.technician_id ? techName : 'No technician') +
      (s.meta.appointment_id ? ' · booking #' + s.meta.appointment_id : '');

    paintHold();
  }

  /* ── Catalog ──────────────────────────────────────────── */
  var tiles = Array.prototype.slice.call(document.querySelectorAll('#tiles .tile'));
  var activeCat = '__all';

  function applyFilter() {
    var q = $('search').value.trim().toLowerCase();
    tiles.forEach(function (el) {
      var cat = el.getAttribute('data-cat');
      var isCustom = el.id === 'tileCustom';
      var catOk = isCustom ? (activeCat === '__all') : (activeCat === '__all' ? cat !== '__appts' : cat === activeCat);
      var qOk = !q || (el.getAttribute('data-name') || '').indexOf(q) !== -1;
      el.style.display = (catOk && qOk) ? '' : 'none';
    });
  }

  $('tabs').addEventListener('click', function (ev) {
    var tab = ev.target.closest('.tab');
    if (!tab) return;
    document.querySelectorAll('#tabs .tab').forEach(function (t) { t.classList.remove('active'); });
    tab.classList.add('active');
    activeCat = tab.getAttribute('data-cat');
    applyFilter();
  });

  $('search').addEventListener('input', applyFilter);
  $('btnClearSearch').addEventListener('click', function () { $('search').value = ''; applyFilter(); $('search').focus(); });

  // A barcode wedge types fast then hits Enter — treat Enter as a scan.
  $('search').addEventListener('keydown', function (ev) {
    if (ev.key !== 'Enter') return;
    ev.preventDefault();
    var code = $('search').value.trim();
    if (!code) return;
    post('scan', { code: code }).then(function (s) {
      $('search').value = '';
      applyFilter();
      toast('Added ' + s.scanned);
      render(s);
    }).catch(function () {});
  });

  $('tiles').addEventListener('click', function (ev) {
    var tile = ev.target.closest('.tile');
    if (!tile) return;
    if (tile.id === 'tileCustom') return openCustom();
    var kind = tile.getAttribute('data-kind'), id = tile.getAttribute('data-id');
    if (tile.classList.contains('out')) return toast('Out of stock — sell it anyway from Products.');
    var action = { product: 'add_product', appointment: 'load_appointment', checkin: 'load_checkin' }[kind] || 'add_service';
    if (action !== 'add_service') {
      post(action, { id: id }).then(render).catch(function () {});
      return;
    }
    // Asked every time, because a ticket routinely spans two chairs. The last
    // person picked comes up highlighted, so the common case is one tap.
    chooseTech('Who is doing ' + (tile.querySelector('.t-name') || {}).textContent + '?', lastTech)
      .then(function (techId) {
        lastTech = techId;
        return post('add_service', { id: id, technician_id: techId });
      })
      .then(render)
      .catch(function () {});
  });

  /* ── Modals ───────────────────────────────────────────── */
  function open(id) { $(id).classList.add('open'); }
  function close(id) { $(id).classList.remove('open'); }
  document.addEventListener('click', function (ev) {
    if (ev.target.hasAttribute && ev.target.hasAttribute('data-close')) ev.target.closest('.modal').classList.remove('open');
    if (ev.target.classList && ev.target.classList.contains('modal')) ev.target.classList.remove('open');
  });

  /* Numeric pad — reused for custom amount, discount and tip. */
  var padDigits = '', padMode = null;

  function openPad(mode, title, extraHtml) {
    padMode = mode; padDigits = '';
    $('padTitle').textContent = title;
    $('padExtra').innerHTML = extraHtml || '';
    paintPad();
    open('mPad');
  }
  function padValue() { return (parseInt(padDigits || '0', 10)) / 100; }
  function paintPad() {
    $('padDisplay').textContent = padMode === 'discount_pct'
      ? (padValue() * 100).toFixed(0) + '%'
      : fmt(padValue());
  }
  $('mPad').querySelector('.pad').addEventListener('click', function (ev) {
    var k = ev.target.getAttribute('data-k');
    if (!k) return;
    if (k === 'del') padDigits = padDigits.slice(0, -1);
    else padDigits = (padDigits + k).replace(/^0+/, '').slice(0, 8);
    paintPad();
  });

  function openCustom() {
    openPad('custom', 'Custom amount',
      '<label class="field"><span>Description</span><input type="text" id="padName" placeholder="Custom item"></label>');
  }
  $('btnDiscount').addEventListener('click', function () {
    openPad('discount_amt', 'Discount',
      '<div class="chips" id="discModes">' +
      '<button class="chip active" type="button" data-mode="discount_amt">Amount</button>' +
      '<button class="chip" type="button" data-mode="discount_pct">Percent</button>' +
      '<button class="chip" type="button" data-mode="discount_off">Remove</button></div>');
    $('discModes').addEventListener('click', function (ev) {
      var c = ev.target.closest('.chip'); if (!c) return;
      document.querySelectorAll('#discModes .chip').forEach(function (x) { x.classList.remove('active'); });
      c.classList.add('active');
      padMode = c.getAttribute('data-mode');
      if (padMode === 'discount_off') padDigits = '';
      paintPad();
    });
  });
  var tipMethod = 'card';
  $('btnTip').addEventListener('click', function () {
    var sub = state ? state.totals.subtotal - state.totals.discount : 0;
    tipMethod = (state && state.meta.tip_method) || 'card';
    var chips = POS.tipPresets.map(function (p) {
      return '<button class="chip" type="button" data-tip="' + (sub * p / 100).toFixed(2) + '">' + p + '% · ' + fmt(sub * p / 100) + '</button>';
    }).join('') + '<button class="chip" type="button" data-tip="0">No tip</button>';
    // Cash or card is not cosmetic: a cash tip is already in the technician's
    // pocket, a card tip is money the shop still owes them on payday.
    var methods = '<div class="chips" id="tipMethods" style="margin-bottom:10px">' +
      '<button class="chip' + (tipMethod === 'card' ? ' active' : '') + '" type="button" data-tm="card">💳 On the card</button>' +
      '<button class="chip' + (tipMethod === 'cash' ? ' active' : '') + '" type="button" data-tm="cash">💵 Cash in hand</button>' +
      '</div>';
    openPad('tip', 'Tip', methods + '<div class="chips" id="tipChips">' + chips + '</div>');
    $('tipMethods').addEventListener('click', function (ev) {
      var c = ev.target.closest('.chip'); if (!c) return;
      document.querySelectorAll('#tipMethods .chip').forEach(function (x) { x.classList.remove('active'); });
      c.classList.add('active');
      tipMethod = c.getAttribute('data-tm');
    });
    $('tipChips').addEventListener('click', function (ev) {
      var c = ev.target.closest('.chip'); if (!c) return;
      post('set_tip', { value: c.getAttribute('data-tip'), method: tipMethod })
        .then(function (s) { close('mPad'); render(s); });
    });
  });

  $('padOk').addEventListener('click', function () {
    var v = padValue();
    if (padMode === 'custom') {
      var nameEl = $('padName');
      var cname = (nameEl && nameEl.value) || 'Custom item';
      // Same two gates a service goes through: somebody owns the work, and a
      // hand-typed price is a manager's call.
      chooseTech('Who is doing ' + cname + '?', lastTech)
        .then(function (techId) {
          lastTech = techId;
          return withManagerApproval(function () {
            return post('add_custom', { name: cname, price: v, technician_id: techId });
          });
        })
        .then(function (s) { close('mPad'); render(s); })
        .catch(function () {});
    } else if (padMode === 'discount_amt') {
      withManagerApproval(function () { return post('set_discount', { type: 'amount', value: v }); })
        .then(function (s) { close('mPad'); render(s); }).catch(function () {});
    } else if (padMode === 'discount_pct') {
      withManagerApproval(function () { return post('set_discount', { type: 'percent', value: v * 100 }); })
        .then(function (s) { close('mPad'); render(s); }).catch(function () {});
    } else if (padMode === 'discount_off') {
      post('set_discount', { type: 'amount', value: 0 }).then(function (s) { close('mPad'); render(s); });
    } else if (padMode === 'tip') {
      post('set_tip', { value: v, method: tipMethod }).then(function (s) { close('mPad'); render(s); });
    }
  });

  /* Customer / technician */
  $('btnCustomer').addEventListener('click', function () {
    if (state) {
      $('cName').value = state.meta.customer_name || '';
      $('cPhone').value = state.meta.customer_phone || '';
      $('cTech').value = state.meta.technician_id || '';
      $('cNote').value = state.meta.note || '';
    }
    open('mCustomer');
  });
  $('cSave').addEventListener('click', function () {
    post('set_meta', {
      customer_name: $('cName').value, customer_phone: $('cPhone').value,
      technician_id: $('cTech').value, note: $('cNote').value
    }).then(function (s) { close('mCustomer'); render(s); });
  });

  $('btnClear').addEventListener('click', function () {
    if (state && state.count && !confirm('Clear this ticket?')) return;
    post('clear', {}).then(render);
  });

  /* Hold / resume — parked locally on this tablet. */
  function paintHold() {
    var raw = localStorage.getItem(HOLD_KEY);
    var btn = $('btnHold');
    if (raw && (!state || !state.count)) {
      btn.textContent = '▶ Resume held';
      btn.dataset.mode = 'resume';
    } else {
      btn.textContent = '⏸ Hold';
      delete btn.dataset.mode;
    }
  }
  $('btnHold').addEventListener('click', function () {
    if (this.dataset.mode !== 'resume') {          // park the current ticket
      if (!state || !state.count) return toast('Nothing to hold.');
      localStorage.setItem(HOLD_KEY, JSON.stringify(state));
      return post('clear', {}).then(function (s) { render(s); paintHold(); toast('Ticket held.'); });
    }
    var held = JSON.parse(localStorage.getItem(HOLD_KEY) || 'null');
    if (!held) return;
    localStorage.removeItem(HOLD_KEY);
    var chain = post('clear', {});
    held.lines.forEach(function (l) {
      chain = chain.then(function () {
        if (l.type === 'custom') return post('add_custom', { name: l.name, price: l.price });
        // Resuming has to bring the technician back with the line, or the
        // ticket returns unassigned and the work goes to nobody.
        var args = { id: l.ref_id || l.key.split(':')[1] };
        if (l.type !== 'product') args.technician_id = l.technician_id || '';
        return post(l.type === 'product' ? 'add_product' : 'add_service', args)
          .then(function () { return l.qty > 1 ? post('set_qty', { key: l.key, qty: l.qty }) : null; });
      });
    });
    chain.then(function () {
      return post('set_meta', {
        customer_name: held.meta.customer_name, customer_phone: held.meta.customer_phone,
        technician_id: held.meta.technician_id || '', note: held.meta.note
      });
    }).then(function () {
      return post('set_discount', { type: held.meta.discount_type, value: held.meta.discount_value });
    }).then(function () { return post('set_tip', { value: held.totals.tip, method: held.meta.tip_method || 'card' }); })
      .then(function (s) { render(s); paintHold(); toast('Ticket resumed.'); });
  });

  /* ── Clients ──────────────────────────────────────────── */
  var searchTimer = null;
  $('btnFindClient').addEventListener('click', function () {
    $('clientSearch').value = '';
    $('clientResults').innerHTML = '';
    $('newClientName').value = state ? (state.meta.customer_name || '') : '';
    $('newClientPhone').value = state ? (state.meta.customer_phone || '') : '';
    open('mClient');
    setTimeout(function () { $('clientSearch').focus(); }, 100);
  });

  $('clientSearch').addEventListener('input', function () {
    clearTimeout(searchTimer);
    var term = this.value.trim();
    if (term.length < 2) { $('clientResults').innerHTML = ''; return; }
    searchTimer = setTimeout(function () {
      post('find_client', { term: term }).then(function (r) {
        if (!r.results.length) {
          $('clientResults').innerHTML = '<div class="empty" style="padding:20px">No match — create them below.</div>';
          return;
        }
        $('clientResults').innerHTML = r.results.map(function (c) {
          return '<button class="btn btn-light" type="button" data-client="' + c.id + '" ' +
            'style="width:100%;justify-content:space-between;margin-bottom:8px;min-height:56px">' +
            '<span>' + c.full_name + '<br><small style="font-weight:500;opacity:.7">' + c.phone + '</small></span>' +
            '<span>⭐ ' + c.points + '</span></button>';
        }).join('');
      }).catch(function () {});
    }, 250);
  });

  $('clientResults').addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-client]');
    if (!btn) return;
    post('attach_client', { id: btn.getAttribute('data-client') })
      .then(function (s) { close('mClient'); render(s); toast('Client attached.'); });
  });

  $('newClientGo').addEventListener('click', function () {
    var name = $('newClientName').value.trim(), phone = $('newClientPhone').value.trim();
    if (!name) return toast('Enter a name.');
    if (!phone) return toast('A phone number is required to create a client.');
    post('new_client', { name: name, phone: phone })
      .then(function (s) { close('mClient'); render(s); toast('Client created.'); })
      .catch(function () {});
  });

  $('btnDetachClient').addEventListener('click', function () {
    post('detach_client', {}).then(render);
  });

  /* ── Gift cards ───────────────────────────────────────── */
  $('btnGift').addEventListener('click', function () {
    $('giftCode').value = '';
    $('giftAmount').value = '';
    $('giftRecipient').value = '';
    paintAppliedCards();
    open('mGift');
  });

  function paintAppliedCards() {
    var cards = state ? state.meta.gift_cards : [];
    $('giftApplied').innerHTML = !cards.length ? '' : cards.map(function (g) {
      return '<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;' +
        'padding:10px 12px;background:#f2f9f4;border-radius:10px;margin-bottom:8px;font-weight:700">' +
        '<span>' + g.code + '</span><span>−' + fmt(g.amount) + '</span>' +
        '<button class="btn btn-light btn-sm" type="button" data-drop="' + g.id + '">Remove</button></div>';
    }).join('');
  }

  $('giftApplied').addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-drop]');
    if (!b) return;
    post('remove_giftcard', { id: b.getAttribute('data-drop') })
      .then(function (s) { render(s); paintAppliedCards(); });
  });

  $('giftApply').addEventListener('click', function () {
    var code = $('giftCode').value.trim();
    if (!code) return toast('Enter the card code.');
    post('apply_giftcard', { code: code }).then(function (s) {
      render(s);
      paintAppliedCards();
      $('giftCode').value = '';
      toast('Applied ' + fmt(s.applied) + ' from the card.');
    }).catch(function () {});
  });

  $('giftPresets').addEventListener('click', function (ev) {
    var c = ev.target.closest('.chip');
    if (c) $('giftAmount').value = c.getAttribute('data-amt');
  });

  $('giftSell').addEventListener('click', function () {
    var amt = parseFloat($('giftAmount').value || '0');
    if (!(amt > 0)) return toast('Enter the gift card amount.');
    post('add_giftcard', { amount: amt, recipient: $('giftRecipient').value })
      .then(function (s) {
        close('mGift');
        render(s);
        toast('Gift card added — the code prints on the receipt.');
      }).catch(function () {});
  });

  /* ── Points ───────────────────────────────────────────── */
  $('btnPoints').addEventListener('click', function () {
    var cl = state && state.meta.client;
    if (!cl) return toast('Attach a client first.');
    var have = cl.points;
    $('pointsInfo').textContent = cl.name + ' has ' + have + ' points, worth ' + fmt(cl.points_value) +
      '. Minimum redemption is ' + POS.minRedeem + '.';
    $('pointsInput').value = state.meta.points_redeem || '';
    $('pointsInput').max = have;
    // Offer the whole balance and a couple of round steps below it.
    var opts = [have, Math.floor(have / 100) * 100, Math.floor(have / 500) * 500]
      .filter(function (v, i, a) { return v >= POS.minRedeem && a.indexOf(v) === i; });
    $('pointsChips').innerHTML = opts.map(function (v) {
      return '<button class="chip" type="button" data-pts="' + v + '">' + v + ' pts</button>';
    }).join('');
    open('mPoints');
  });

  $('pointsChips').addEventListener('click', function (ev) {
    var c = ev.target.closest('.chip');
    if (c) $('pointsInput').value = c.getAttribute('data-pts');
  });

  $('pointsGo').addEventListener('click', function () {
    post('set_points', { points: parseInt($('pointsInput').value || '0', 10) })
      .then(function (s) {
        close('mPoints');
        render(s);
        if (s.meta.points_redeem) toast('Redeemed ' + s.meta.points_redeem + ' points.');
      }).catch(function () {});
  });

  $('pointsNone').addEventListener('click', function () {
    post('set_points', { points: 0 }).then(function (s) { close('mPoints'); render(s); });
  });

  /* ── Payment ──────────────────────────────────────────── */
  var payMethod = 'cash';
  $('payMethods').addEventListener('click', function (ev) {
    var c = ev.target.closest('.chip'); if (!c) return;
    document.querySelectorAll('#payMethods .chip').forEach(function (x) { x.classList.remove('active'); });
    c.classList.add('active');
    payMethod = c.getAttribute('data-m');
    if (payMethod !== 'cash') $('payAmount').value = state.totals.due.toFixed(2);
  });

  $('btnPay').addEventListener('click', function () {
    if (!state || !state.count) return;
    var due = state.totals.due;
    if (due <= 0) {   // gift cards and/or points already cover it
      $('payGo').disabled = true;
      post('checkout', { payments: JSON.stringify([{ method: 'other', amount: 0, reference: 'covered' }]) })
        .then(finishSale).catch(function () { $('payGo').disabled = false; });
      return;
    }
    $('payDue').textContent = 'Due ' + fmt(due);
    $('payAmount').value = due.toFixed(2);
    $('payRef').value = '';
    $('payMsg').innerHTML = '';
    // Quick cash buttons: exact, then the next few round notes up from the total.
    var rounds = [due, Math.ceil(due / 5) * 5, Math.ceil(due / 10) * 10, Math.ceil(due / 20) * 20, Math.ceil(due / 50) * 50];
    var seen = {}, html = '';
    rounds.forEach(function (v, i) {
      v = Math.round(v * 100) / 100;
      if (seen[v]) return;
      seen[v] = 1;
      html += '<button class="chip" type="button" data-cash="' + v.toFixed(2) + '">' + (i === 0 ? 'Exact ' : '') + fmt(v) + '</button>';
    });
    $('quickCash').innerHTML = html;
    open('mPay');
  });

  $('quickCash').addEventListener('click', function (ev) {
    var c = ev.target.closest('.chip'); if (!c) return;
    $('payAmount').value = c.getAttribute('data-cash');
  });

  function finishSale(r) {
    $('payGo').disabled = false;
    close('mPay');
    $('doneReceipt').href = r.receipt_url;
    open('mDone');
    localStorage.removeItem(HOLD_KEY);
  }

  $('payGo').addEventListener('click', function () {
    var amt = parseFloat($('payAmount').value || '0');
    if (!(amt > 0)) return toast('Enter the amount taken.');
    var due = state.totals.due;
    if (amt + 0.001 < due) {
      $('payMsg').innerHTML = '<div class="alert alert-err">That is less than the ' + fmt(due) +
        ' still due. For a split, take one method here and charge the rest on a second ticket.</div>';
      return;
    }
    $('payGo').disabled = true;
    post('checkout', { payments: JSON.stringify([{ method: payMethod, amount: amt, reference: $('payRef').value }]) })
      .then(function (r) {
        var change = payMethod === 'cash' ? Math.max(0, amt - due) : 0;
        $('doneChange').textContent = change > 0 ? 'Change ' + fmt(change) : 'Paid in full';
        finishSale(r);
      })
      .catch(function () { $('payGo').disabled = false; });
  });

  $('doneNew').addEventListener('click', function () {
    close('mDone');
    post('state', {}).then(function (s) { render(s); paintHold(); });
  });

  /* ── Boot ─────────────────────────────────────────────── */
  applyFilter();
  // ?checkin= / ?client= deep links from the queue board and client pages.
  var boot = post('state', {});
  if (POS.preload) {
    var bits = POS.preload.split(':');
    boot = boot.then(function () { return post(bits[0], { id: bits[1] }); })
               .then(function (s) {
                 history.replaceState({}, '', POS.base + '/pos/');
                 return s;
               })
               .catch(function () { return post('state', {}); });
  }
  boot.then(function (s) { render(s); paintHold(); });
})();
