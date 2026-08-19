document.addEventListener('DOMContentLoaded', () => {
  const steps = Array.from(document.querySelectorAll('.kiosk-step'));
  const backBtn = document.getElementById('kiosk-back');
  const subtitle = document.getElementById('kiosk-subtitle');
  const alertBox = document.getElementById('kiosk-alert');
  const csrfToken = document.getElementById('csrf_token').value;

  let phoneDigits = '';
  let fullName = '';
  let cameFromLookup = false;
  let selectedServices = []; // array of card elements, up to 3
  let selectedDiscountId = '';
  let restartTimer = null;
  const MAX_SERVICES = 3;

  const subtitles = {
    0: 'Enter your phone number to check in',
    1: "What's your name?",
    2: 'Choose your service',
    3: 'Any discounts?',
    4: '',
  };

  function showStep(index) {
    steps.forEach((s) => s.classList.toggle('active', Number(s.dataset.step) === index));
    subtitle.textContent = subtitles[index] ?? '';
    backBtn.style.visibility = index === 0 ? 'hidden' : 'visible';
    hideAlert();
  }

  function showAlert(message) {
    alertBox.textContent = message;
    alertBox.classList.add('show');
  }
  function hideAlert() {
    alertBox.classList.remove('show');
  }

  function formatPhoneDisplay(digits) {
    if (!digits) return 'Phone Number';
    const area = digits.slice(0, 3);
    const mid = digits.slice(3, 6);
    const last = digits.slice(6, 10);
    let out = '';
    if (digits.length > 6) out = `(${area}) ${mid}-${last}`;
    else if (digits.length > 3) out = `(${area}) ${mid}`;
    else out = `${area}`;
    return out;
  }

  /* ---------------- Step 0: phone keypad ---------------- */
  const phoneDisplay = document.getElementById('phone-display');
  const phoneContinue = document.getElementById('phone-continue');

  function refreshPhoneDisplay() {
    phoneDisplay.textContent = formatPhoneDisplay(phoneDigits);
    phoneDisplay.classList.toggle('filled', phoneDigits.length > 0);
    phoneContinue.disabled = phoneDigits.length !== 10;
  }

  document.querySelectorAll('.kiosk-key[data-digit]').forEach((key) => {
    key.addEventListener('click', () => {
      if (phoneDigits.length >= 10) return;
      phoneDigits += key.dataset.digit;
      refreshPhoneDisplay();
    });
  });
  document.getElementById('kiosk-erase').addEventListener('click', () => {
    phoneDigits = phoneDigits.slice(0, -1);
    refreshPhoneDisplay();
  });

  phoneContinue.addEventListener('click', async () => {
    phoneContinue.disabled = true;
    phoneContinue.textContent = 'Checking...';
    try {
      const res = await fetch('/actions/kiosk-lookup-client.php?phone=' + encodeURIComponent(phoneDigits));
      const data = await res.json();
      if (data.ok && data.found) {
        fullName = data.full_name;
        cameFromLookup = true;
        subtitles[2] = `Welcome back, ${fullName.split(' ')[0]}! Choose your service`;
        showStep(2);
      } else {
        cameFromLookup = false;
        document.getElementById('name-input').value = '';
        showStep(1);
      }
    } catch (err) {
      showAlert('Could not reach the front desk system. Please ask for help.');
    }
    phoneContinue.disabled = phoneDigits.length !== 10;
    phoneContinue.textContent = 'Continue';
  });

  /* ---------------- Step 1: name ---------------- */
  const nameInput = document.getElementById('name-input');
  document.getElementById('name-continue').addEventListener('click', () => {
    const value = nameInput.value.trim();
    if (!value) {
      showAlert('Please enter your name.');
      return;
    }
    fullName = value;
    subtitles[2] = 'Choose your service';
    showStep(2);
  });

  /* ---------------- Step 2: service (up to 3) ---------------- */
  const serviceContinue = document.getElementById('service-continue');
  const serviceSummary = document.getElementById('kiosk-service-summary');

  function refreshServiceSummary() {
    if (!serviceSummary) return;
    if (selectedServices.length === 0) {
      serviceSummary.style.display = 'none';
      return;
    }
    const totalPrice = selectedServices.reduce((sum, c) => sum + parseFloat(c.dataset.servicePriceRaw || '0'), 0);
    const totalMinutes = selectedServices.reduce((sum, c) => sum + parseInt(c.dataset.serviceDuration || '0', 10), 0);
    serviceSummary.textContent = `${selectedServices.length} selected — $${totalPrice.toFixed(0)} · ${totalMinutes} min total`;
    serviceSummary.style.display = 'block';
  }

  document.querySelectorAll('.kiosk-step[data-step="2"] .kiosk-service').forEach((card) => {
    card.addEventListener('click', () => {
      const alreadySelected = selectedServices.includes(card);
      if (alreadySelected) {
        selectedServices = selectedServices.filter((c) => c !== card);
        card.classList.remove('selected');
      } else {
        if (selectedServices.length >= MAX_SERVICES) {
          showAlert(`You can select up to ${MAX_SERVICES} services.`);
          return;
        }
        selectedServices.push(card);
        card.classList.add('selected');
      }
      refreshServiceSummary();
      serviceContinue.disabled = selectedServices.length === 0;
    });
  });

  serviceContinue.addEventListener('click', () => {
    if (selectedServices.length === 0) return;
    showStep(3);
  });

  /* ---------------- Step 3: discount (optional) ---------------- */
  const discountContinue = document.getElementById('discount-continue');
  document.querySelectorAll('.kiosk-step[data-step="3"] .kiosk-service').forEach((card) => {
    card.addEventListener('click', () => {
      document.querySelectorAll('.kiosk-step[data-step="3"] .kiosk-service').forEach((c) => c.classList.remove('selected'));
      card.classList.add('selected');
      selectedDiscountId = card.dataset.discountId || '';
    });
  });

  discountContinue.addEventListener('click', async () => {
    discountContinue.disabled = true;
    discountContinue.textContent = 'Checking you in...';
    try {
      const res = await fetch('/actions/kiosk-checkin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          csrf_token: csrfToken,
          phone: phoneDigits,
          full_name: fullName,
          service_ids: selectedServices.map((c) => c.dataset.serviceId),
          discount_id: selectedDiscountId,
        }),
      });
      const data = await res.json();
      if (!data.ok) {
        showAlert(data.error || 'Please review your details and try again.');
        discountContinue.disabled = false;
        discountContinue.textContent = 'Check Me In';
        return;
      }
      document.getElementById('confirm-name').textContent = data.booking.full_name.split(' ')[0];
      document.getElementById('confirm-service').textContent = data.booking.service_name;
      const pointsEl = document.getElementById('confirm-points');
      if (data.booking.points_earned > 0) {
        pointsEl.textContent = `✨ You earned ${data.booking.points_earned} reward point${data.booking.points_earned === 1 ? '' : 's'}!`;
        pointsEl.style.display = 'block';
      } else {
        pointsEl.style.display = 'none';
      }
      showStep(4);
      restartTimer = setTimeout(resetKiosk, 15000);
    } catch (err) {
      showAlert('Could not reach the front desk system. Please ask for help.');
      discountContinue.disabled = false;
      discountContinue.textContent = 'Check Me In';
    }
  });

  /* ---------------- Back / restart ---------------- */
  backBtn.addEventListener('click', () => {
    const current = steps.findIndex((s) => s.classList.contains('active'));
    if (current === 2 && cameFromLookup) {
      showStep(0);
    } else if (current > 0) {
      showStep(current - 1);
    }
  });

  function resetKiosk() {
    if (restartTimer) clearTimeout(restartTimer);
    phoneDigits = '';
    fullName = '';
    cameFromLookup = false;
    selectedServices = [];
    selectedDiscountId = '';
    document.querySelectorAll('.kiosk-step[data-step="2"] .kiosk-service').forEach((c) => c.classList.remove('selected'));
    document.querySelectorAll('.kiosk-step[data-step="3"] .kiosk-service').forEach((c, i) => c.classList.toggle('selected', i === 0));
    refreshServiceSummary();
    serviceContinue.disabled = true;
    serviceContinue.textContent = 'Continue';
    discountContinue.disabled = false;
    discountContinue.textContent = 'Check Me In';
    nameInput.value = '';
    refreshPhoneDisplay();
    subtitles[2] = 'Choose your service';
    showStep(0);
  }

  document.getElementById('kiosk-restart').addEventListener('click', resetKiosk);

  refreshPhoneDisplay();
});
