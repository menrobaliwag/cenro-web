document.addEventListener('DOMContentLoaded', function () {
  const modalEl = document.getElementById('viewDetailsModal');
  const modalBody = document.getElementById('modalContent');
  const modal = new bootstrap.Modal(modalEl); // ✅ Create modal instance ONCE

  // Delegate click event to .view_details buttons
  document.addEventListener('click', function (e) {
    const button = e.target.closest('.view_details');
    if (button) {
      const id = button.dataset.id;

      // Show loading spinner
      modalBody.innerHTML = `
        <div class="text-center py-5">
          <div class="spinner-border text-primary"></div>
        </div>`;

      modal.show();

      // Fetch and load content
      const base = window.BASE_URL || '';
      fetch(base + '/modules/mrf/truck_record/truck_record_viewdetails.php?id=' + encodeURIComponent(id))
        .then(res => res.ok ? res.text() : Promise.reject())
        .then(html => {
          modalBody.innerHTML = html;
        })
        .catch(() => {
          modalBody.innerHTML = '<div class="alert alert-danger">Error loading details.</div>';
        });
    }
  });

  // Clean up when modal is hidden
  modalEl.addEventListener('hidden.bs.modal', function () {
    modalBody.innerHTML = '';

    // Fix: Remove lingering backdrop if any
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());

    // Optional: reset scroll lock styles
    document.body.classList.remove('modal-open');
    document.body.style = '';
  });
});
