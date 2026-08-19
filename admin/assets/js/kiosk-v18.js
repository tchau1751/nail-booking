/* ============================================================
   Diamond Nail & Spa — Kiosk Check-In Interface
   ============================================================ */
'use strict';

const state = {
  currentStep: 0,
  phone: '',
  name: '',
  dob: '',
  services: [],
  selectedDiscount: null,
  giftCardCode: '',
  giftCardAmount: 0,
  redeemPoints: false,
  previousSteps: [], // Track visited steps for back button
};

const UI = {
  alert: () => document.getElementById('kiosk-alert'),
  step: (n) => document.querySelector(`[data-step="${n}"]`),
  back: () => document.getElementById('kiosk-back'),
  subtitle: () => document.getElementById('kiosk-subtitle'),
  phoneDisplay: () => document.getElementById('phone-display'),
  phoneContinue: () => document.getElementById('phone-continue'),
  checkPointsBtn: () => document.getElementById('check-points-btn'),
  nameInput: () => document.getElementById('name-input'),
  dobInput: () => document.getElementById('dob-input'),
  nameContinue: () => document.getElementById('name-continue'),
  serviceGrid: () => document.querySelector('.kiosk-service-grid'),
  serviceSummary: () => document.getElementById('kiosk-service-summary'),
  serviceContinue: () => document.getElementById('service-continue'),
  discountContinue: () => document.getElementById('discount-continue'),
  giftCardInput: () => document.getElementById('gift-card-input'),
  giftCardStatus: () => document.getElementById('gift-card-status'),
  confirmBtn: () => document.getElementById('kiosk-restart'),
  confirmName: () => document.getElementById('confirm-name'),
  confirmService: () => document.getElementById('confirm-service'),
  confirmGiftCard: () => document.getElementById('confirm-gift-card'),
  confirmPoints: () => document.getElementById('confirm-points'),
};

// ── Utilities ────────────────────────────────────────────────
function showAlert(message, type = 'error') {
  const el = UI.alert();
  if (!el) return;
  el.className = `kiosk-alert kiosk-alert-${type}`;
  el.textContent = message;
  el.style.display = message ? 'block' : 'none';
}

function goToStep(n) {
  document.querySelectorAll('.kiosk-step').forEach(el => {
    el.classList.remove('active');
  });
  const step = UI.step(n);
  if (step) step.classList.add('active');

  if (n !== state.currentStep) {
    state.previousSteps.push(state.currentStep);
  }
  state.currentStep = n;

  const back = UI.back();
  if (back) {
    // Show back button on all steps except 0 and when there's nowhere to go back to
    back.style.visibility = (n === 0 || state.previousSteps.length === 0) ? 'hidden' : 'visible';
  }

  updateSubtitle();
  showAlert('');
}

function updateSubtitle() {
  const sub = UI.subtitle();
  if (!sub) return;

  const subtitles = {
    0: 'Enter your phone number to check in',
    1: 'Tell us your name',
    2: 'Select up to 3 services',
    3: 'Have a discount? Select it below',
    4: 'You\'re checked in!',
    5: 'Your points balance',
  };
  sub.textContent = subtitles[state.currentStep] || '';
}

// ── Step 0: Phone Entry ──────────────────────────────────────
function initPhoneKeypad() {
  const display = UI.phoneDisplay();
  const continueBtn = UI.phoneContinue();
  const checkPointsBtn = UI.checkPointsBtn();

  document.querySelectorAll('.kiosk-key[data-digit]').forEach(btn => {
    btn.addEventListener('click', () => {
      if (state.phone.length < 10) {
        state.phone += btn.dataset.digit;
        updatePhoneDisplay();
      }
    });
  });

  document.getElementById('kiosk-erase')?.addEventListener('click', () => {
    state.phone = state.phone.slice(0, -1);
    updatePhoneDisplay();
  });

  function updatePhoneDisplay() {
    if (display) {
      const formatted = formatPhoneForDisplay(state.phone);
      display.textContent = formatted || 'Phone Number';
    }
    if (continueBtn) continueBtn.disabled = state.phone.length < 10;
    if (checkPointsBtn) checkPointsBtn.disabled = state.phone.length < 10;
  }

  continueBtn?.addEventListener('click', () => lookupOrCreateClient());
  checkPointsBtn?.addEventListener('click', () => checkPointsLookup());
}

function formatPhoneForDisplay(digits) {
  if (!digits) return '';
  const d = digits.replace(/\D/g, '');
  if (d.length <= 3) return d;
  if (d.length <= 6) return `(${d.slice(0,3)}) ${d.slice(3)}`;
  return `(${d.slice(0,3)}) ${d.slice(3,6)}-${d.slice(6)}`;
}

