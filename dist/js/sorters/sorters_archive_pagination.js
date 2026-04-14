document.addEventListener('DOMContentLoaded', function () {
  const table = document.getElementById('archivedWasteTable');
  const searchInput = document.getElementById('searchArchived');
  const rowsPerPage = 10;
  const rows = Array.from(table.querySelectorAll('tbody tr'));
  const pagination = document.getElementById('archivedPagination');
  let currentPage = 1;

  function displayRows(page) {
    const start = (page - 1) * rowsPerPage;
    const end = start + rowsPerPage;

    rows.forEach((row, index) => {
      row.style.display = (index >= start && index < end) ? '' : 'none';
    });
  }

  function setupPagination() {
    const totalPages = Math.ceil(rows.length / rowsPerPage);
    pagination.innerHTML = '';
    for (let i = 1; i <= totalPages; i++) {
      const li = document.createElement('li');
      li.className = 'page-item' + (i === currentPage ? ' active' : '');
      li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
      li.addEventListener('click', function (e) {
        e.preventDefault();
        currentPage = i;
        displayRows(currentPage);
        setupPagination();
      });
      pagination.appendChild(li);
    }
  }

  searchInput.addEventListener('input', function () {
    const value = this.value.toLowerCase();
    rows.forEach(row => {
      const match = row.textContent.toLowerCase().includes(value);
      row.style.display = match ? '' : 'none';
    });
  });

  displayRows(currentPage);
  setupPagination();
});
