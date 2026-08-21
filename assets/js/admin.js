// ============================================================
//  DIAMOND NAIL & SPA — Admin Dashboard JS
//  BASE_PATH injected by admin/index.php e.g. /diamond-nail-spa
// ============================================================
'use strict';

const BP = window.BASE_PATH || '';

const $ = (s, ctx = document) => ctx.querySelector(s);
const $$ = (s, ctx = document) => [...ctx.querySelectorAll(s)];

// ── State ────────────────────────────────────────────────────
let currentPage = 'overview';
let calendarInstance = null;

// ── Init ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // Nav
  $$('.nav-item').forEach(a => {
    a.addEventListener('click', e => {
      e.preventDefault();
      const page = a.dataset.page;
      $$('.nav-item').forEach(n => n.classList.remove('active'));
      a.classList.add('active');
      $('#pageTitle').textContent = a.textContent.trim();
      loadPage(page);
    });
  });

  // Mobile menu
  $('#menuToggle').addEventListener('click', () => $('#sidebar').classList.toggle('open'));

  // Modal close
  $('#modalClose').addEventListener('click', closeModal);
  $('#modalOverlay').addEventListener('click', e => { if (e.target === $('#modalOverlay')) closeModal(); });

  loadPage('overview');
});

async function api(url, opts = {}) {
  try {
    const r = await fetch(url, {
      headers: { 'Content-Type': 'application/json' },
      ...opts,
    });
    return await r.json();
  } catch (e) {
    return { success: false, error: e.message };
  }
}

function setLoading() {
  $('#mainContent').innerHTML = '<div class="loader-wrap"><div class="spinner"></div></div>';
}

function openModal(html) {
  $('#modalBody').innerHTML = html;
  $('#modalOverlay').style.display = 'flex';
}
function closeModal() {
  $('#modalOverlay').style.display = 'none';
  $('#modalBody').innerHTML = '';
}

// ── Router ───────────────────────────────────────────────────
function loadPage(page) {
  currentPage = page;
  if (calendarInstance) { calendarInstance.destroy(); calendarInstance = null; }
  const pages = {
    overview:     pageOverview,
    calendar:     pageCalendar,
    appointments: pageAppointments,
    technicians:  pageTechnicians,
    services:     pageServices,
    hours:        pageHours,
    blocked:      pageBlocked,
    sms:          pageSMS,
    settings:     pageSettings,
  };
  (pages[page] || pageOverview)();
}

// ─────────────────────────────────────────────────────────────
//  OVERVIEW
// ─────────────────────────────────────────────────────────────
async function pageOverview() {
  setLoading();
  const res = await api(BP + '/api/admin_stats.php');
  if (!res.success) { $('#mainContent').innerHTML = '<p>Failed to load.</p>'; return; }
  const d = res.data;
  const upcoming = (d.upcoming || []).map(a => `
    <tr>
      <td><strong>${esc(a.full_name)}</strong><br><small class="text-muted">${esc(a.phone)}</small></td>
      <td>${esc(a.service_name)}</td>
      <td>${fmtDate(a.appointment_date)}<br><small>${fmtTime(a.start_time)}</small></td>
      <td>${a.technician_name ? esc(a.technician_name) : '<span class="text-muted">Any</span>'}</td>
      <td><span class="badge badge-${a.status}">${a.status}</span></td>
    </tr>
  `).join('') || '<tr><td colspan="5" class="empty-state">No upcoming appointments.</td></tr>';

  $('#mainContent').innerHTML = `
    <div class="stat-grid">
      <div class="stat-card"><div class="stat-accent"></div><div class="stat-value">${d.today_appointments}</div><div class="stat-label">Today's appointments</div></div>
      <div class="stat-card"><div class="stat-accent"></div><div class="stat-value">${d.pending}</div><div class="stat-label">Pending</div></div>
      <div class="stat-card"><div class="stat-accent"></div><div class="stat-value">${d.total_appointments}</div><div class="stat-label">Total appointments</div></div>
      <div class="stat-card"><div class="stat-accent"></div><div class="stat-value">$${Number(d.month_revenue).toFixed(0)}</div><div class="stat-label">Revenue this month</div></div>
      <div class="stat-card"><div class="stat-accent"></div><div class="stat-value">${d.active_services}</div><div class="stat-label">Active services</div></div>
      <div class="stat-card"><div class="stat-accent"></div><div class="stat-value">${d.active_technicians}</div><div class="stat-label">Technicians</div></div>
    </div>
    <div class="card">
      <div class="section-header">
        <h3 class="section-title">Upcoming appointments</h3>
        <button class="btn btn-secondary btn-sm" onclick="loadPage('calendar')">📅 Calendar view</button>
      </div>
      <div class="table-wrap">
        <table><thead><tr><th>Client</th><th>Service</th><th>Date & time</th><th>Technician</th><th>Status</th></tr></thead>
        <tbody>${upcoming}</tbody></table>
      </div>
    </div>`;
}

