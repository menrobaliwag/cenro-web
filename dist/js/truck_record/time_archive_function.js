let selectedId = null;

function notifyToast(opts) {
  if (window.AppToast && typeof window.AppToast.show === 'function') {
    window.AppToast.show(opts);
    return;
  }
  alert((opts && opts.message) || 'Notification');
}

$(document).on('click', '.archive_data', function () {
  selectedId = $(this).data('id');
  const archiveModal = new bootstrap.Modal(document.getElementById('archiveModal'));
  archiveModal.show();
});

$('#confirmArchiveBtn').on('click', function () {
  if (!selectedId) {
    notifyToast({ title: 'Archive', message: 'No record selected.', variant: 'warning' });
    return;
  }

  const $btn = $(this);
  $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Archiving...');

  $.ajax({
    url: 'truck_record_archive_record.php',
    type: 'POST',
    dataType: 'json',
    data: { id: selectedId },
    success: function (response) {
      notifyToast({
        title: 'Archive',
        message: response.message || 'Request completed.',
        variant: response.success ? 'success' : 'danger'
      });
      if (response.success) location.reload();
    },
    error: function () {
      notifyToast({ title: 'Archive', message: 'Failed to archive the record. Please try again.', variant: 'danger' });
    },
    complete: function () {
      $btn.prop('disabled', false).html('<i class="fa fa-check me-1"></i> Yes, Archive');
    }
  });
});
