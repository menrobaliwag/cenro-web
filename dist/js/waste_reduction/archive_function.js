(() => {
  let selectedId = null;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

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
      headers: csrfToken ? { 'X-CSRF-Token': csrfToken } : {},
      data: { id: selectedId },
      success: function (response) {
        alert(response.message || 'Done.');
        if (response.success) location.reload();
      },
      error: function (xhr) {
        console.error(xhr.responseText);
        alert('Failed to archive the record. Please try again.');
      },
      complete: function () {
        $btn.prop('disabled', false).html('<i class="fa fa-archive me-1"></i> Archive');
      }
    });
  });
})();