// ─────────────────────────────────────────────────────────────
//  CALENDAR
// ─────────────────────────────────────────────────────────────
function pageCalendar() {
  $('#mainContent').innerHTML = `
    <div class="card" style="height:calc(100vh - 140px);min-height:430px;display:flex;flex-direction:column">
      <div id="cal" style="flex:1;min-height:0"></div>
    </div>`;
  calendarInstance = new FullCalendar.Calendar($('#cal'), {
    // Open on the whole month so the owner sees the shape of the week
    // at a glance; the toolbar switches to week or day for detail.
    initialView: 'dayGridMonth',
    // Fill the card rather than draw at a natural 700px and spill out of it.
    // On the salon's 540-tall tablet the card is 400px, so a month grid drawn
    // at its own size hung 300px below the white box it was supposed to be in.
    height: '100%',
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
    buttonText: { today: 'Today', month: 'Month', week: 'Week', day: 'Day', list: 'List' },
    views: {
      dayGridMonth: { dayMaxEventRows: 4 },   // "+2 more" rather than a stretched cell
      listWeek:     { noEventsContent: 'No bookings this week' },
    },
    firstDay: 1,                 // salons think in Mon-Sun weeks
    // The red line is the current time. FullCalendar only draws it on the
    // time grids, so it shows in Week and Day, not in the Month squares.
    nowIndicator: true,
    scrollTime: '09:00:00',      // week/day open at the start of trade, not midnight
    // Drag a booking to move it. The length is kept by the server, so a
    // 45-minute service stays 45 minutes wherever it lands.
    editable: true,
    eventStartEditable: true,
    eventDurationEditable: false,
    eventDrop: onEventMoved,
    slotMinTime: '08:00:00',
    slotMaxTime: '21:00:00',
    allDaySlot: true,
    expandRows: true,
    stickyHeaderDates: true,
    height: '100%',
    events: async (info, successCb, failureCb) => {
      const res = await api(`${BP}/api/calendar.php?start=${info.startStr.substr(0,10)}&end=${info.endStr.substr(0,10)}`);
      if (Array.isArray(res)) successCb(res);
      else successCb([]);
    },
    eventClick(info) {
      const e = info.event;
      const p = e.extendedProps;
      const day = e.startStr.substr(0, 10);
      openModal(`
        <h3>${esc(e.title)}</h3>
        <p><strong>When:</strong> ${day} &nbsp; ${e.startStr?.substr(11,5)} – ${e.endStr?.substr(11,5)}</p>
        <p><strong>Status:</strong> <span class="badge badge-${p.status}">${p.status}</span></p>
        <label class="form-group"><span>Change status</span>
          <select id="calStatus">
            ${['pending','confirmed','completed','cancelled'].map(st =>
              `<option value="${st}" ${st === p.status ? 'selected' : ''}>${st}</option>`).join('')}
          </select>
        </label>
        <p class="muted" style="font-size:13px">Drag the booking on the calendar to move it to another time.</p>
        <div class="form-actions">
          <button class="btn btn-secondary" onclick="closeModal()">Close</button>
          <button class="btn btn-primary" onclick="saveCalStatus(${e.id})">Save status</button>
        </div>`);
    },
  });
  calendarInstance.render();
}