async function lookupOrCreateClient() {
  showAlert('');

  if (state.phone.length < 10) {
    showAlert('Please enter a valid 10-digit phone number');
    return;
  }

  try {
    // Look up client to see if they're new or existing
    const resp = await fetch('/api/lookup_client.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ phone: state.phone })
    });

    const data = await resp.json();

    if (data.exists) {
      // Existing client - go to service selection
      state.name = data.name || '';
      goToStep(2);
    } else {
      // New client - ask for name
      goToStep(1);
    }
  } catch (err) {
    showAlert('Connection error. Please try again.');
    console.error(err);
  }
}

async function checkPointsLookup() {
  showAlert('');

  if (state.phone.length < 10) {
    showAlert('Please enter a valid phone number');
    return;
  }

  try {
    const resp = await fetch('/api/lookup_points.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ phone: state.phone })
    });

    const data = await resp.json();

    if (!data.success) {
      showAlert(data.error || 'Client not found');
      return;
    }

    state.name = data.name || '';
    const nameEl = document.getElementById('points-lookup-name');
    const balanceEl = document.getElementById('points-lookup-balance');

    if (nameEl) nameEl.textContent = `Hello, ${data.name}!`;
    if (balanceEl) balanceEl.textContent = `✨ ${data.points || 0} Points`;

    goToStep(5);
  } catch (err) {
    showAlert('Connection error. Please try again.');
    console.error(err);
  }
}

// ── Step 1: Name Entry ───────────────────────────────────────
function initNameEntry() {
  UI.nameContinue()?.addEventListener('click', () => {
    state.name = (UI.nameInput()?.value || '').trim();
    if (!state.name) {
      showAlert('Please enter your name');
      return;
    }

    const dobInput = UI.dobInput()?.value?.trim();
    if (dobInput) {
      // Validate date format MM/DD/YYYY
      if (!/^\d{2}\/\d{2}\/\d{4}$/.test(dobInput)) {
        showAlert('Date of birth must be in MM/DD/YYYY format');
        return;
      }
      state.dob = dobInput;
    }

    goToStep(2);
  });
}

// ── Step 2: Service Selection ────────────────────────────────
function initServiceSelection() {
  const grid = UI.serviceGrid();
  if (!grid) return;

  grid.querySelectorAll('.kiosk-service[data-service-id]').forEach(el => {
    el.addEventListener('click', () => {
      const count = state.services.length;
      const serviceId = el.dataset.serviceId;
      const serviceName = el.dataset.serviceName;
      const isPedicure = serviceName.toLowerCase().includes('pedicure');
      const pedicureCount = state.services.filter(s => s.name.toLowerCase().includes('pedicure')).length;

      // Allow up to 3 services total, but allow 2+ pedicures
      if (count < 3) {
        state.services.push({
          id: serviceId,
          name: serviceName,
          price: parseFloat(el.dataset.servicePriceRaw),
          duration: parseInt(el.dataset.serviceDuration),
        });
        el.classList.add('selected');

        // Show how many are selected for pedicures
        if (isPedicure && pedicureCount > 0) {
          showAlert(`${pedicureCount + 1} pedicures selected`, 'success');
        }
      } else {
        showAlert('You can select up to 3 services (e.g., 2+ pedicures + 1 other service)');
        return;
      }

      updateServiceSummary();
    });

    // Add remove button to selected services (click twice to deselect)
    el.addEventListener('dblclick', () => {
      const index = state.services.findIndex(s => s.id == el.dataset.serviceId);
      if (index !== -1) {
        state.services.splice(index, 1);
        // Update selected styling - only remove if ALL instances are gone
        const hasMore = state.services.some(s => s.id == el.dataset.serviceId);
        if (!hasMore) {
          el.classList.remove('selected');
        }
        updateServiceSummary();
      }
    });
  });

  UI.serviceContinue()?.addEventListener('click', () => {
    if (!state.services.length) {
      showAlert('Please select at least one service');
      return;
    }
    goToStep(3);
  });
}

function updateServiceSummary() {
  const el = UI.serviceSummary();
  if (!el) return;

  if (!state.services.length) {
    el.style.display = 'none';
    return;
  }

  el.style.display = 'block';
  const items = state.services.map(s => `${s.name}`).join(' + ');
  el.textContent = `${items} — $${state.services.reduce((sum, s) => sum + s.price, 0).toFixed(0)}`;
}

// ── Step 3: Discount & Gift Card ────────────────────────────
function initDiscountSelection() {
  const grid = document.querySelector('[data-step="3"] .kiosk-service-grid');
  if (!grid) return;

  grid.querySelectorAll('.kiosk-service').forEach(el => {
    el.addEventListener('click', () => {
      grid.querySelectorAll('.kiosk-service').forEach(e => e.classList.remove('selected'));
      el.classList.add('selected');
      state.selectedDiscount = {
        id: el.dataset.discountId || '',
        name: el.dataset.discountName || '',
      };
    });
  });

  const giftCardInput = UI.giftCardInput();
  if (giftCardInput) {
    giftCardInput.addEventListener('blur', () => validateGiftCard());
    giftCardInput.addEventListener('keyup', (e) => {
      if (e.key === 'Enter') validateGiftCard();
    });
  }

  UI.discountContinue()?.addEventListener('click', () => submitCheckIn());
}

