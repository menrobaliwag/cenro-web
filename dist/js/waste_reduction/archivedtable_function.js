const rowsPerPage = 5;
let currentPage = 1;
const sourceData = (typeof archivedData !== 'undefined' && Array.isArray(archivedData)) ? archivedData : [];
let filteredData = [...sourceData];

function highlight(text, term) {
  if (!term) return text;
  const regex = new RegExp(`(${term})`, 'gi');
  return String(text).replace(regex, '<mark>$1</mark>');
}

function formatKgs(value) {
  const num = Number(value);
  if (Number.isFinite(num)) return num.toFixed(2);
  return '0.00';
}

function renderTable(page = 1) {
  const searchInput = document.getElementById("searchArchived");
  const tbody = document.getElementById("archivedTableBody");
  if (!tbody) return;

  const searchTerm = (searchInput?.value || '').toLowerCase();
  const start = (page - 1) * rowsPerPage;
  const end = start + rowsPerPage;
  const data = filteredData.slice(start, end);

  tbody.innerHTML = data.length ? data.map((row, i) => `
    <tr>
      <td>${start + i + 1}</td>
      <td>${highlight(row.date || '', searchTerm)}</td>
      <td>${highlight(row.name || '', searchTerm)}</td>
      <td>${highlight(row.other_waste || '', searchTerm)}</td>
      <td>${highlight(formatKgs(row.kgs_before), searchTerm)}</td>
      <td>${highlight(formatKgs(row.kgs_after), searchTerm)}</td>
      <td>
        <button class="btn btn-sm bg-purple text-white unarchive_data" data-id="${row.id}">
          <i class="fa fa-undo-alt"></i> Restore
        </button>
      </td>
    </tr>
  `).join('') : `<tr><td colspan="7" class="text-center text-muted">No archived records found.</td></tr>`;

  renderPagination();
}

function renderPagination() {
  const pag = document.getElementById("archivedPagination");
  if (!pag) return;

  const pageCount = Math.ceil(filteredData.length / rowsPerPage);
  pag.innerHTML = '';

  for (let i = 1; i <= pageCount; i++) {
    pag.innerHTML += `<li class="page-item ${i === currentPage ? 'active' : ''}">
      <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
    </li>`;
  }
}

function changePage(page) {
  currentPage = page;
  renderTable(currentPage);
}

document.getElementById("searchArchived")?.addEventListener("input", function () {
  const term = (this.value || '').toLowerCase();
  filteredData = sourceData.filter(row =>
    Object.values(row).some(val =>
      String(val ?? '').toLowerCase().includes(term)
    )
  );
  currentPage = 1;
  renderTable(currentPage);
});

renderTable(currentPage);