/** Persists a drag, and puts the booking back where it was if the save fails. */
async function onEventMoved(info) {
  const e = info.event;
  const res = await api(BP + '/api/appointments.php', {
    method: 'PATCH',
    body: JSON.stringify({
      action: 'move',
      id: Number(e.id),
      date: e.startStr.substr(0, 10),
      start: e.startStr.substr(11, 8) || '09:00:00',
    }),
  });
  if (!res || !res.success) {
    info.revert();
    alert((res && res.error) || 'Could not move that booking.');
  }
}

/** Status change from the calendar's own popup. */
async function saveCalStatus(id) {
  const sel = $('#calStatus');
  if (!sel) return;
  const res = await api(BP + '/api/appointments.php', {
    method: 'PATCH',
    body: JSON.stringify({ id: Number(id), status: sel.value }),
  });
  if (res && res.success) {
    closeModal();
    if (calendarInstance) calendarInstance.refetchEvents();
  } else {
    alert((res && res.error) || 'Could not update that booking.');
  }
}

// ─────────────────────────────────────────────────────────────
//  APPOINTMENTS
// ─────────────────────────────────────────────────────────────
async function pageAppointments(statusFilter = '', search = '', page = 1) {
  setLoading();
  let url = `${BP}/api/appointments.php?page=${page}`;
  if (statusFilter) url += `&status=${statusFilter}`;
  if (search) url += `&search=${encodeURIComponent(search)}`;
  const res = await api(url);
  if (!res.success) { $('#mainContent').innerHTML = '<p>Error loading appointments.</p>'; return; }

  const rows = res.data.map(a => `
    <tr>
      <td><strong>${esc(a.full_name)}</strong><br><small>${esc(a.email)}</small></td>
      <td>${esc(a.service_name)}</td>
      <td>${a.technician_name ? esc(a.technician_name) : '—'}</td>
      <td>${fmtDate(a.appointment_date)}<br><small>${fmtTime(a.start_time)} – ${fmtTime(a.end_time)}</small></td>
      <td>${esc(a.phone)}</td>
      <td><span class="badge badge-${a.status}">${a.status}</span></td>
      <td>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <select class="status-select" style="padding:4px 8px;border:1.5px solid var(--taupe);border-radius:8px;font-size:12px" data-id="${a.id}" onchange="updateStatus(this)">
            ${['pending','confirmed','cancelled','completed'].map(s => `<option value="${s}"${a.status===s?' selected':''}>${s}</option>`).join('')}
          </select>
          <button class="btn btn-sm btn-secondary" onclick="sendSMSBtn(${a.id},'confirmation')">✓ Confirm</button>
          <button class="btn btn-sm btn-secondary" onclick="sendSMSBtn(${a.id},'reminder')">💬 Remind</button>
          <button class="btn btn-sm btn-secondary" onclick="apptDetail(${a.id})">Detail</button>
        </div>
      </td>
    </tr>`).join('') || '<tr><td colspan="7"><div class="empty-state"><p>No appointments found.</p></div></td></tr>';

  const pages = Math.ceil(res.total / res.limit);
  const pagination = pages > 1 ? `
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px">
      ${Array.from({length:pages},(_,i)=>i+1).map(p=>`<button class="btn btn-sm ${p===page?'btn-primary':'btn-secondary'}" onclick="pageAppointments('${statusFilter}','${search}',${p})">${p}</button>`).join('')}
    </div>` : '';

  $('#mainContent').innerHTML = `
    <div class="section-header">
      <h2 class="section-title">Appointments</h2>
    </div>
    <div class="filters">
      <select id="apptStatus" onchange="pageAppointments(this.value,$('#apptSearch').value)">
        <option value="">All statuses</option>
        ${['pending','confirmed','cancelled','completed'].map(s=>`<option value="${s}"${statusFilter===s?' selected':''}>${s}</option>`).join('')}
      </select>
      <input id="apptSearch" type="text" placeholder="Search client…" value="${esc(search)}" style="padding:8px 12px;border:1.5px solid var(--taupe);border-radius:8px;font-size:13px" onkeydown="if(event.key==='Enter')pageAppointments($('#apptStatus').value,this.value)">
      <button class="btn btn-secondary btn-sm" onclick="pageAppointments($('#apptStatus').value,$('#apptSearch').value)">Search</button>
      <span style="margin-left:auto;font-size:12px;color:rgba(58,42,36,.5)">${res.total} total</span>
    </div>
    <div class="card" style="padding:0">
      <div class="table-wrap">
        <table><thead><tr><th>Client</th><th>Service</th><th>Technician</th><th>Date & time</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>${rows}</tbody></table>
      </div>
    </div>
    ${pagination}`;
}

