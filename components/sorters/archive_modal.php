<?php
// Fetch archived scavenger data
$data = [];
$qry = $conn->query("SELECT * FROM `scavenger` WHERE archived = 1 ORDER BY date DESC");
if ($qry instanceof mysqli_result) {
  while ($row = $qry->fetch_assoc()) {
    $data[] = [
      'id'        => $row['id'],
      'date'      => date('F j, Y', strtotime($row['date'])),
      'name'      => htmlspecialchars($row['name']),
      'mrf'       => htmlspecialchars($row['mrf']),
      'puti'      => $row['puti'],
      'assorted'  => $row['assorted'],
      'karton'    => $row['karton'],
      'pet'       => $row['pet'],
      'sibak'     => $row['sibak'],
      'lata'      => $row['lata'],
      'aluminum'  => $row['aluminum'],
      'bakal'     => $row['bakal'],
      'yero'      => $row['yero'],
      'glass'     => $row['glass']
    ];
  }
} else {
  error_log('sorters archive modal query failed: ' . $conn->error);
}
?>

<!-- Archived Waste Records Modal -->
<div class="modal fade" id="archivedModal" tabindex="-1" aria-labelledby="archivedModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header bg-purple text-white">
        <h5 class="modal-title fw-bold" id="archivedModalLabel">
          <i class="fa fa-archive me-2"></i> Archived Waste Records
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body">
        <div class="mb-3 d-flex justify-content-between align-items-center">
          <input type="text" id="searchArchived" class="form-control w-50" placeholder="Search records...">
        </div>

        <div class="table-responsive sorters-no-scroll-shell">
          <table class="table table-bordered table-hover text-center align-middle sorters-no-scroll-table" id="archivedWasteTable">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Date</th>
                <th>Name</th>
                <th>Type</th>
                <th>Puti</th>
                <th>Assorted</th>
                <th>Karton</th>
                <th>PET</th>
                <th>Sibak</th>
                <th>Lata</th>
                <th>Aluminum</th>
                <th>Bakal</th>
                <th>Yero</th>
                <th>Glass</th>
                <th class="noExport">Action</th>
              </tr>
            </thead>
        <tbody>
    <?php if (!empty($data)): ?>
        <?php $i = 1; foreach ($data as $row): ?>
        <tr>
            <td><?= $i++ ?></td>
            <td><?= $row['date'] ?></td>
            <td><?= $row['name'] ?></td>
            <td><?= $row['mrf'] ?></td>
            <td><?= $row['puti'] ?></td>
            <td><?= $row['assorted'] ?></td>
            <td><?= $row['karton'] ?></td>
            <td><?= $row['pet'] ?></td>
            <td><?= $row['sibak'] ?></td>
            <td><?= $row['lata'] ?></td>
            <td><?= $row['aluminum'] ?></td>
            <td><?= $row['bakal'] ?></td>
            <td><?= $row['yero'] ?></td>
            <td><?= $row['glass'] ?></td>
            <td>
             <button class="btn btn-sm bg-purple text-white unarchive_data" data-id="<?= $row['id'] ?>">
                <i class="fa fa-undo-alt me-1"></i> Restore
              </button>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
        <td colspan="15" class="text-center text-muted">No archived waste records found.</td>
        </tr>
    <?php endif; ?>
    </tbody>

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
<!-- Confirm Unarchive Modal -->
<div class="modal fade" id="unarchiveModal" tabindex="-1" aria-labelledby="unarchiveModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4">
      <div class="modal-header bg-purple text-white">
        <h5 class="modal-title" id="unarchiveModalLabel"><i class="fa fa-undo-alt me-2"></i> Confirm Unarchive</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to restore this archived record?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="confirmUnarchiveBtn" class="btn bg-purple text-white">
          <i class="fa fa-check-circle me-1"></i> Yes, Restore
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================== -->
<!-- END ARCHIVED WASTE RECORDS MODAL -->
<!-- ============================================================== -->
<!-- ✅ ARCHIVE CONFIRMATION MODAL (for Waste) -->
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

<!-- ✅ END ARCHIVE CONFIRMATION MODAL (for Waste) -->
