function showToast(message, type = 'success') {
  const wrap = document.getElementById('toast-wrap');
  if (!wrap) return;
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.textContent = message;
  wrap.appendChild(el);
  setTimeout(() => el.remove(), 4000);
}

async function postJSON(url, payload) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  return res.json();
}

async function uploadImage(file, subdir, csrfToken) {
  const fd = new FormData();
  fd.append('image', file);
  fd.append('subdir', subdir);
  fd.append('csrf_token', csrfToken);
  const res = await fetch('/admin/actions/upload-image.php', { method: 'POST', body: fd });
  return res.json();
}

function openModal(id) {
  document.getElementById(id)?.classList.add('open');
}
function closeModal(id) {
  document.getElementById(id)?.classList.remove('open');
}

document.addEventListener('click', (e) => {
  if (e.target.matches('[data-modal-close]')) {
    const backdrop = e.target.closest('.modal-backdrop');
    if (backdrop) backdrop.classList.remove('open');
  }
  if (e.target.matches('.modal-backdrop')) {
    e.target.classList.remove('open');
  }
});

/* ---------------- Notification bell (new bookings + check-ins) ---------------- */
const NOTIF_POLL_MS = 20000;
const NOTIF_LAST_SEEN_KEY = 'diamond_admin_last_seen';
let notifPollSince = null;
let notifUnreadCount = 0;

function notifEventLabel(item) {
  if (item.type === 'check_in') {
    return { icon: 'check_in', text: `${item.full_name} checked in`, sub: item.service_name };
  }
  return { icon: 'new_booking', text: `New booking — ${item.full_name}`, sub: item.service_name };
}

function notifTimeAgo(mysqlDatetime) {
  const then = new Date(mysqlDatetime.replace(' ', 'T'));
  const diffMin = Math.max(0, Math.round((Date.now() - then.getTime()) / 60000));
  if (diffMin < 1) return 'just now';
  if (diffMin < 60) return `${diffMin}m ago`;
  const diffHr = Math.round(diffMin / 60);
  if (diffHr < 24) return `${diffHr}h ago`;
  return `${Math.round(diffHr / 24)}d ago`;
}

function notifPrependItems(items) {
  const list = document.getElementById('notif-list');
  if (!list) return;
  if (list.querySelector('.notif-empty')) list.innerHTML = '';
  items.forEach((item) => {
    const label = notifEventLabel(item);
    const row = document.createElement('div');
    row.className = 'notif-item';
    row.innerHTML = `
      <div class="notif-icon ${label.icon}">${label.icon === 'check_in' ? '✓' : '＋'}</div>
      <div class="notif-item-body"><strong>${label.text}</strong><span>${label.sub} · ${notifTimeAgo(item.event_time)}</span></div>
    `;
    list.prepend(row);
  });
}

function notifSetBadge(count) {
  notifUnreadCount = count;
  const badge = document.getElementById('notif-badge');
  if (!badge) return;
  if (count > 0) {
    badge.textContent = count > 9 ? '9+' : String(count);
    badge.style.display = 'flex';
  } else {
    badge.style.display = 'none';
  }
}

async function notifPoll(isInitial) {
  const since = notifPollSince || localStorage.getItem(NOTIF_LAST_SEEN_KEY) || '';
  try {
    const res = await fetch('/admin/actions/activity-feed.php?since=' + encodeURIComponent(since));
    const data = await res.json();
    if (!data.ok) return;

    if (data.items.length) {
      notifPrependItems([...data.items].reverse());
      if (!isInitial) {
        notifSetBadge(notifUnreadCount + data.items.length);
        data.items.forEach((item) => {
          const label = notifEventLabel(item);
          showToast(`${label.text} — ${label.sub}`, 'success');
        });
      } else {
        notifSetBadge(data.items.length);
      }
    }
    notifPollSince = data.server_time;
  } catch (err) {
    // Silent — next interval will retry.
  }
}

function toggleNotifDropdown() {
  const dropdown = document.getElementById('notif-dropdown');
  if (!dropdown) return;
  const opening = !dropdown.classList.contains('open');
  dropdown.classList.toggle('open', opening);
  if (opening) {
    notifSetBadge(0);
    if (notifPollSince) localStorage.setItem(NOTIF_LAST_SEEN_KEY, notifPollSince);
  }
}

document.addEventListener('click', (e) => {
  const wrap = document.querySelector('.notif-bell-wrap');
  const dropdown = document.getElementById('notif-dropdown');
  if (wrap && dropdown && !wrap.contains(e.target)) {
    dropdown.classList.remove('open');
  }
});

if (document.getElementById('notif-bell-btn')) {
  notifPoll(true);
  setInterval(() => notifPoll(false), NOTIF_POLL_MS);
}

/* ---------------- Booking status update (used on bookings/calendar/checkin) ---------------- */
async function updateBookingStatus(bookingId, status, csrfToken) {
  try {
    const data = await postJSON('/admin/actions/update-booking-status.php', {
      booking_id: bookingId,
      status,
      csrf_token: csrfToken,
    });
    if (data.ok) {
      showToast('Booking updated.', 'success');
      return true;
    }
    showToast(data.error || 'Could not update booking.', 'error');
    return false;
  } catch (err) {
    showToast('Network error — please try again.', 'error');
    return false;
  }
}