async function updateStatus(sel) {
  const id = sel.dataset.id;
  const status = sel.value;
  const res = await api(BP + '/api/appointments.php', { method: 'PATCH', body: JSON.stringify({ id: Number(id), status }) });
  if (!res.success) { alert('Failed to update status'); sel.value = sel.dataset.prev; }
}

async function sendSMSBtn(id, type) {
  const res = await api(BP + '/api/appointments.php?action=sms', { method: 'POST', body: JSON.stringify({ id, type }) });
  alert(res.success ? '✅ SMS sent!' : '❌ ' + (res.error || 'SMS not configured'));
}

async function apptDetail(id) {
  const res = await api(`/api/appointments.php?id=${id}`);
  // fallback: just show from table data we already have
  openModal(`<h3>Appointment #${id}</h3>
    <p>See the appointment row for details. Use the status dropdown to update status, or use the 💬 Remind button to send an SMS reminder.</p>
    <div class="form-actions"><button class="btn btn-secondary" onclick="closeModal()">Close</button></div>`);
}

// ─────────────────────────────────────────────────────────────
//  TECHNICIANS
// ─────────────────────────────────────────────────────────────
async function pageTechnicians() {
  setLoading();
  const [techRes, svcRes] = await Promise.all([
    api(BP + '/api/admin_technicians.php'),
    api(BP + '/api/services.php'),
  ]);
  const techs    = techRes.data || [];
  const services = svcRes.data  || [];

  const rows = techs.map(t => `
    <tr>
      <td><strong>${esc(t.name)}</strong></td>
      <td>${esc(t.phone || '—')}</td>
      <td>${esc(t.email || '—')}</td>
      <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(t.specialties || '—')}</td>
      <td><span class="badge badge-${t.is_active?'active':'inactive'}">${t.is_active?'Active':'Inactive'}</span></td>
      <td>
        <button class="btn btn-sm btn-secondary" onclick='openTechModal(${JSON.stringify(t)},${JSON.stringify(services)})'>Edit</button>
        <button class="btn btn-sm btn-danger" onclick="toggleTech(${t.id},${t.is_active?0:1})">${t.is_active?'Deactivate':'Activate'}</button>
      </td>
    </tr>`).join('') || '<tr><td colspan="6"><div class="empty-state"><p>No technicians yet.</p></div></td></tr>';

  $('#mainContent').innerHTML = `
    <div class="section-header">
      <h2 class="section-title">Technicians</h2>
      <button class="btn btn-primary" onclick='openTechModal(null,${JSON.stringify(services)})'>+ Add technician</button>
    </div>
    <div class="card" style="padding:0">
      <div class="table-wrap">
        <table><thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Specialties</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>${rows}</tbody></table>
      </div>
    </div>`;
}

function openTechModal(tech, services) {
  const isEdit = !!tech;
  const sid = tech?.service_ids || [];
  openModal(`
    <h3>${isEdit ? 'Edit technician' : 'Add technician'}</h3>
    <div class="field"><label>Name *</label><input id="tName" value="${esc(tech?.name||'')}"></div>
    <div class="form-row">
      <div class="field"><label>Phone</label><input id="tPhone" value="${esc(tech?.phone||'')}"></div>
      <div class="field"><label>Email</label><input id="tEmail" type="email" value="${esc(tech?.email||'')}"></div>
    </div>
    <div class="field"><label>Bio</label><textarea id="tBio">${esc(tech?.bio||'')}</textarea></div>
    <div class="field"><label>Photo URL</label><input id="tPhoto" value="${esc(tech?.photo_url||'')}"></div>
    <div class="field"><label>Specialties (comma separated)</label><input id="tSpec" value="${esc(tech?.specialties||'')}"></div>
    <div class="field"><label>Services offered</label>
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px">
        ${services.map(s=>`<label style="display:flex;align-items:center;gap:6px;font-size:13px">
          <input type="checkbox" value="${s.id}" ${sid.includes(String(s.id))||sid.includes(s.id)?'checked':''}> ${esc(s.name)}
        </label>`).join('')}
      </div>
    </div>
    <div class="form-actions">
      <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
      <button class="btn btn-primary" onclick="saveTech(${isEdit?tech.id:'null'})">${isEdit?'Save changes':'Add technician'}</button>
    </div>`);
}

