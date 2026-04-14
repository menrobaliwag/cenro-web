document.addEventListener("DOMContentLoaded", function () {
  function notifyToast(opts) {
    if (window.AppToast && typeof window.AppToast.show === 'function') {
      window.AppToast.show(opts);
      return;
    }
    alert((opts && opts.message) || 'Notification');
  }

  // ✅ REMOVE QUERY STRING (clean URL after filtering)
  if (window.location.search.includes('date_start') || window.location.search.includes('date_end')) {
    const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
    window.history.pushState({}, '', cleanUrl);
  }

  const editForm = document.getElementById('editForm');

  // ✅ DELEGATED EVENT LISTENER FOR EDIT BUTTONS
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.edit_btn');
    if (btn) {
      document.getElementById('edit_id').value = btn.dataset.id;
      document.getElementById('edit_date').value = btn.dataset.date;
      document.getElementById('edit_timein').value = btn.dataset.timein;
      document.getElementById('edit_timeout').value = btn.dataset.timeout;
      document.getElementById('edit_truck').value = btn.dataset.truck;
      document.getElementById('edit_name').value = btn.dataset.name;
      document.getElementById('edit_area').value = btn.dataset.area;
      document.getElementById('edit_dumping').value = btn.dataset.dumping;

      const modal = new bootstrap.Modal(document.getElementById('editModal'));
      modal.show();
    }
  });

  // ✅ FORM SUBMISSION WITH MODAL HIDE FIX
  if (editForm) {
    editForm.addEventListener('submit', function (e) {
      e.preventDefault();

      const formData = new FormData(editForm);

      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      fetch((window.BASE_URL || '') + '/modules/mrf/truck_record/truck_record_update_data.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken },
        body: formData
      })
      .then(res => res.text())
      .then(data => {
        if (data.trim() === 'success') {
          // ✅ Blur active element first to avoid aria-hidden focus issue
          if (document.activeElement) {
            document.activeElement.blur();
          }

          const modalElement = document.getElementById('editModal');
          const modalInstance = bootstrap.Modal.getInstance(modalElement);
          if (modalInstance) modalInstance.hide();

          notifyToast({ title: 'Update', message: 'Record updated successfully.', variant: 'success' });
          location.reload(); // Optional: use AJAX refresh if preferred
        } else {
          notifyToast({ title: 'Update', message: ("Update failed: " + data).trim(), variant: 'danger' });
        }
      })
      .catch(error => {
        console.error("AJAX Error: ", error);
        notifyToast({ title: 'Update', message: 'AJAX error occurred.', variant: 'danger' });
      });
    });
  }
});
