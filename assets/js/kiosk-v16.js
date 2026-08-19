document.addEventListener('DOMContentLoaded', () => {
  const steps = Array.from(document.querySelectorAll('.kiosk-step'));
  const backBtn = document.getElementById('kiosk-back');
  const subtitle = document.getElementById('kiosk-subtitle');
  const alertBox = document.getElementById('kiosk-alert');
  const csrfToken = document.getElementById('csrf_token').value;

  let phoneDigits = '';
  let fullName = '';
  let dobValue = '';
  let cameFromLookup = false;
  let rewardPoints = 0;
  let selectedServices = []; // array of card elements, up to 3
  let selectedDiscountId = '';
  let restartTimer = null;
  const MAX_SERVICES = 3;

  function renderStampCard(containerEl, totalPoints) {
    if (!containerEl) return;
    const points = Math.max(0, totalPoints || 0);
    const completedBlocks = Math.floor(points / 10);
    const filled = points % 10;
    const remaining = filled === 0 ? 10 : 10 - filled;

    let html = '<div class="kiosk-stamp-card-title">Your Visit Card</div><div class="kiosk-stamp-grid">';
    for (let i = 0; i < 10; i++) {
      html += `<div class="kiosk-stamp${i < filled ? ' filled' : ''}">${i < filled ? 'â™¦' : ''}</div>`;
    }
    html += '</div>';

    let note = '';
    if (completedBlocks > 0) {
      note += `ðŸ’Ž You have <strong>$${completedBlocks * 10}</strong> in rewards ready â€” ask us to redeem it! `;
    }
    note += `<strong>${remaining}</strong> more visit${remaining === 1 ? '' : 's'} until your next $10 off.`;
    html += `<div class="kiosk-stamp-note">${note}</div>`;

    containerEl.innerHTML = html;
    containerEl.style.display = 'block';
  }

  const subtitles = {
    0: 'Enter your phone number to check in',
    1: "What's your name?",
    2: 'Choose your service',
    3: 'Any discounts?',
    4: '',
    5: '',
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
  const checkPointsBtn = document.getElementById('check-points-btn');

  function refreshPhoneDisplay() {
    phoneDisplay.textContent = formatPhoneDisplay(phoneDigits);
    phoneDisplay.classList.toggle('filled', phoneDigits.length > 0);
    phoneContinue.disabled = phoneDigits.length !== 10;
    if (checkPointsBtn) checkPointsBtn.disabled = phoneDigits.length !== 10;
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
        rewardPoints = data.reward_points || 0;
        cameFromLookup = true;
        subtitles[2] = `Welcome back, ${fullName.split(' ')[0]}! Choose your service`;
        showStep(2);
      } else {
        cameFromLookup = false;
        rewardPoints = 0;
        document.getElementById('name-input').value = '';
        showStep(1);
      }
    } catch (err) {
      showAlert('Could not reach the front desk system. Please ask for help.');
    }
    phoneContinue.disabled = phoneDigits.length !== 10;
    phoneContinue.textContent = 'Continue';
  });

  /* ---------------- "Check My Points" (no check-in) ---------------- */
  if (checkPointsBtn) {
    checkPointsBtn.addEventListener('click', async () => {
      if (phoneDigits.length !== 10) return;
      checkPointsBtn.disabled = true;
      checkPointsBtn.textContent = 'Looking up...';
      try {
        const res = await fetch('/actions/kiosk-check-points.php?phone=' + encodeURIComponent(phoneDigits));
        const data = await res.json();
        const lookupCard = document.getElementById('points-lookup-stamp-card');
        if (data.ok && data.found) {
          document.getElementById('points-lookup-name').textContent = `Hi, ${data.full_name.split(' ')[0]}!`;
          document.getElementById('points-lookup-balance').textContent = `You have ${data.points} reward point${data.points === 1 ? '' : 's'}.`;
          renderStampCard(lookupCard, data.points);
        } else {
          document.getElementById('points-lookup-name').textContent = 'No account found';
          document.getElementById('points-lookup-balance').textContent = 'We couldn\'t find a rewards account for that phone number yet â€” check in for a service to start earning points.';
          if (lookupCard) lookupCard.style.display = 'none';
        }
        showStep(5);
      } catch (err) {
        showAlert('Could not reach the front desk system. Please ask for help.');
      }
      checkPointsBtn.disabled = phoneDigits.length !== 10;
      checkPointsBtn.textContent = 'âœ¨ Check My Points';
    });
  }
  document.getElementById('points-lookup-done')?.addEventListener('click', resetKiosk);

  /* ---------------- Step 1: name ---------------- */
  const nameInput = document.getElementById('name-input');
  const dobInput = document.getElementById('dob-input');
  if (dobInput) {
    dobInput.addEventListener('input', () => {
      let digits = dobInput.value.replace(/\D/g, '').slice(0, 8);
      let formatted = digits;
      if (digits.length > 4) formatted = `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`;
      else if (digits.length > 2) formatted = `${digits.slice(0, 2)}/${digits.slice(2)}`;
      dobInput.value = formatted;
    });
  }
  document.getElementById('name-continue').addEventListener('click', () => {
    const value = nameInput.value.trim();
    if (!value) {
      showAlert('Please enter your name.');
      return;
    }
    fullName = value;
    dobValue = dobInput ? dobInput.value : '';
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
    const totalMinutes = selectedServices.reduce((sum, c) => sum + parseInt(c.dataset.serviceDuration || '0', 10), 0);
    serviceSummary.textContent = `${selectedServices.length} selected Â· ${totalMinutes} min total`;
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
    refreshRedeemPointsBlock();
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

  const giftCardInput = document.getElementById('gift-card-input');
  const redeemPointsBlock = document.getElementById('redeem-points-block');
  const redeemPointsCheckbox = document.getElementById('redeem-points-checkbox');
  const redeemPointsLabel = document.getElementById('redeem-points-label');

  function refreshRedeemPointsBlock() {
    if (!redeemPointsBlock) return;
    const redeemableAmount = Math.floor(rewardPoints / 10) * 10;
    if (redeemableAmount >= 10) {
      redeemPointsLabel.textContent = `ðŸ’Ž Use $${redeemableAmount} in reward points on this visit`;
      redeemPointsBlock.style.display = 'block';
    } else {
      redeemPointsBlock.style.display = 'none';
      if (redeemPointsCheckbox) redeemPointsCheckbox.checked = false;
    }
  }

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
          date_of_birth: dobValue,
          service_ids: selectedServices.map((c) => c.dataset.serviceId),
          discount_id: selectedDiscountId,
          gift_card_code: giftCardInput ? giftCardInput.value.trim() : '',
          redeem_points: redeemPointsCheckbox ? redeemPointsCheckbox.checked : false,
        }),
      });
      const data = await res.json();
      if (!data.ok) {
        showAlert(data.error || 'Please review your details and try again.');
        discountContinue.disabled = false;
        discountContinue.textContent = 'Check Me In';
        return;
      }
      const pointsRedeemedEl = document.getElementById('confirm-points-redeemed');
      if (pointsRedeemedEl) {
        if (data.booking.points_redeemed_error) {
          pointsRedeemedEl.textContent = data.booking.points_redeemed_error;
          pointsRedeemedEl.style.color = 'var(--k-danger)';
          pointsRedeemedEl.style.display = 'block';
        } else if (data.booking.points_redeemed_amount) {
          pointsRedeemedEl.textContent = 'Reward points applied to your visit.';
          pointsRedeemedEl.style.color = 'var(--k-success)';
          pointsRedeemedEl.style.display = 'block';
        } else {
          pointsRedeemedEl.style.display = 'none';
        }
      }
      const giftCardEl = document.getElementById('confirm-gift-card');
      if (giftCardEl) {
        if (data.booking.gift_card_error) {
          giftCardEl.textContent = data.booking.gift_card_error;
          giftCardEl.style.color = 'var(--k-danger)';
          giftCardEl.style.display = 'block';
        } else if (data.booking.gift_card_applied) {
          giftCardEl.textContent = 'Gift card applied to your visit.';
          giftCardEl.style.color = 'var(--k-success)';
          giftCardEl.style.display = 'block';
        } else {
          giftCardEl.style.display = 'none';
        }
      }
      document.getElementById('confirm-name').textContent = data.booking.full_name.split(' ')[0];
      document.getElementById('confirm-service').textContent = data.booking.service_name;
      const pointsEl = document.getElementById('confirm-points');
      if (data.booking.points_earned > 0) {
        pointsEl.textContent = `âœ¨ You earned ${data.booking.points_earned} point${data.booking.points_earned === 1 ? '' : 's'}! Total: ${data.booking.total_points} point${data.booking.total_points === 1 ? '' : 's'}.`;
        pointsEl.style.display = 'block';
      } else if (data.booking.total_points > 0) {
        pointsEl.textContent = `You have ${data.booking.total_points} reward point${data.booking.total_points === 1 ? '' : 's'}.`;
        pointsEl.style.display = 'block';
      } else {
        pointsEl.style.display = 'none';
      }
      renderStampCard(document.getElementById('confirm-stamp-card'), data.booking.total_points || 0);
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
    dobValue = '';
    cameFromLookup = false;
    rewardPoints = 0;
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
    if (dobInput) dobInput.value = '';
    if (giftCardInput) giftCardInput.value = '';
    refreshRedeemPointsBlock();
    refreshPhoneDisplay();
    subtitles[2] = 'Choose your service';
    showStep(0);
  }

  document.getElementById('kiosk-restart').addEventListener('click', resetKiosk);

  refreshPhoneDisplay();
});