async function saveTech(id) {
  const serviceIds = $$('#modalBody input[type=checkbox]:checked').map(c=>Number(c.value));
  const payload = {
    name:         $('#tName').value.trim(),
    phone:        $('#tPhone').value.trim(),
    email:        $('#tEmail').value.trim(),
    bio:          $('#tBio').value.trim(),
    photo_url:    $('#tPhoto').value.trim(),
    specialties:  $('#tSpec').value.trim(),
    is_active:    1,
    service_ids:  serviceIds,
  };
  if (id) payload.id = id;
  const res = await api(BP + '/api/admin_technicians.php', { method: id ? 'PUT' : 'POST', body: JSON.stringify(payload) });
  if (res.success) { closeModal(); pageTechnicians(); } else alert('Error: ' + res.error);
}

async function toggleTech(id, active) {
  await api(BP + '/api/admin_technicians.php', { method: 'PUT', body: JSON.stringify({ id, is_active: active }) });
  pageTechnicians();
}

// ─────────────────────────────────────────────────────────────
//  SERVICES
// ─────────────────────────────────────────────────────────────
async function pageServices() {
  setLoading();
  const res = await api(BP + '/api/admin_services.php');
  const svcs = res.data || [];

  const rows = svcs.map(s => `
    <tr>
      <td><strong>${esc(s.name)}</strong><br><small>${esc(s.category)}</small></td>
      <td style="max-width:220px">${esc(s.description||'—')}</td>
      <td>${s.duration_minutes} min</td>
      <td>$${Number(s.price).toFixed(2)}</td>
      <td><span class="badge badge-${s.is_active?'active':'inactive'}">${s.is_active?'Active':'Inactive'}</span></td>
      <td>
        <button class="btn btn-sm btn-secondary" onclick='openSvcModal(${JSON.stringify(s)})'>Edit</button>
        <button class="btn btn-sm ${s.is_active?'btn-danger':'btn-success'}" onclick="toggleSvc(${s.id},${s.is_active?0:1})">${s.is_active?'Deactivate':'Activate'}</button>
      </td>
    </tr>`).join('') || '<tr><td colspan="6"><div class="empty-state"><p>No services yet.</p></div></td></tr>';

  $('#mainContent').innerHTML = `
    <div class="section-header">
      <h2 class="section-title">Services</h2>
      <button class="btn btn-primary" onclick="openSvcModal(null)">+ Add service</button>
    </div>
    <div class="card" style="padding:0">
      <div class="table-wrap">
        <table><thead><tr><th>Service</th><th>Description</th><th>Duration</th><th>Price</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>${rows}</tbody></table>
      </div>
    </div>`;
}

function openSvcModal(svc) {
  const isEdit = !!svc;
  openModal(`
    <h3>${isEdit?'Edit service':'Add service'}</h3>
    <div class="field"><label>Name *</label><input id="sName" value="${esc(svc?.name||'')}"></div>
    <div class="field"><label>Description</label><textarea id="sDesc">${esc(svc?.description||'')}</textarea></div>
    <div class="form-row">
      <div class="field"><label>Duration (minutes) *</label><input id="sDur" type="number" min="5" value="${svc?.duration_minutes||45}"></div>
      <div class="field"><label>Price ($) *</label><input id="sPrice" type="number" min="0" step="0.01" value="${svc?.price||0}"></div>
    </div>
    <div class="form-row">
      <div class="field"><label>Category</label>
        <select id="sCat">
          ${['Manicure','Pedicure','Art','Acrylic','Other'].map(c=>`<option${svc?.category===c?' selected':''}>${c}</option>`).join('')}
        </select>
      </div>
      <div class="field"><label>Display order</label><input id="sOrder" type="number" value="${svc?.display_order||0}"></div>
    </div>
    <div class="field"><label>Image URL</label><input id="sImg" value="${esc(svc?.image_url||'')}"></div>
    <div class="form-actions">
      <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
      <button class="btn btn-primary" onclick="saveSvc(${isEdit?svc.id:'null'})">${isEdit?'Save changes':'Add service'}</button>
    </div>`);
}

