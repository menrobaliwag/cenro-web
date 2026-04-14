$(document).on('click', '.edit_btn', function () {
  const btn = $(this);

  // Fill hidden id
  $('#edit_id').val(btn.data('id'));

  // Fill fields
  $('#edit_full_name').val(btn.data('full_name') || '');
  $('#edit_date_created').val(btn.data('date_created') || '');
  $('#edit_address').val(btn.data('address') || '');
  $('#edit_contact_number').val(btn.data('contact_number') || '');
  $('#edit_id_number').val(btn.data('id_number') || '');
  $('#edit_place_of_violation').val(btn.data('place_of_violation') || '');
  $('#edit_penalty_type').val(btn.data('penalty_type') || '');

  // ID Type / Other handling
  const idType = btn.data('id_type') || '';
  const otherId = btn.data('other_id') || '';

  // If current idType isn't in the dropdown list, treat as Other + set other_id
  const hasOption = $('#edit_id_type option').filter(function(){
    return $(this).val() === idType;
  }).length > 0;

  if (hasOption) {
    $('#edit_id_type').val(idType).trigger('change');
    if (idType === 'Other') {
      $('#edit_other_id').val(otherId);
    } else {
      $('#edit_other_id').val('');
    }
  } else {
    $('#edit_id_type').val('Other').trigger('change');
    $('#edit_other_id').val(idType); // save actual type into other_id field
  }

  // Image preview (existing)
  const idImage = btn.data('id_image') || '';
  const img = document.getElementById('edit_id_image_preview');
  const noImg = document.getElementById('edit_no_image');

  if (idImage) {
    img.src = "../../uploads/ids/" + idImage; // adjust path if needed
    img.style.display = "block";
    noImg.style.display = "none";
  } else {
    img.src = "";
    img.style.display = "none";
    noImg.style.display = "block";
  }

  // Clear file input each time
  $('#edit_id_image').val('');

  // Open modal
  const modal = new bootstrap.Modal(document.getElementById('editViolatorModal'));
  modal.show();
});
