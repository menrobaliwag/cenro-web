let selectedId = null;

$(document).on('click', '.archive_data', function () {
  selectedId = $(this).data('id');
  const archiveModal = new bootstrap.Modal(document.getElementById('archiveModal'));
  archiveModal.show();
});

$('#confirmArchiveBtn').on('click', function () {
  if (!selectedId) return alert("No record selected.");

  const $btn = $(this);
  $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Archiving...');

  $.ajax({
    url: 'archive_function.php',
    type: 'POST',
    dataType: 'json',
    data: { id: selectedId },
    success: function (response) {
      alert(response.message);
      if (response.success) location.reload();
    },
    error: function () {
      alert('Failed to archive the record. Please try again.');
    },
    complete: function () {
      $btn.prop('disabled', false).html('<i class="fa fa-check me-1"></i> Yes, Archive');
    }
  });
});



// ✅ RESTORE LOGIC (Vanilla JS)
document.addEventListener('DOMContentLoaded', () => {
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.restore-btn');
    if (!btn) return;

    const id = btn.dataset.id;
    const spinner = btn.querySelector('.spinner-border');
    const text = btn.querySelector('.restore-text');

    if (!confirm("Restore this archived record?")) return;

    // Show spinner
    text.classList.add('d-none');
    spinner.classList.remove('d-none');

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    fetch((window.BASE_URL || '') + '/modules/eco_police/archive_function.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': csrfToken
      },
      body: 'id=' + encodeURIComponent(id)
    })
    .then(res => res.text())
    .then(data => {
      if (data.trim() === 'success') {
        document.getElementById('row-' + id).remove(); 
        alert("Record restored successfully.");
      } else {
        alert("Restore failed: " + data);
        spinner.classList.add('d-none');
        text.classList.remove('d-none');
      }
    })
    .catch(err => {
      console.error(err);
      alert("AJAX error occurred.");
      spinner.classList.add('d-none');
      text.classList.remove('d-none');
    });
  });
});
