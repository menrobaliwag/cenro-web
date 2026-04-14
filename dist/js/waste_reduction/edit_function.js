document.addEventListener("DOMContentLoaded", function () {
  const editForm = document.getElementById('editForm');

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.edit_btn');
    if (!btn) return;

    document.getElementById('edit_id').value = btn.dataset.id || '';
    document.getElementById('edit_date').value = btn.dataset.date || '';
    document.getElementById('edit_name').value = btn.dataset.name || '';
    document.getElementById('edit_other_waste').value = btn.dataset.other_waste || '';
    document.getElementById('edit_kgs_before').value = btn.dataset.kgs_before || '0';
    document.getElementById('edit_kgs_after').value = btn.dataset.kgs_after || '0';

    const modal = new bootstrap.Modal(document.getElementById('editModal'));
    modal.show();
  });

  if (editForm) {
    editForm.addEventListener('submit', function (e) {
      e.preventDefault();

      const formData = new FormData(editForm);
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

      fetch((window.BASE_URL || '') + '/modules/mrf/waste_reduction/update_function.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken },
        body: formData
      })
        .then(res => res.text())
        .then(data => {
          if (data.trim() === 'success') {
            const modalElement = document.getElementById('editModal');
            const modalInstance = bootstrap.Modal.getInstance(modalElement);
            if (modalInstance) modalInstance.hide();

            alert("Record updated successfully!");
            location.reload();
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