async function saveSvc(id) {
  const payload = {
    name:             $('#sName').value.trim(),
    description:      $('#sDesc').value.trim(),
    duration_minutes: Number($('#sDur').value),
    price:            Number($('#sPrice').value),
    category:         $('#sCat').value,
    display_order:    Number($('#sOrder').value),
    image_url:        $('#sImg').value.trim(),
    is_active:        1,
  };
  if (id) payload.id = id;
  const res = await api(BP + '/api/admin_services.php', { method: id ? 'PUT' : 'POST', body: JSON.stringify(payload) });
  if (res.success) { closeModal(); pageServices(); } else alert('Error: ' + res.error);
}

async function toggleSvc(id, active) {
  await api(BP + '/api/admin_services.php', { method: 'PUT', body: JSON.stringify({ id, is_active: active, name: ' ' }) });
  // Re-fetch to not overwrite name; use a patch approach:
  await api(BP + '/api/admin_services.php', { method: 'PUT', body: JSON.stringify({ id, is_active: active }) });
  pageServices();
}

// ─────────────────────────────────────────────────────────────
//  BUSINESS HOURS
// ─────────────────────────────────────────────────────────────
const DAYS = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

async function pageHours() {
  setLoading();
  const res = await api(BP + '/api/admin_hours.php');
  const hours = res.data || [];

  const rows = hours.map(h => `
    <div class="hour-row ${h.is_open?'':'hours-row-disabled'}" id="hr-${h.weekday}">
      <label class="toggle-row" style="margin-bottom:0">
        <label class="toggle"><input type="checkbox" id="hOpen-${h.weekday}" ${h.is_open?'checked':''} onchange="toggleDayRow(${h.weekday})"><span class="toggle-slider"></span></label>
        <strong>${DAYS[h.weekday]}</strong>
      </label>
      <div class="hour-times" id="htimes-${h.weekday}" ${h.is_open?'':'style="opacity:.3;pointer-events:none"'}>
        <input type="time" id="hStart-${h.weekday}" value="${h.start_time.substr(0,5)}" style="padding:6px 8px;border:1.5px solid var(--taupe);border-radius:8px">
        <span>to</span>
        <input type="time" id="hEnd-${h.weekday}" value="${h.end_time.substr(0,5)}" style="padding:6px 8px;border:1.5px solid var(--taupe);border-radius:8px">
      </div>
    </div>`).join('');

  $('#mainContent').innerHTML = `
    <div class="section-header">
      <h2 class="section-title">Business Hours</h2>
      <button class="btn btn-primary" onclick="saveHours()">Save hours</button>
    </div>
    <div class="card">
      <div class="hours-grid">${rows}</div>
      <div class="form-actions">
        <button class="btn btn-primary" onclick="saveHours()">Save changes</button>
      </div>
    </div>`;
}

function toggleDayRow(wd) {
  const open = $(`#hOpen-${wd}`).checked;
  $(`#htimes-${wd}`).style.cssText = open ? '' : 'opacity:.3;pointer-events:none';
}

async function saveHours() {
  const rows = Array.from({length:7},(_,i) => ({
    weekday:    i,
    is_open:    $(`#hOpen-${i}`).checked ? 1 : 0,
    start_time: $(`#hStart-${i}`)?.value + ':00' || '09:00:00',
    end_time:   $(`#hEnd-${i}`)?.value   + ':00' || '18:00:00',
  }));
  const res = await api(BP + '/api/admin_hours.php', { method: 'POST', body: JSON.stringify(rows) });
  alert(res.success ? '✅ Hours saved!' : '❌ Failed to save.');
}

