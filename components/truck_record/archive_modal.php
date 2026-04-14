<!-- ============================================================== -->
<!-- ARCHIVED MODAL  -->
<!-- ============================================================== -->
<?php
// Fetch data from the DB
$data = [];
$qry = $conn->query("SELECT * FROM `truck_record` WHERE archived = 1 ORDER BY date_created DESC");
if ($qry instanceof mysqli_result) {
  while ($row = $qry->fetch_assoc()) {
    $time_in = strtotime($row['date_created'] . ' ' . $row['time_in']);
    $time_out = strtotime($row['date_created'] . ' ' . $row['time_out']);
    if ($time_out < $time_in) $time_out += 86400; // handles overnight logs
    $duration = floor(($time_out - $time_in) / 3600) . ' hr';

    $data[] = [
      'id' => $row['id'],
      'date' => date('F j, Y', strtotime($row['date_created'])),
      'time_in' => date('h:i A', $time_in),
      'time_out' => date('h:i A', $time_out),
      'duration' => $duration,
      'truck' => htmlspecialchars($row['truck']),
      'name' => htmlspecialchars($row['name']),
      'area' => htmlspecialchars($row['area']),
      'dumping' => htmlspecialchars($row['dumping']),
    ];
  }
} else {
  error_log('truck_record archive modal query failed: ' . $conn->error);
}
?>
<!-- Archived Records Modal -->
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
                <th>Time In</th>
                <th>Time Out</th>
                <th>Total Hours</th>
                <th>Truck No.</th>
                <th>Driver</th>
                <th>Area</th>
                <th>Dumping</th>
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
<!-- ============================================================== -->
<!-- END ARCHIVED MODAL  -->
<!-- ============================================================== -->
<div class="modal fade" id="unarchiveModal" tabindex="-1" aria-labelledby="unarchiveModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header bg-purple text-white">
        <h5 class="modal-title" id="unarchiveModalLabel">
          <i class="fa fa-undo-alt me-2"></i>  Confirm Unarchive

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
<!-- =============================================== -->
<!-- ARCHIVE CONFIRMATION MODAL -->
<!-- =============================================== -->
<div class="modal fade" id="archiveModal" tabindex="-1" aria-labelledby="archiveModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content rounded-4 shadow-lg border-0 animate__animated animate__fadeInDown">

      <!-- Modal Header -->
      <div class="modal-header bg-purple text-white rounded-top-4 justify-content-center text-center">
        <div>
          <h5 class="modal-title fw-semibold fs-5 mb-0" id="archiveModalLabel">
            <i class="fa fa-archive me-2 animate__animated animate__bounceIn"></i> Confirm Archive
          </h5>
          <small class="text-light opacity-75">This record will be moved to the archive list.</small>
        </div>
      </div>

      <!-- Modal Body -->
      <div class="modal-body text-center px-4">
        <p class="fs-6 mb-1">Are you sure you want to archive this record?</p>
        <p class="fw-bold text-danger small">⚠️ This action cannot be undone.</p>
      </div>

     <!-- Modal Footer -->
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
<!-- ============================================================== -->
<!-- END ARCHIVE MODAL  -->
<!-- ============================================================== -->
