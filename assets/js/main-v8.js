window.addEventListener('error', (e) => {
  const box = document.createElement('div');
  box.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:99999;background:#c0392b;color:#fff;padding:14px;font-size:13px;font-family:monospace;white-space:pre-wrap;';
  box.textContent = 'JS ERROR: ' + e.message + ' | ' + (e.filename || '') + ':' + (e.lineno || '') + ':' + (e.colno || '');
  document.body.appendChild(box);
});

document.addEventListener('DOMContentLoaded', () => {
  /* ---------------- Navbar scroll state ---------------- */
  const navbar = document.querySelector('.navbar');
  const onScroll = () => {
    if (!navbar) return;
    navbar.classList.toggle('is-scrolled', window.scrollY > 30);
  };
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  /* ---------------- Mobile menu ---------------- */
  const menuToggle = document.querySelector('.nav-toggle');
  const mobileMenu = document.querySelector('.mobile-menu');
  const menuClose = document.querySelector('.mobile-menu-close');
  menuToggle?.addEventListener('click', () => mobileMenu?.classList.add('open'));
  menuClose?.addEventListener('click', () => mobileMenu?.classList.remove('open'));
  mobileMenu?.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => mobileMenu.classList.remove('open')));

  /* ---------------- Reveal on scroll ---------------- */
  const revealEls = document.querySelectorAll('[data-reveal]');
  if ('IntersectionObserver' in window && revealEls.length) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.15 }
    );
    revealEls.forEach((el) => observer.observe(el));
  } else {
    revealEls.forEach((el) => el.classList.add('is-visible'));
  }

  /* ---------------- Booking flow ---------------- */
  initBookingFlow();

  /* ---------------- Pre-select service from a "Book Now" card link ---------------- */
  document.querySelectorAll('[data-book-service]').forEach((link) => {
    link.addEventListener('click', () => {
      const id = link.dataset.bookService;
      const pick = document.querySelector(`.service-pick[data-service-id="${id}"]`);
      pick?.click();
    });
  });
});

