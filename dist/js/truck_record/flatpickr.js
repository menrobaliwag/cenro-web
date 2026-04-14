document.addEventListener('DOMContentLoaded', function () {
  const startInput = document.getElementById('date_start_input');
  const endInput = document.getElementById('date_end_input');

  if (typeof flatpickr === 'function' && startInput) {
    flatpickr(startInput, {
      dateFormat: 'F j, Y',
      disableMobile: true,
      allowInput: false
    });
  }

  if (typeof flatpickr === 'function' && endInput) {
    flatpickr(endInput, {
      dateFormat: 'F j, Y',
      disableMobile: true,
      allowInput: false
    });
  }

  function clearField(inputEl) {
    if (!inputEl) return;
    if (inputEl._flatpickr) {
      inputEl._flatpickr.clear();
    } else {
      inputEl.value = '';
    }
  }

  document.querySelectorAll('.clear-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const type = btn.getAttribute('data-clear');
      if (type === 'start') clearField(startInput);
      if (type === 'end') clearField(endInput);

      const filterForm = startInput ? startInput.closest('form') : null;
      const cleanAction = filterForm
        ? (filterForm.getAttribute('action') || window.location.pathname)
        : window.location.pathname;

      const startVal = String(startInput && startInput.value ? startInput.value : '').trim();
      const endVal = String(endInput && endInput.value ? endInput.value : '').trim();
      if (!startVal && !endVal && window.location.search) {
        window.location.assign(cleanAction);
      }
    });
  });

  if (!startInput || !endInput) return;

  const filterForm = startInput.closest('form');
  if (!filterForm) return;
  const cleanAction = filterForm.getAttribute('action') || window.location.pathname;

  filterForm.addEventListener('submit', function (event) {
    const startVal = String(startInput.value || '').trim();
    const endVal = String(endInput.value || '').trim();
    filterForm.action = cleanAction;

    startInput.disabled = false;
    endInput.disabled = false;

    if (!startVal) startInput.disabled = true;
    if (!endVal) endInput.disabled = true;

    if (startVal && endVal) {
      const startDate = Date.parse(startVal);
      const endDate = Date.parse(endVal);
      if (!Number.isNaN(startDate) && !Number.isNaN(endDate) && startDate > endDate) {
        event.preventDefault();
        startInput.disabled = false;
        endInput.disabled = false;
        alert('Date Start must not be later than Date End.');
        return;
      }
    }

    // If both dates are cleared, reload clean URL without stale query params.
    if (!startVal && !endVal) {
      event.preventDefault();
      window.location.assign(cleanAction);
    }
  });
});
