(() => {
  let selectedId = null;
  let $clickedBtn = null;
  let ajaxInProgress = false; // prevent multiple clicks

  // Bind click event for unarchive buttons (use off() to prevent duplicates)
  $(document).off('click', '.unarchive_data').on('click', '.unarchive_data', function () {
    selectedId = $(this).data('id');
    $clickedBtn = $(this);

    const unarchiveModal = new bootstrap.Modal(document.getElementById('unarchiveModal'));
    unarchiveModal.show();
  });

  // Bind click event for confirm button (off() to prevent duplicate binding)
  $('#confirmUnarchiveBtn').off('click').on('click', function () {
    if (ajaxInProgress) return; // prevent multiple clicks
    if (!selectedId) {
      alert("No record selected.");
      return;
    }

    ajaxInProgress = true;

    const $confirmBtn = $(this);

    // Disable buttons and show spinner
    $confirmBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Restoring...');
    if ($clickedBtn) {
      $clickedBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Restoring...');
    }

    $.ajax({
      url: 'unarchived_function.php',
      type: 'POST',
      dataType: 'json',
      data: { id: selectedId },
      success: function (response) {
        console.log('AJAX response:', response);
        alert(response.message);
        if (response.success) {
          const modal = bootstrap.Modal.getInstance(document.getElementById('unarchiveModal'));
          modal.hide();
          location.reload(); // reload page to reflect changes
        }
      },
      error: function (xhr) {
        console.error('AJAX Error:', xhr.responseText);
        alert('Failed to unarchive the record. Server says: ' + xhr.responseText);
      },
      complete: function () {
        ajaxInProgress = false;

        // Restore button states
        $confirmBtn.prop('disabled', false).html('<i class="fa fa-check-circle me-1"></i> Yes, Restore');
        if ($clickedBtn) {
          $clickedBtn.prop('disabled', false).html('<i class="fa fa-undo-alt me-1"></i> Unarchive');
        }
      }
    });
  });
})();
