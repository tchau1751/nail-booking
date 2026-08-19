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
  let selectedServiceEl = null;

  const servicePicks = Array.from(form.querySelectorAll('.service-pick'));
  const serviceIdInput = form.querySelector('#service_id');

  servicePicks.forEach((pick) => {
    pick.addEventListener('click', () => {
      servicePicks.forEach((p) => p.classList.remove('selected'));
      pick.classList.add('selected');
      selectedServiceEl = pick;
      serviceIdInput.value = pick.dataset.serviceId;
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
      if (!serviceIdInput.value) {
        showAlert('Please select a service to continue.');
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
    const pick = selectedServiceEl;
    const date = form.querySelector('#appointment_date').value;
    const time = form.querySelector('#appointment_time').value;
    document.getElementById('summary-service').textContent = pick ? pick.dataset.serviceName : '—';
    document.getElementById('summary-price').textContent = pick ? pick.dataset.servicePrice : '—';
    document.getElementById('summary-date').textContent = date ? formatDateLabel(date) : '—';
    document.getElementById('summary-time').textContent = time ? formatTimeLabel(time) : '—';
    const staffSelect = form.querySelector('#staff_id');
    const summaryStaff = document.getElementById('summary-staff');
    if (staffSelect && summaryStaff) {
      const selectedOption = staffSelect.options[staffSelect.selectedIndex];
      summaryStaff.textContent = staffSelect.value ? selectedOption.textContent : 'No preference';
    }
  }

  function formatDateLabel(value) {
    const d = new Date(value + 'T00:00:00');
    return d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
  }
  function formatTimeLabel(value) {
    const [h, m] = value.split(':').map(Number);
    const period = h >= 12 ? 'PM' : 'AM';
    const hour12 = h % 12 === 0 ? 12 : h % 12;
    return `${hour12}:${String(m).padStart(2, '0')} ${period}`;
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!validateStep(2)) return;

    const submitBtn = form.querySelector('[data-submit]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Booking...';
    hideAlert();

    const staffSelectEl = form.querySelector('#staff_id');
    const payload = {
      csrf_token: form.querySelector('[name="csrf_token"]').value,
      service_id: serviceIdInput.value,
      staff_id: staffSelectEl ? staffSelectEl.value : '',
      full_name: form.querySelector('#full_name').value.trim(),
      email: form.querySelector('#email').value.trim(),
      phone: form.querySelector('#phone').value.trim(),
      appointment_date: form.querySelector('#appointment_date').value,
      appointment_time: form.querySelector('#appointment_time').value,
      notes: form.querySelector('#notes').value.trim(),
    };

    try {
      const res = await fetch('/actions/create-booking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
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
    document.getElementById('confirm-when').textContent = `${booking.date_label} at ${booking.time_label}`;
    document.getElementById('confirm-duration').textContent = booking.duration;
    document.getElementById('confirm-price').textContent = booking.price;
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
}
