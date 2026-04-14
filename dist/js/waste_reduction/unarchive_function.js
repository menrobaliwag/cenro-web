(() => {
  let selectedId = null;
  let ajaxInProgress = false;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  $(document).off('click', '.unarchive_data').on('click', '.unarchive_data', function () {
    selectedId = $(this).data('id');
    const unarchiveModal = new bootstrap.Modal(document.getElementById('unarchiveModal'));
    unarchiveModal.show();
  });

  $('#confirmUnarchiveBtn').off('click').on('click', function () {
    if (ajaxInProgress) return;
    if (!selectedId) return alert("No record selected.");

    ajaxInProgress = true;
    const $confirmBtn = $(this);
    $confirmBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Restoring...');

    $.ajax({
      url: 'unarchived_function.php',
      type: 'POST',
      dataType: 'json',
      headers: csrfToken ? { 'X-CSRF-Token': csrfToken } : {},
      data: { id: selectedId },
      success: function (response) {
        alert(response.message || 'Done.');
        if (response.success) location.reload();
      },
      error: function (xhr) {
        console.error('AJAX Error:', xhr.responseText);
        alert('Failed to restore the record.');
      },
      complete: function () {
        ajaxInProgress = false;
        $confirmBtn.prop('disabled', false).html('<i class="fa fa-check-circle me-1"></i> Yes, Restore');
      }
    });
  });
})();