// ─────────────────────────────────────────────────────────────
//  BLOCKED DATES
// ─────────────────────────────────────────────────────────────
async function pageBlocked() {
  setLoading();
  const res = await api(BP + '/api/admin_blocked.php');
  const dates = res.data || [];

  const rows = dates.map(b => `
    <tr>
      <td>${fmtDate(b.blocked_date)}</td>
      <td>${esc(b.reason||'—')}</td>
      <td>${fmtDate(b.created_at?.substr(0,10))}</td>
      <td><button class="btn btn-sm btn-danger" onclick="removeBlocked(${b.id})">Remove</button></td>
    </tr>`).join('') || '<tr><td colspan="4"><div class="empty-state"><p>No blocked dates.</p></div></td></tr>';

  $('#mainContent').innerHTML = `
    <div class="section-header">
      <h2 class="section-title">Blocked Dates</h2>
    </div>
    <div class="page-row">
      <div class="card">
        <h3 style="font-family:var(--font-display);font-size:17px;margin-bottom:16px">Block a date</h3>
        <div class="field"><label>Date *</label><input type="date" id="bDate" min="${new Date().toISOString().substr(0,10)}"></div>
        <div class="field"><label>Reason (optional)</label><input id="bReason" placeholder="e.g. Public holiday"></div>
        <button class="btn btn-primary" onclick="addBlocked()">Block date</button>
      </div>
      <div>
        <div class="card" style="padding:0">
          <div class="table-wrap">
            <table><thead><tr><th>Date</th><th>Reason</th><th>Added</th><th></th></tr></thead>
            <tbody>${rows}</tbody></table>
          </div>
        </div>
      </div>
    </div>`;
}

async function addBlocked() {
  const date   = $('#bDate').value;
  const reason = $('#bReason').value.trim();
  if (!date) { alert('Please select a date.'); return; }
  const res = await api(BP + '/api/admin_blocked.php', { method: 'POST', body: JSON.stringify({ blocked_date: date, reason }) });
  if (res.success) { pageBlocked(); } else alert('Error: ' + (res.error || 'Failed'));
}

async function removeBlocked(id) {
  if (!confirm('Remove this blocked date?')) return;
  await api(`/api/admin_blocked.php?id=${id}`, { method: 'DELETE' });
  pageBlocked();
}

// ─────────────────────────────────────────────────────────────
//  SMS LOG
// ─────────────────────────────────────────────────────────────
async function pageSMS() {
  setLoading();
  // Reuse appointments endpoint logic — just show sms_log
  const res = await api(BP + '/api/admin_sms_log.php');
  const logs = res.data || [];
  const rows = logs.map(l=>`
    <tr>
      <td>${esc(l.to_number)}</td>
      <td><span class="sms-tag">${esc(l.type)}</span></td>
      <td style="max-width:300px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="${esc(l.message)}">${esc(l.message)}</td>
      <td><span class="badge badge-${l.status==='sent'?'active':'inactive'}">${esc(l.status)}</span></td>
      <td>${fmtDate(l.sent_at?.substr(0,10))} ${l.sent_at?.substr(11,5)}</td>
    </tr>`).join('') || '<tr><td colspan="5"><div class="empty-state"><p>No SMS sent yet. Configure Twilio in Settings to enable SMS.</p></div></td></tr>';

  $('#mainContent').innerHTML = `
    <div class="section-header">
      <h2 class="section-title">SMS Log</h2>
    </div>
    <div class="card" style="padding:0">
      <div class="table-wrap">
        <table><thead><tr><th>To</th><th>Type</th><th>Message</th><th>Status</th><th>Sent at</th></tr></thead>
        <tbody>${rows}</tbody></table>
      </div>
    </div>`;
}