async function validateGiftCard() {
  const input = UI.giftCardInput();
  const status = UI.giftCardStatus();

  if (!input || !status) return;

  const code = input.value.trim().toUpperCase();
  if (!code) {
    status.textContent = '';
    state.giftCardCode = '';
    state.giftCardAmount = 0;
    return;
  }

  status.textContent = '⏳ Validating...';

  try {
    const resp = await fetch('/api/validate_gift_card.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ code })
    });

    const data = await resp.json();

    if (data.valid) {
      status.textContent = `✅ Valid • $${data.amount}`;
      status.style.color = '#059669';
      state.giftCardCode = code;
      state.giftCardAmount = data.amount;
    } else {
      status.textContent = data.error || '❌ Invalid code';
      status.style.color = '#DC2626';
      state.giftCardCode = '';
      state.giftCardAmount = 0;
    }
  } catch (err) {
    status.textContent = '❌ Error validating';
    status.style.color = '#DC2626';
    console.error(err);
  }
}

async function submitCheckIn() {
  showAlert('');

  try {
    const resp = await fetch('/studio/actions/checkin-with-stamps.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        phone: state.phone,
        name: state.name,
        dob: state.dob,
        services: state.services,
        discount: state.selectedDiscount,
        gift_card_code: state.giftCardCode,
      })
    });

    const data = await resp.json();

    if (!data.success) {
      showAlert(data.error || 'Check-in failed');
      return;
    }

    // Show confirmation
    const confirmName = UI.confirmName();
    const confirmService = UI.confirmService();
    const confirmGiftCard = UI.confirmGiftCard();
    const confirmPoints = UI.confirmPoints();

    if (confirmName) confirmName.textContent = state.name;
    if (confirmService) {
      const services = state.services.map(s => s.name).join(', ');
      confirmService.textContent = services;
    }

    if (state.giftCardCode && confirmGiftCard) {
      confirmGiftCard.style.display = 'block';
      confirmGiftCard.textContent = `🎁 Gift card applied: $${state.giftCardAmount}`;
    }

    if (data.points && confirmPoints) {
      confirmPoints.style.display = 'block';
      confirmPoints.textContent = `You have ${data.points} points!`;
    }

    goToStep(4);

    // Auto-send gift card SMS if applicable
    if (state.giftCardCode) {
      sendGiftCardSMS();
    }
  } catch (err) {
    showAlert('Network error. Please try again.');
    console.error(err);
  }
}

async function sendGiftCardSMS() {
  try {
    await fetch('/api/gift_card_send_sms.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        phone: state.phone,
        card_code: state.giftCardCode,
        amount: state.giftCardAmount,
      })
    });
  } catch (err) {
    console.error('Failed to send gift card SMS:', err);
  }
}

// ── Navigation ───────────────────────────────────────────────
function initNavigation() {
  const backBtn = UI.back();
  if (!backBtn) return;

  backBtn.addEventListener('click', () => {
    if (state.previousSteps.length > 0) {
      const prevStep = state.previousSteps.pop();

      // Clear future-step data when going back
      if (state.currentStep > 2) {
        state.selectedDiscount = null;
        state.giftCardCode = '';
        state.giftCardAmount = 0;
      }

      // Don't clear services when going back to service selection
      if (prevStep !== 2) {
        state.services = [];
      }

      goToStep(prevStep);
    }
  });
}

function initRestart() {
  const restartBtn = UI.confirmBtn();
  if (!restartBtn) return;

  restartBtn.addEventListener('click', () => {
    state.phone = '';
    state.name = '';
    state.dob = '';
    state.services = [];
    state.selectedDiscount = null;
    state.giftCardCode = '';
    state.giftCardAmount = 0;

    document.querySelectorAll('.kiosk-service').forEach(el => el.classList.remove('selected'));

    const phoneInput = UI.phoneDisplay();
    if (phoneInput) phoneInput.textContent = 'Phone Number';

    goToStep(0);
  });

  const pointsDone = document.getElementById('points-lookup-done');
  if (pointsDone) {
    pointsDone.addEventListener('click', () => {
      state.phone = '';
      state.name = '';
      goToStep(0);
    });
  }
}

// ── Init ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  initPhoneKeypad();
  initNameEntry();
  initServiceSelection();
  initDiscountSelection();
  initNavigation();
  initRestart();
  goToStep(0);
});
