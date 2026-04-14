document.addEventListener("DOMContentLoaded", function () {
  // ✅ Clean the URL by removing ?date_start=...&date_end=...
  if (window.location.search.includes('date_start') || window.location.search.includes('date_end')) {
    const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
    window.history.pushState({}, '', cleanUrl);
  }

  const editForm = document.getElementById('editForm');

  // ✅ Delegated event listener for edit buttons
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.edit_btn');
    if (btn) {
      document.getElementById('edit_id').value = btn.dataset.id;
      document.getElementById('edit_date').value = btn.dataset.date;
      document.getElementById('edit_name').value = btn.dataset.name;
      document.getElementById('edit_eco').value = btn.dataset.eco;
      document.getElementById('edit_pavement').value = btn.dataset.pavement;
      document.getElementById('edit_cement').value = btn.dataset.cement;
      document.getElementById('edit_coal').value = btn.dataset.coal;
      document.getElementById('edit_hollow').value = btn.dataset.hollow;

      const modal = new bootstrap.Modal(document.getElementById('editModal'));
      modal.show();
    }
  });

  // ✅ AJAX form submission
  if (editForm) {
    editForm.addEventListener('submit', function (e) {
      e.preventDefault();

      const formData = new FormData(editForm);

      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      fetch((window.BASE_URL || '') + '/modules/mrf/other_waste/update_function.php', { // Make sure path is correct
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken },
        body: formData
      })
      .then(res => res.text())
      .then(data => {
        if (data.trim() === 'success') {
          if (document.activeElement) {
            document.activeElement.blur();
          }

          const modalElement = document.getElementById('editModal');
          const modalInstance = bootstrap.Modal.getInstance(modalElement);
          if (modalInstance) modalInstance.hide();

          alert("Record updated successfully!");
          location.reload(); // Optional: change this if using dynamic reload
        } else {
          alert("Update failed: " + data);
        }
      })
      .catch(error => {
        console.error("AJAX Error: ", error);
        alert("AJAX error occurred.");
      });
    });
  }
});