// ─────────────────────────────────────────────────────────────
//  SETTINGS
// ─────────────────────────────────────────────────────────────
async function pageSettings() {
  setLoading();
  const res = await api(BP + '/api/admin_settings.php');
  const s = res.data || {};

  $('#mainContent').innerHTML = `
    <div class="section-header"><h2 class="section-title">Business Settings</h2></div>
    <div class="page-row">
      <div class="card">
        <h3 style="font-family:var(--font-display);font-size:17px;margin-bottom:18px">Studio information</h3>
        <div class="field"><label>Studio name</label><input id="sName" value="${esc(s.business_name||'')}"></div>
        <div class="field"><label>Phone</label><input id="sPh" value="${esc(s.business_phone||'')}"></div>
        <div class="field"><label>Email</label><input id="sEm" type="email" value="${esc(s.business_email||'')}"></div>
        <div class="field"><label>Address</label><textarea id="sAddr">${esc(s.business_address||'')}</textarea></div>
        <div class="form-row">
          <div class="field"><label>Slot interval (minutes)</label><input id="sSlot" type="number" min="5" value="${s.slot_interval_minutes||30}"></div>
          <div class="field"><label>Booking notice (hours)</label><input id="sNotice" type="number" min="0" value="${s.booking_notice_hours||2}"></div>
        </div>
        <div class="field"><label>Reminder hours before appointment</label><input id="sRemind" type="number" min="1" value="${s.reminder_hours_before||24}"></div>
        <button class="btn btn-primary" onclick="saveSettings()">Save studio settings</button>
      </div>
      <div class="card">
        <h3 style="font-family:var(--font-display);font-size:17px;margin-bottom:8px">Twilio SMS Configuration</h3>
        <p style="font-size:12px;color:rgba(58,42,36,.55);margin-bottom:16px">Get credentials at <a href="https://twilio.com/console" target="_blank" style="color:var(--rosegold)">twilio.com/console</a></p>
        <div class="field"><label>Account SID</label><input id="tSID" value="${esc(s.twilio_account_sid||'')}" placeholder="ACxxxxxxxxxxxx"></div>
        <div class="field"><label>Auth Token</label><input id="tTok" type="password" value="" autocomplete="new-password" placeholder="${s.twilio_auth_token_set ? '•••••••• stored — leave blank to keep' : 'not set'}"></div>
        <div class="field"><label>From number (E.164)</label><input id="tFrom" value="${esc(s.twilio_from_number||'')}" placeholder="+12025551234"></div>
        <div class="field"><label>SMS Sender name</label><input id="tSend" value="${esc(s.sms_sender||'DiamondNail')}"></div>
        <button class="btn btn-primary" onclick="saveSettings()">Save SMS settings</button>
        <p style="margin-top:12px;font-size:12px;color:rgba(58,42,36,.55)">💬 SMS confirmations are sent automatically on booking. Reminders can be triggered manually from the Appointments page.</p>
      </div>
    </div>`;
}

async function saveSettings() {
  const payload = {
    business_name:        $('#sName')?.value.trim(),
    business_phone:       $('#sPh')?.value.trim(),
    business_email:       $('#sEm')?.value.trim(),
    business_address:     $('#sAddr')?.value.trim(),
    slot_interval_minutes:Number($('#sSlot')?.value||30),
    booking_notice_hours: Number($('#sNotice')?.value||2),
    reminder_hours_before:Number($('#sRemind')?.value||24),
    twilio_account_sid:   $('#tSID')?.value.trim(),
    twilio_auth_token:    $('#tTok')?.value.trim(),
    twilio_from_number:   $('#tFrom')?.value.trim(),
    sms_sender:           $('#tSend')?.value.trim(),
  };
  const res = await api(BP + '/api/admin_settings.php', { method: 'POST', body: JSON.stringify(payload) });
  alert(res.success ? '✅ Settings saved!' : '❌ Failed to save.');
}

// ─────────────────────────────────────────────────────────────
//  UTILS
// ─────────────────────────────────────────────────────────────
function esc(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmtDate(d) {
  if (!d) return '—';
  try { return new Date(d + 'T12:00:00').toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}); } catch { return d; }
}
function fmtTime(t) {
  if (!t) return '—';
  try {
    const [h,m] = t.split(':');
    const hh = Number(h), ampm = hh >= 12 ? 'PM' : 'AM';
    return `${hh % 12 || 12}:${m} ${ampm}`;
  } catch { return t; }
}

// Expose for inline onclick
window.updateStatus   = updateStatus;
window.sendSMSBtn     = sendSMSBtn;
window.apptDetail     = apptDetail;
window.openTechModal  = openTechModal;
window.saveTech       = saveTech;
window.toggleTech     = toggleTech;
window.openSvcModal   = openSvcModal;
window.saveSvc        = saveSvc;
window.toggleSvc      = toggleSvc;
window.saveHours      = saveHours;
window.toggleDayRow   = toggleDayRow;
window.addBlocked     = addBlocked;
window.removeBlocked  = removeBlocked;
window.saveSettings   = saveSettings;
window.closeModal     = closeModal;
window.loadPage       = loadPage;
window.pageAppointments = pageAppointments;