function initBookingFlow() {
  const form = document.getElementById('booking-form');
  if (!form) return;

  const steps = Array.from(form.querySelectorAll('.booking-step'));
  const dots = Array.from(document.querySelectorAll('.booking-step-dot'));
  const lines = Array.from(document.querySelectorAll('.booking-step-line'));
  const alertBox = document.getElementById('booking-alert');
  let currentStep = 0;

  const MAX_SERVICES = 3;
  let selectedServices = []; // array of service-pick elements, in selection order
  const staffChoices = {}; // service_id -> chosen staff_id, preserved across re-renders

  const servicePicks = Array.from(form.querySelectorAll('.service-pick'));
  const serviceIdInput = form.querySelector('#service_id');
  const pickSummary = document.getElementById('service-pick-summary');
  const staffPerServiceEl = document.getElementById('staff-per-service');

  function refreshStaffPerService() {
    if (!staffPerServiceEl || typeof STAFF_LIST === 'undefined') return;
    staffPerServiceEl.innerHTML = selectedServices.map((p) => {
      const sid = p.dataset.serviceId;
      const options = ['<option value="">No preference</option>']
        .concat(STAFF_LIST.map((s) => `<option value="${s.id}">${s.name} — ${s.title}</option>`))
        .join('');
      return `<div style="display:flex;align-items:center;gap:10px;">
        <span style="flex:1;font-size:13px;color:#7a6a5f;">${p.dataset.serviceName}</span>
        <select class="staff-per-service-select" data-service-id="${sid}" style="flex:1;">${options}</select>
      </div>`;
    }).join('');
    staffPerServiceEl.querySelectorAll('.staff-per-service-select').forEach((sel) => {
      const sid = sel.dataset.serviceId;
      if (staffChoices[sid]) sel.value = staffChoices[sid];
      sel.addEventListener('change', () => { staffChoices[sid] = sel.value; });
    });
  }

  function refreshServicePickSummary() {
    if (!pickSummary) return;
    if (selectedServices.length === 0) {
      pickSummary.style.display = 'none';
      return;
    }
    const totalPrice = selectedServices.reduce((sum, p) => sum + parseFloat(p.dataset.servicePriceRaw || '0'), 0);
    const totalMinutes = selectedServices.reduce((sum, p) => sum + parseInt(p.dataset.serviceDuration || '0', 10), 0);
    const names = selectedServices.map((p) => p.dataset.serviceName).join(' + ');
    pickSummary.textContent = `${names} — $${totalPrice.toFixed(0)} · ${totalMinutes} min total`;
    pickSummary.style.display = 'block';
  }

  servicePicks.forEach((pick) => {
    pick.addEventListener('click', () => {
      const alreadySelected = selectedServices.includes(pick);
      if (alreadySelected) {
        selectedServices = selectedServices.filter((p) => p !== pick);
        pick.classList.remove('selected');
      } else {
        if (selectedServices.length >= MAX_SERVICES) {
          showAlert(`You can select up to ${MAX_SERVICES} services per booking.`);
          return;
        }
        selectedServices.push(pick);
        pick.classList.add('selected');
      }
      serviceIdInput.value = selectedServices.map((p) => p.dataset.serviceId).join(',');
      refreshServicePickSummary();
      refreshStaffPerService();
      clearFieldError('service_id');
    });
  });

  function showStep(index) {
    steps.forEach((s, i) => s.classList.toggle('active', i === index));
    dots.forEach((d, i) => {
      d.classList.toggle('active', i === index);
      d.classList.toggle('done', i < index);
    });
    lines.forEach((l, i) => l.classList.toggle('done', i < index));
    currentStep = index;
  }

  function setFieldError(name, message) {
    const field = form.querySelector(`[data-field="${name}"]`);
    if (!field) return;
    field.classList.add('has-error');
    const err = field.querySelector('.form-error');
    if (err) err.textContent = message;
  }

  function clearFieldError(name) {
    const field = form.querySelector(`[data-field="${name}"]`);
    field?.classList.remove('has-error');
  }

  function clearAllErrors() {
    form.querySelectorAll('.form-field').forEach((f) => f.classList.remove('has-error'));
  }

  function showAlert(message) {
    if (!alertBox) return;
    alertBox.textContent = message;
    alertBox.classList.add('show', 'error');
  }
  function hideAlert() {
    alertBox?.classList.remove('show', 'error');
  }

  function validateStep(index) {
    clearAllErrors();
    hideAlert();
    if (index === 0) {
      if (selectedServices.length === 0) {
        showAlert('Please select at least one service to continue.');
        return false;
      }
    }
    if (index === 1) {
      const date = form.querySelector('#appointment_date').value;
      const time = form.querySelector('#appointment_time').value;
      let ok = true;
      if (!date) { setFieldError('appointment_date', 'Please choose a date.'); ok = false; }
      if (!time) { setFieldError('appointment_time', 'Please choose a time.'); ok = false; }
      if (!ok) showAlert('Please choose a date and time for your visit.');
      return ok;
    }
    if (index === 2) {
      const name = form.querySelector('#full_name').value.trim();
      const email = form.querySelector('#email').value.trim();
      const phone = form.querySelector('#phone').value.trim();
      let ok = true;
      if (!name) { setFieldError('full_name', 'Please enter your name.'); ok = false; }
      if (!email && !phone) {
        setFieldError('email', 'Add an email or phone number.');
        showAlert('Please provide an email or phone number so we can confirm your visit.');
        ok = false;
      }
      if (!ok && name) showAlert('Please double-check your contact details.');
      return ok;
    }
    return true;
  }

  form.querySelectorAll('[data-next]').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (!validateStep(currentStep)) return;
      if (currentStep === 1) fillSummary();
      showStep(Math.min(currentStep + 1, steps.length - 1));
    });
  });
  form.querySelectorAll('[data-prev]').forEach((btn) => {
    btn.addEventListener('click', () => showStep(Math.max(currentStep - 1, 0)));
  });

  function fillSummary() {
    const names = selectedServices.map((p) => p.dataset.serviceName).join(' + ');
    document.getElementById('summary-service').textContent = names || '—';
    const summaryStaff = document.getElementById('summary-staff');
    if (summaryStaff && typeof STAFF_LIST !== 'undefined') {
      const lines = selectedServices.map((p) => {
        const staffId = staffChoices[p.dataset.serviceId];
        const staff = STAFF_LIST.find((s) => String(s.id) === staffId);
        return staff ? `${p.dataset.serviceName}: ${staff.name}` : null;
      }).filter(Boolean);
      summaryStaff.textContent = lines.length ? lines.join(', ') : 'No preference';
    }
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!validateStep(2)) return;

    const submitBtn = form.querySelector('[data-submit]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Booking...';
    hideAlert();

    const staffIds = {};
    selectedServices.forEach((p) => { staffIds[p.dataset.serviceId] = staffChoices[p.dataset.serviceId] || null; });
    const payload = {
      csrf_token: form.querySelector('[name="csrf_token"]').value,
      service_ids: selectedServices.map((p) => p.dataset.serviceId),
      staff_ids: staffIds,
      full_name: form.querySelector('#full_name').value.trim(),
      email: form.querySelector('#email').value.trim(),
      phone: form.querySelector('#phone').value.trim(),
      date_of_birth: form.querySelector('#dob') ? form.querySelector('#dob').value : '',
      appointment_date: form.querySelector('#appointment_date').value,
      appointment_time: form.querySelector('#appointment_time').value,
      notes: form.querySelector('#notes').value.trim(),
    };

    try {
      let res = await fetch('/actions/create-booking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      if (res.status === 419) {
        try {
          const refreshRes = await fetch('/actions/refresh-csrf.php');
          const refreshData = await refreshRes.json();
          if (refreshData.ok && refreshData.csrf_token) {
            form.querySelector('[name="csrf_token"]').value = refreshData.csrf_token;
            payload.csrf_token = refreshData.csrf_token;
            res = await fetch('/actions/create-booking.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(payload),
            });
          }
        } catch (refreshErr) {
          // fall through to normal error handling below with the original response
        }
      }

      const data = await res.json();

      if (!data.ok) {
        const fieldStep = {
          service_id: 0, staff_id: 1, appointment_date: 1, appointment_time: 1,
          full_name: 2, email: 2, phone: 2, contact: 2,
        };
        let earliestStep = 2;
        if (data.errors) {
          Object.entries(data.errors).forEach(([field, msg]) => {
            setFieldError(field, msg);
            if (field in fieldStep) earliestStep = Math.min(earliestStep, fieldStep[field]);
          });
        }
        if (earliestStep < currentStep) {
          showStep(earliestStep);
          showAlert('Please review the highlighted fields below.');
        } else {
          showAlert(data.error || 'Please review the highlighted fields.');
        }
        submitBtn.disabled = false;
        submitBtn.textContent = 'Confirm Reservation';
        return;
      }

      renderConfirmation(data.booking);
      showStep(3);
    } catch (err) {
      showAlert('We could not reach the server. Please check your connection and try again.');
      submitBtn.disabled = false;
      submitBtn.textContent = 'Confirm Reservation';
    }
  });

  function renderConfirmation(booking) {
    document.getElementById('confirm-name').textContent = booking.full_name;
    document.getElementById('confirm-service').textContent = booking.service_name;
    document.getElementById('confirm-duration').textContent = booking.duration;
    document.getElementById('confirm-id').textContent = '#DN' + String(booking.id).padStart(4, '0');
    const staffRow = document.getElementById('confirm-staff-row');
    if (staffRow && booking.staff_name) {
      document.getElementById('confirm-staff').textContent = booking.staff_name;
      staffRow.style.display = 'flex';
    }
  }

  const dateInput = form.querySelector('#appointment_date');
  if (dateInput) {
    const today = new Date();
    dateInput.min = today.toISOString().split('T')[0];
  }
  const dobInput = form.querySelector('#dob');
  if (dobInput) {
    dobInput.max = new Date().toISOString().split('T')[0];
  }
}
