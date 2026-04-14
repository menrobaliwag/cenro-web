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
    url: 'sorter_record_archive_record.php',
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
