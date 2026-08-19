/* ============================================================
   Diamond Nail & Spa — Public Booking JS
   BASE_PATH injected by index.php  e.g. /diamond-nail-spa
   ============================================================ */
'use strict';

const BP = window.BASE_PATH || '';

// ── Service images — swap URLs here anytime ─────────────────
const SERVICE_IMAGES = {
  'classic manicure': 'https://images.unsplash.com/photo-1604902396830-aca29e19b067?q=80&w=800&auto=format&fit=crop',
  'gel manicure':     'https://images.unsplash.com/photo-1632345031435-8727f6897d53?q=80&w=800&auto=format&fit=crop',
  'classic pedicure': 'https://images.unsplash.com/photo-1519014816548-bf5fe059798b?q=80&w=800&auto=format&fit=crop',
  'gel pedicure':     'https://images.unsplash.com/photo-1607779097040-26e80aa78e66?q=80&w=800&auto=format&fit=crop',
  'nail art design':  'https://images.unsplash.com/photo-1607602132700-068258431c6e?q=80&w=800&auto=format&fit=crop',
  'acrylic full set': 'https://images.unsplash.com/photo-1610992015734-5933e1aab1c5?q=80&w=800&auto=format&fit=crop',
};
const FALLBACK_IMG = 'https://images.unsplash.com/photo-1632344042963-eb223dd6f8e7?q=80&w=800&auto=format&fit=crop';
function svcImg(name) { return SERVICE_IMAGES[name.trim().toLowerCase()] || FALLBACK_IMG; }

// ── State ────────────────────────────────────────────────────
const state = {
  services: [], service: null,
  technicians: [], technician: null,
  date: null, slot: null,
};

// ── Helpers ──────────────────────────────────────────────────
const qs  = id  => document.getElementById(id);
const qsa = sel => [...document.querySelectorAll(sel)];

