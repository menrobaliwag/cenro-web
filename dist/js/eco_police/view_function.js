document.addEventListener('DOMContentLoaded', function () {
  const modalEl = document.getElementById('viewDetailsModal');
  const modalBody = document.getElementById('modalContent');
  const modal = new bootstrap.Modal(modalEl);

  document.addEventListener('click', function (e) {
    const button = e.target.closest('.view_details');
    if (button) {
      const id = button.dataset.id;

      modalBody.innerHTML = `
        <div class="text-center py-5">
          <div class="spinner-border text-primary"></div>
        </div>`;

      modal.show();

      const base = window.BASE_URL || '';
      fetch(base + '/modules/eco_police/view_details_function.php?id=' + encodeURIComponent(id))
        .then(res => res.ok ? res.text() : Promise.reject())
        .then(html => {
          modalBody.innerHTML = html;
        })
        .catch(() => {
          modalBody.innerHTML = '<div class="alert alert-danger">Error loading details.</div>';
        });
    }
  });

  modalEl.addEventListener('hidden.bs.modal', function () {
    modalBody.innerHTML = '';
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style = '';
  });
});
