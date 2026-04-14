<?php
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';

requirePermission('mrf.truck');

include dirname(__DIR__, 3) . '/includes/header.php';
include dirname(__DIR__, 3) . '/includes/topbar_sidebar.php';

include dirname(__DIR__, 3) . '/components/truck_record/add_modal.php';
include dirname(__DIR__, 3) . '/components/truck_record/edit_modal.php';
include dirname(__DIR__, 3) . '/components/truck_record/archive_modal.php';
include dirname(__DIR__, 3) . '/components/truck_record/view_modal.php';

function formatReadableDate($raw) {
  return !empty($raw) ? date('F j, Y', strtotime($raw)) : '';
}

$rawStartInput = trim((string)($_GET['date_start'] ?? ''));
$rawEndInput   = trim((string)($_GET['date_end'] ?? ''));

$dateStart = $rawStartInput !== '' ? security_parse_date_to_ymd($rawStartInput) : null;
$dateEnd   = $rawEndInput !== '' ? security_parse_date_to_ymd($rawEndInput) : null;

$displayDateStart = $dateStart ? formatReadableDate($dateStart) : '';
$displayDateEnd   = $dateEnd ? formatReadableDate($dateEnd) : '';

$hasInvalidFilter = ($rawStartInput !== '' && $dateStart === null) || ($rawEndInput !== '' && $dateEnd === null);
$startBound = $dateStart ?? '2000-01-01';
$endBound   = $dateEnd ?? '2100-12-31';
if ($startBound > $endBound) {
  [$startBound, $endBound] = [$endBound, $startBound];
}

?>

<div class="page-wrapper">
  <div class="page-breadcrumb">
    <div class="row">
      <div class="col-5 align-self-center"></div>
      <div class="col-7 align-self-center">
        <div class="d-flex align-items-center justify-content-end">
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
              <li class="breadcrumb-item"><a href="<?= url_with_base('dashboard.php') ?>">Home</a></li>
              <li class="breadcrumb-item active" aria-current="page">Time and Out</li>
            </ol>
          </nav>
        </div>
      </div>
    </div>

    <form method="GET" class="row g-3 align-items-end mb-4 px-3 form-group-card">
      <div class="col-md-4">
        <label for="date_start_input" class="form-label fw-semibold">
          <i class="fa fa-calendar-alt me-1 text-primary"></i> Date Start
        </label>
        <div class="input-clear-wrapper position-relative">
          <input type="text" name="date_start" id="date_start_input"
                 class="form-control form-control-sm rounded shadow-sm"
                 placeholder="Select start date"
                 value="<?= htmlspecialchars($displayDateStart, ENT_QUOTES, 'UTF-8') ?>"
                 autocomplete="off" readonly>
          <button type="button"
                  class="clear-btn position-absolute top-50 end-0 translate-middle-y me-2 btn btn-sm btn-light border"
                  data-clear="start" aria-label="Clear start date">&times;</button>
        </div>
      </div>

      <div class="col-md-4">
        <label for="date_end_input" class="form-label fw-semibold">
          <i class="fa fa-calendar-alt me-1 text-primary"></i> Date End
        </label>
        <div class="input-clear-wrapper position-relative">
          <input type="text" name="date_end" id="date_end_input"
                 class="form-control form-control-sm rounded shadow-sm"
                 placeholder="Select end date"
                 value="<?= htmlspecialchars($displayDateEnd, ENT_QUOTES, 'UTF-8') ?>"
                 autocomplete="off" readonly>
          <button type="button"
                  class="clear-btn position-absolute top-50 end-0 translate-middle-y me-2 btn btn-sm btn-light border"
                  data-clear="end" aria-label="Clear end date">&times;</button>
        </div>
      </div>

      <div class="col-md-4 d-flex align-items-end mt-2 mt-md-4">
        <button type="submit" class="btn btn-action-left shadow-sm me-2">
          <i class="fa fa-filter me-2"></i> Filter
        </button>
        <button type="button" class="btn btn-action-right shadow-sm" data-bs-toggle="modal" data-bs-target="#archivedModal">
          <i class="fa fa-archive me-2"></i> View Archived
        </button>
      </div>
    </form>

    <?php if ($hasInvalidFilter): ?>
      <div class="alert alert-warning mx-3 mt-1 mb-3 py-2">
        Invalid date filter ignored. Please use a valid date.
      </div>
    <?php endif; ?>

    <div class="container-fluid">
      <div class="row">
        <div class="col-12">
          <div class="card fade-in">
            <div class="card-body">

              <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="card-title mb-0">Truck In and Out Record</h4>
                <button type="button" class="btn btn-add-record" data-bs-toggle="modal" data-bs-target="#addRecordModal">
                  <i class="fa fa-plus me-2"></i> Add Record
                </button>
              </div>

              <div class="table-modern-wrap">
                  <table
                    id="file_export"
                    class="table table-modern table-bordered table-hover align-middle text-center table-sm w-100"
                    data-source-url="<?= htmlspecialchars(url_with_base('modules/mrf/truck_record/ajax/fetch.php'), ENT_QUOTES, 'UTF-8') ?>"
                    data-date-start="<?= htmlspecialchars($dateStart ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    data-date-end="<?= htmlspecialchars($dateEnd ?? '', ENT_QUOTES, 'UTF-8') ?>"
                  >
                  <colgroup>
                    <col style="width: 4%;">
                    <col style="width: 12%;">
                    <col style="width: 10%;">
                    <col style="width: 10%;">
                    <col style="width: 12%;">
                    <col style="width: 10%;">
                    <col style="width: 12%;">
                    <col style="width: 10%;">
                    <col style="width: 10%;">
                    <col style="width: 10%;">
                  </colgroup>

                  <thead class="table-light">
                    <tr>
                      <th>#</th>
                      <th>Date</th>
                      <th>Time In</th>
                      <th>Time Out</th>
                      <th>Total Hours</th>
                      <th>Truck Number</th>
                      <th>Driver</th>
                      <th>Area</th>
                      <th>Dumping</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>

<script>
  // keep this if your archive modal uses it; if not, you can remove
  const archivedData = <?= json_encode($data ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>

<?php include dirname(__DIR__, 3) . '/includes/footer_scripts.php'; ?>
</body>
</html>