function nextDays(n) {
  const days = []; const today = new Date(); today.setHours(0,0,0,0);
  for (let i = 0; i < n; i++) { const d = new Date(today); d.setDate(d.getDate()+i); days.push(d); }
  return days;
}
function padDate(d) {
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}
function esc(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function setStepUI(n) {
  qsa('.book-step').forEach(el => el.style.display = 'none');
  const s = qs('step'+n); if (s) s.style.display = 'block';
  qsa('.step[data-s]').forEach(el => {
    const sn = parseInt(el.dataset.s);
    el.classList.remove('active','done');
    const numEl = el.querySelector('.step-n');
    if (sn === n) { el.classList.add('active'); if(numEl) numEl.textContent = sn; }
    else if (sn < n) { el.classList.add('done'); if(numEl) numEl.textContent = '✓'; }
    else { if(numEl) numEl.textContent = sn; }
  });
  qs('bookingCard')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ── Step 1: Load services ────────────────────────────────────
async function initServices() {
  try {
    const res  = await fetch(`${BP}/api/services.php`);
    const json = await res.json();
    state.services = json.data || [];

    // Public services section cards
    const grid = qs('servicesGrid');
    if (grid) {
      grid.innerHTML = state.services.length === 0
        ? '<p style="color:rgba(58,42,36,.5)">Services coming soon.</p>'
        : state.services.map(s => `
          <div class="svc-card">
            <div class="svc-img"><img src="${svcImg(s.name)}" alt="${esc(s.name)}" loading="lazy"></div>
            <div class="svc-body">
              <h3>${esc(s.name)}</h3>
              <p>${esc(s.description||'')}</p>
              <div class="svc-meta">
                <span class="svc-dur">⏱ ${s.duration_minutes} min</span>
                <span class="svc-price">$${parseFloat(s.price).toFixed(0)}</span>
              </div>
              <button class="svc-btn" onclick="selectServiceAndScroll(${s.id})">
                Select &amp; schedule <span>→</span>
              </button>
            </div>
          </div>`).join('');
    }

    // Booking step 1 choices
    const choices = qs('step1Services');
    if (choices) {
      choices.innerHTML = state.services.map(s => `
        <button class="svc-choice" data-id="${s.id}" onclick="selectService(${s.id})">
          <div class="svc-choice-name">${esc(s.name)}</div>
          <div class="svc-choice-meta">⏱ ${s.duration_minutes} min &nbsp;·&nbsp; $${parseFloat(s.price).toFixed(0)}</div>
        </button>`).join('');
    }
  } catch(e) {
    const grid = qs('servicesGrid');
    if (grid) grid.innerHTML = '<p style="color:#DC2626">Could not load services. Is your server running?</p>';
  }
}

function selectServiceAndScroll(id) {
  selectService(id);
  setTimeout(() => qs('booking')?.scrollIntoView({ behavior:'smooth', block:'start' }), 100);
}

function selectService(id) {
  state.service = state.services.find(s => s.id == id) || null;
  if (!state.service) return;
  qsa('.svc-choice').forEach(b => b.classList.toggle('selected', parseInt(b.dataset.id) === id));
  const lbl = qs('selSvcLabel');
  if (lbl) lbl.innerHTML = `Service: <strong>${esc(state.service.name)}</strong> &nbsp;·&nbsp; $${parseFloat(state.service.price).toFixed(0)} &nbsp;·&nbsp; ${state.service.duration_minutes} min`;
  state.slot = null; state.date = null;
  renderDateStrip();
  loadTechnicians();
  setStepUI(2);
}

// ── Step 2: Date ─────────────────────────────────────────────
function renderDateStrip() {
  const strip = qs('dateStrip'); if (!strip) return;
  strip.innerHTML = nextDays(21).map(d => {
    const iso = padDate(d);
    return `<button class="date-btn" data-iso="${iso}" onclick="selectDate('${iso}',this)">
      <div class="date-day">${d.toLocaleDateString('en-US',{weekday:'short'})}</div>
      <div class="date-num">${d.getDate()}</div>
    </button>`;
  }).join('');
}

function selectDate(iso, btn) {
  qsa('.date-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const [y,m,d] = iso.split('-').map(Number);
  state.date = new Date(y, m-1, d, 12, 0, 0);
  state.slot = null;
  qs('step2Next').disabled = true;
  qsa('.slot-btn').forEach(b => b.classList.remove('active'));
  loadSlots(iso);
}

async function loadSlots(iso) {
  const grid = qs('slotsGrid'); const sec = qs('slotsSection');
  if (!grid || !sec || !state.service) return;
  sec.style.display = 'block';
  grid.innerHTML = '<div class="slot-loading">⏳ Checking availability…</div>';
  try {
    let url = `${BP}/api/slots.php?date=${iso}&service_id=${state.service.id}`;
    if (state.technician) url += `&technician_id=${state.technician}`;
    const json = await (await fetch(url)).json();
    const slots = json.data || [];
    grid.innerHTML = slots.length === 0
      ? '<p style="color:rgba(58,42,36,.5);font-size:13px">No available times on this date. Please try another day.</p>'
      : slots.map(sl => `<button class="slot-btn" onclick="selectSlot('${sl.start}','${sl.end}','${sl.label}',this)">${sl.label}</button>`).join('');
  } catch(e) {
    grid.innerHTML = '<p style="color:#DC2626;font-size:13px">Error loading times. Please refresh.</p>';
  }
}

function selectSlot(start, end, label, btn) {
  state.slot = { start, end, label };
  qsa('.slot-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  qs('step2Next').disabled = false;
}

// ── Technicians ───────────────────────────────────────────────
async function loadTechnicians() {
  const sec = qs('techSection'); const wrap = qs('techChoices');
  if (!sec || !wrap || !state.service) return;
  try {
    const json = await (await fetch(`${BP}/api/technicians.php?service_id=${state.service.id}`)).json();
    state.technicians = json.data || [];
    if (!state.technicians.length) { sec.style.display = 'none'; return; }
    sec.style.display = 'block';
    wrap.innerHTML = `<button class="tech-btn active" onclick="pickTech('',this)">Any available</button>
      ${state.technicians.map(t => `<button class="tech-btn" data-id="${t.id}" onclick="pickTech(${t.id},this)">${esc(t.name)}</button>`).join('')}`;
  } catch(e) { sec.style.display = 'none'; }
}

function pickTech(id, btn) {
  state.technician = id || null;
  qsa('.tech-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  if (state.date) loadSlots(padDate(state.date));
}

// ── Step 3: Summary & submit ─────────────────────────────────
function goStep(n) {
  if (n === 3) renderSummary();
  setStepUI(n);
}

function renderSummary() {
  const el = qs('apptSummary');
  if (!el || !state.service || !state.date || !state.slot) return;
  const tech = state.technician
    ? (state.technicians.find(t => t.id == state.technician)?.name || 'Selected technician')
    : 'Any available';
  el.innerHTML = `
    <h4>Appointment summary</h4>
    <div class="sum-row"><span>Service</span><span>${esc(state.service.name)}</span></div>
    <div class="sum-row"><span>Technician</span><span>${esc(tech)}</span></div>
    <div class="sum-row"><span>Date</span><span>${state.date.toLocaleDateString('en-US',{weekday:'short',month:'short',day:'numeric'})}</span></div>
    <div class="sum-row"><span>Time</span><span>${state.slot.label}</span></div>
    <div class="sum-row"><span>Duration</span><span>${state.service.duration_minutes} min</span></div>
    <div class="sum-row" style="margin-top:8px;padding-top:10px;border-top:2px solid var(--taupe)">
      <span style="font-weight:700">Total</span>
      <span class="sum-price">$${parseFloat(state.service.price).toFixed(0)}</span>
    </div>`;
}

async function submitBooking() {
  const name  = qs('bName')?.value.trim();
  const email = qs('bEmail')?.value.trim();
  const phone = qs('bPhone')?.value.trim();
  const notes = qs('bNotes')?.value.trim();
  const errEl = qs('bookError');

  if (!name || !email || !phone) {
    if (errEl) { errEl.textContent = 'Please fill in your name, email and phone number.'; errEl.style.display = 'block'; }
    return;
  }
  if (errEl) errEl.style.display = 'none';

  const btn = qs('confirmBtn');
  if (btn) { btn.disabled = true; btn.textContent = 'Submitting…'; }

  try {
    const res  = await fetch(`${BP}/api/book.php`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        full_name:        name,
        email:            email,
        phone:            phone,
        service_id:       state.service.id,
        technician_id:    state.technician || '',
        appointment_date: padDate(state.date),
        start_time:       state.slot.start,
        notes:            notes || '',
      }),
    });
    const json = await res.json();

    if (!json.success) {
      if (errEl) { errEl.textContent = json.error || 'Booking failed. Please try again.'; errEl.style.display = 'block'; }
      if (btn)   { btn.disabled = false; btn.textContent = 'Confirm appointment'; }
      return;
    }

    // Show success
    const tech = state.technician
      ? (state.technicians.find(t => t.id == state.technician)?.name || 'Your technician')
      : 'Any available technician';
    const sd = qs('successDetail');
    if (sd) sd.innerHTML = `
      <div class="sum-row"><span>Service</span><span>${esc(state.service.name)}</span></div>
      <div class="sum-row"><span>Technician</span><span>${esc(tech)}</span></div>
      <div class="sum-row"><span>Date</span><span>${state.date.toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric'})}</span></div>
      <div class="sum-row"><span>Time</span><span>${state.slot.label}</span></div>
      ${json.sms_sent ? '<p style="margin-top:10px;font-size:12px;color:#059669">✅ SMS confirmation sent to your phone.</p>' : ''}`;
    setStepUI(4);

  } catch(e) {
    if (errEl) { errEl.textContent = 'Network error. Please try again.'; errEl.style.display = 'block'; }
    if (btn)   { btn.disabled = false; btn.textContent = 'Confirm appointment'; }
  }
}

function resetBooking() {
  state.service = null; state.technician = null; state.date = null; state.slot = null;
  ['bName','bEmail','bPhone','bNotes'].forEach(id => { const el = qs(id); if(el) el.value=''; });
  setStepUI(1);
}

// ── Expose globals ────────────────────────────────────────────
Object.assign(window, {
  selectService, selectServiceAndScroll, selectDate, selectSlot,
  pickTech, goStep, submitBooking, resetBooking,
  closeMobileNav: () => qs('navMobile')?.classList.remove('open'),
});

// ── Boot ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  initServices();
  setStepUI(1);
  qs('step2Next')?.addEventListener('click', () => goStep(3));

  window.addEventListener('scroll', () => {
    qs('navbar')?.classList.toggle('scrolled', window.scrollY > 30);
  });
  qs('navToggle')?.addEventListener('click', () => {
    qs('navMobile')?.classList.toggle('open');
  });
});
