document.addEventListener('DOMContentLoaded', function () {
  // Keep filtered result but clean URL (same behavior as other modules).
  if (window.location.search.includes('date_start') || window.location.search.includes('date_end')) {
    const cleanUrl = window.location.protocol + '//' + window.location.host + window.location.pathname;
    window.history.pushState({}, '', cleanUrl);
  }
});

document.addEventListener('click', function (event) {
  const button = event.target.closest('.edit_btn');
  if (!button) return;

  document.getElementById('edit_id').value = button.dataset.id || '';
  document.getElementById('edit_name').value = button.dataset.name || '';
  document.getElementById('edit_mrf').value = button.dataset.mrf || '';
  document.getElementById('edit_date').value = button.dataset.date || '';

  document.getElementById('edit_puti').value = button.dataset.puti || '';
  document.getElementById('edit_assorted').value = button.dataset.assorted || '';
  document.getElementById('edit_karton').value = button.dataset.karton || '';
  document.getElementById('edit_pet').value = button.dataset.pet || '';
  document.getElementById('edit_sibak').value = button.dataset.sibak || '';
  document.getElementById('edit_lata').value = button.dataset.lata || '';
  document.getElementById('edit_aluminum').value = button.dataset.aluminum || '';
  document.getElementById('edit_bakal').value = button.dataset.bakal || '';
  document.getElementById('edit_yero').value = button.dataset.yero || '';
  document.getElementById('edit_glass').value = button.dataset.glass || '';

  new bootstrap.Modal(document.getElementById('editRecordModal')).show();
});
