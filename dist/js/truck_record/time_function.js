function calculateTotalHours() {
    const timeIn = document.getElementById('timein').value;
    const timeOut = document.getElementById('timeout').value;
    const totalHoursField = document.getElementById('total_hours');

    if (timeIn && timeOut) {
      const start = new Date(`1970-01-01T${timeIn}:00`);
      const end = new Date(`1970-01-01T${timeOut}:00`);
      let diff = (end - start) / (1000 * 60 * 60); // in hours

      // Handle negative diff (e.g., time out is past midnight)
      if (diff < 0) {
        diff += 24;
      }

      totalHoursField.value = diff.toFixed(2) + ' hrs';
    } else {
      totalHoursField.value = '';
    }
  }

  // Attach event listeners
  document.getElementById('timein').addEventListener('change', calculateTotalHours);
  document.getElementById('timeout').addEventListener('change', calculateTotalHours);
