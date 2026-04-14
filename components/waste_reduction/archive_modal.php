<?php
require_once dirname(__DIR__, 2) . '/config/db.php';

$data = [];
$qry = $conn->query("SELECT * FROM `waste_reduction` WHERE archived = 1 ORDER BY date DESC");
if ($qry) {
  while ($row = $qry->fetch_assoc()) {
    $data[] = [
      'id' => $row['id'],
      'date' => date('F j, Y', strtotime($row['date'])),
      'name' => htmlspecialchars($row['name'] ?? ''),
      'other_waste' => htmlspecialchars($row['other_waste'] ?? ''),
      'kgs_before' => $row['kgs_before'],
      'kgs_after' => $row['kgs_after'],
    ];
  }
}
?>

<!-- Archived Waste Reduction Records Modal -->
<div class="modal fade" id="archivedModal" tabindex="-1" aria-labelledby="archivedModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header bg-purple text-white">
        <h5 class="modal-title fw-bold" id="archivedModalLabel">
          <i class="fa fa-archive me-2"></i> Archived Records
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="mb-3 d-flex justify-content-between align-items-center">
          <input type="text" id="searchArchived" class="form-control w-50" placeholder="Search records...">
        </div>

        <div class="table-responsive">
          <table class="table table-bordered table-hover text-center align-middle">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Date</th>
                <th>Name</th>
                <th>Other Wastes</th>
                <th>Kgs Before</th>
                <th>Kgs After</th>
                <th class="noExport">Action</th>
              </tr>
            </thead>
            <tbody id="archivedTableBody"></tbody>
          </table>
        </div>

        <div class="d-flex justify-content-center justify-content-md-between align-items-center mt-3 flex-wrap">
          <nav class="mx-auto">
            <ul class="pagination mb-0" id="archivedPagination"></ul>
          </nav>
          <button type="button" class="btn btn-secondary mt-2 mt-md-0" data-bs-dismiss="modal">
            <i class="fa fa-times me-1"></i> Close
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Confirm Restore Modal -->
<div class="modal fade" id="unarchiveModal" tabindex="-1" aria-labelledby="unarchiveModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header bg-purple text-white">
        <h5 class="modal-title" id="unarchiveModalLabel">
          <i class="fa fa-undo-alt me-2"></i> Confirm Unarchive
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center fs-6">
        Are you sure you want to restore this archived record?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-purple text-white" id="confirmUnarchiveBtn">
          <i class="fa fa-check-circle me-1"></i> Yes, Restore
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Archive Confirmation Modal -->
<div class="modal fade" id="archiveModal" tabindex="-1" aria-labelledby="archiveModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content rounded-4 shadow-lg border-0 animate__animated animate__fadeInDown">
      <div class="modal-header bg-purple text-white rounded-top-4 justify-content-center text-center">
        <div>
          <h5 class="modal-title fw-semibold fs-5 mb-0" id="archiveModalLabel">
            <i class="fa fa-archive me-2 animate__animated animate__bounceIn"></i> Confirm Archive
          </h5>
          <small class="text-light opacity-75">This record will be moved to the archive list.</small>
        </div>
      </div>
      <div class="modal-body text-center px-4">
        <p class="fs-6 mb-1">Are you sure you want to archive this record?</p>
        <p class="fw-bold text-danger small">⚠️ This action cannot be undone.</p>
      </div>
      <div class="modal-footer d-flex justify-content-center gap-2 py-3 px-4">
        <button type="button" id="confirmArchiveBtn" class="btn bg-purple text-white px-3">
          <i class="fa fa-archive me-1"></i> Archive
        </button>
        <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">
          <i class="fa fa-times me-1"></i> Cancel
        </button>
      </div>
    </div>
  </div>
</div>
