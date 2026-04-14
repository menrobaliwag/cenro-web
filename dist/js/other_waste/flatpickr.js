  document.addEventListener('DOMContentLoaded', function () {
    // Start Date Picker
    flatpickr("#date_start_input", {
      dateFormat: "F j, Y",      // Display format: June 18, 2025
      disableMobile: true,
      allowInput: true
    });

    // End Date Picker
    flatpickr("#date_end_input", {
      dateFormat: "F j, Y",
      disableMobile: true,
      allowInput: true
    });

    // Clear buttons
    document.querySelectorAll('.clear-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const type = btn.getAttribute('data-clear');
        if (type === 'start') {
          document.getElementById('date_start_input')._flatpickr.clear();
        } else if (type === 'end') {
          document.getElementById('date_end_input')._flatpickr.clear();
        }
      });
    });
  });
    document.addEventListener('DOMContentLoaded', function () {
    flatpickr("#date_start_input", {
      dateFormat: "F j, Y",
      disableMobile: true,
      allowInput: false,
    });

    flatpickr("#date_end_input", {
      dateFormat: "F j, Y",
      disableMobile: true,
      allowInput: false,
    });

    document.querySelectorAll('.clear-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const type = btn.getAttribute('data-clear');
        if (type === 'start') {
          document.getElementById('date_start_input')._flatpickr.clear();
        } else if (type === 'end') {
          document.getElementById('date_end_input')._flatpickr.clear();
        }
      });
    });
  });