let restoreId = null;

// click Restore button in archived table
$(document).on('click', '.unarchive_data', function () {
  restoreId = $(this).data('id');
  $('#unarchive_id').val(restoreId);
  new bootstrap.Modal(document.getElementById('unarchiveModal')).show();
});

// confirm restore
$('#confirmUnarchiveBtn').on('click', function () {
  const id = $('#unarchive_id').val();
  if (!id) return alert("No record selected.");

  const $btn = $(this);
  $btn.prop('disabled', true);
  $btn.find('.unarchive-text').addClass('d-none');
  $btn.find('.spinner-border').removeClass('d-none');

  $.ajax({
    url: 'unarchive_function.php', // ✅ if not same folder, use absolute path
    type: 'POST',
    dataType: 'json',
    data: { id: id },
    success: function (response) {
      alert(response.message);
      if (response.success) location.reload();
    },
    error: function (xhr) {
      console.log(xhr.status, xhr.responseText);
      alert("Restore failed!\n\nStatus: " + xhr.status + "\n\n" + (xhr.responseText || "No response"));
    },
    complete: function () {
      $btn.prop('disabled', false);
      $btn.find('.unarchive-text').removeClass('d-none');
      $btn.find('.spinner-border').addClass('d-none');
    }
  });
});
