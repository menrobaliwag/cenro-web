const rowsPerPage = 5;
let currentPage = 1;
let filteredData = [...archivedData]; // assume archivedData is your full dataset

function notifyToast(opts) {
  if (window.AppToast && typeof window.AppToast.show === 'function') {
    window.AppToast.show(opts);
    return;
  }
  alert((opts && opts.message) || 'Notification');
}

// Highlight matching term
function highlight(text, term) {
  if (!term) return text;
  const regex = new RegExp(`(${term})`, 'gi');
  return text.replace(regex, '<mark>$1</mark>');
}

// Render table rows with pagination and highlighted search terms
function renderTable(page = 1) {
  const searchTerm = document.getElementById("searchArchived").value.toLowerCase();
  const start = (page - 1) * rowsPerPage;
  const end = start + rowsPerPage;
  const data = filteredData.slice(start, end);
  const tbody = document.getElementById("archivedTableBody");

  tbody.innerHTML = data.length ? data.map((row, i) => `
    <tr>
      <td>${start + i + 1}</td>
      <td>${highlight(row.date, searchTerm)}</td>
      <td>${highlight(row.time_in, searchTerm)}</td>
      <td>${highlight(row.time_out, searchTerm)}</td>
      <td><span class="badge text-dark py-2 rounded-pill">${highlight(row.duration, searchTerm)}</span></td>
      <td>${highlight(row.truck, searchTerm)}</td>
      <td>${highlight(row.name, searchTerm)}</td>
      <td>${highlight(row.area, searchTerm)}</td>
      <td>${highlight(row.dumping, searchTerm)}</td>
      <td>
        <button class="btn btn-sm bg-purple text-white unarchive_data" data-id="${row.id}">
          <i class="fa fa-undo-alt"></i> Restore
        </button>
      </td>
    </tr>
  `).join('') : `<tr><td colspan="10" class="text-center">No archived records found.</td></tr>`;

  renderPagination();
}

// Render pagination buttons
function renderPagination() {
  const pageCount = Math.ceil(filteredData.length / rowsPerPage);
  const pag = document.getElementById("archivedPagination");
  pag.innerHTML = '';

  for (let i = 1; i <= pageCount; i++) {
    pag.innerHTML += `<li class="page-item ${i === currentPage ? 'active' : ''}">
      <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
    </li>`;
  }
}

// Change current page and re-render table
function changePage(page) {
  currentPage = page;
  renderTable();
}

// Live search input handler to filter archivedData and re-render table
document.getElementById("searchArchived").addEventListener("input", function () {
  const term = this.value.toLowerCase();
  filteredData = archivedData.filter(row =>
    Object.values(row).some(val =>
      typeof val === 'string' && val.toLowerCase().includes(term)
    )
  );
  currentPage = 1;
  renderTable();
});

// --- Restore Modal + AJAX with spinner and button disabling ---
(() => {
  let selectedId = null;
  let $clickedBtn = null;
  let ajaxInProgress = false;

  // Show modal on restore button click, store selectedId and clicked button
  $(document).off('click', '.unarchive_data').on('click', '.unarchive_data', function () {
    selectedId = $(this).data('id');
    $clickedBtn = $(this);
    const modal = new bootstrap.Modal(document.getElementById('unarchiveModal'));
    modal.show();
  });

  // Confirm restore button click
  $('#confirmUnarchiveBtn').off('click').on('click', function () {
    if (ajaxInProgress) return;
    if (!selectedId) {
      notifyToast({ title: 'Restore', message: 'No record selected.', variant: 'warning' });
      return;
    }

    ajaxInProgress = true;
    const $confirmBtn = $(this);
    const originalHTML = $confirmBtn.html();

    // Disable buttons and show spinner
    $confirmBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Restoring...');
    if ($clickedBtn) {
      $clickedBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Restoring...');
    }

    $.ajax({
      url: 'truck_record_unarchive.php', // adjust if your URL differs
      type: 'POST',
      dataType: 'json',
      data: { id: selectedId },
      success: function (response) {
        notifyToast({
          title: 'Restore',
          message: response.message || 'Request completed.',
          variant: response.success ? 'success' : 'danger'
        });
        if (response.success) {
          const modalInstance = bootstrap.Modal.getInstance(document.getElementById('unarchiveModal'));
          modalInstance.hide();

          // Option 1: reload entire page
          location.reload();

          // Option 2: (optional) remove restored item from filteredData and rerender without reload
          /*
          filteredData = filteredData.filter(item => item.id !== selectedId);
          renderTable(currentPage);
          */
        }
      },
      error: function (xhr) {
        console.error('AJAX Error:', xhr.responseText);
        notifyToast({ title: 'Restore', message: 'Failed to restore the record. Please try again.', variant: 'danger' });
      },
      complete: function () {
        ajaxInProgress = false;
        $confirmBtn.prop('disabled', false).html(originalHTML);
        if ($clickedBtn) {
          $clickedBtn.prop('disabled', false).html('<i class="fa fa-undo-alt"></i> Restore');
        }
      }
    });
  });
})();

// Initial render of table on page load
renderTable();
