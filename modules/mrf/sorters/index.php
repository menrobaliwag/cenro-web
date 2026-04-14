<?php
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';

requirePermission('mrf.sorters');

include dirname(__DIR__, 3) . '/includes/header.php';
include dirname(__DIR__, 3) . '/includes/topbar_sidebar.php';

include dirname(__DIR__, 3) . '/components/sorters/add_modal.php';
include dirname(__DIR__, 3) . '/components/sorters/edit_modal.php';
include dirname(__DIR__, 3) . '/components/sorters/archive_modal.php';
include dirname(__DIR__, 3) . '/components/sorters/view_modal.php';

function formatReadableDate($raw) {
  return !empty($raw) ? date('F j, Y', strtotime($raw)) : '';
}

function formatNumberShort($value) {
  $value = floatval($value);
  if (abs($value - round($value)) < 0.00001) return number_format($value, 0);
  return number_format($value, 2);
}

$rawStartInput = trim((string)($_GET['date_start'] ?? ''));
$rawEndInput = trim((string)($_GET['date_end'] ?? ''));
$dateStart = $rawStartInput !== '' ? security_parse_date_to_ymd($rawStartInput) : null;
$dateEnd = $rawEndInput !== '' ? security_parse_date_to_ymd($rawEndInput) : null;
$displayDateStart = $dateStart ? formatReadableDate($dateStart) : '';
$displayDateEnd = $dateEnd ? formatReadableDate($dateEnd) : '';
$hasInvalidFilter = ($rawStartInput !== '' && $dateStart === null) || ($rawEndInput !== '' && $dateEnd === null);

$rates = [
  'puti' => 0,
  'assorted' => 0,
  'karton' => 0,
  'pet' => 0,
  'sibak' => 0,
  'lata' => 0,
  'aluminum' => 0,
  'bakal' => 0,
  'yero' => 0,
  'glass' => 0,
];

$startBound = $dateStart ?? '2000-01-01';
$endBound = $dateEnd ?? '2100-12-31';
if ($startBound > $endBound) {
  [$startBound, $endBound] = [$endBound, $startBound];
}

$totalputi = $totalassorted = $totalkarton = $totalpet = $totalsibak = 0;
$totallata = $totalaluminum = $totalbakal = $totalyero = $totalglass = 0;

$stmtTotals = $conn->prepare(
  "SELECT
      COALESCE(SUM(puti), 0) AS totalputi,
      COALESCE(SUM(assorted), 0) AS totalassorted,
      COALESCE(SUM(karton), 0) AS totalkarton,
      COALESCE(SUM(pet), 0) AS totalpet,
      COALESCE(SUM(sibak), 0) AS totalsibak,
      COALESCE(SUM(lata), 0) AS totallata,
      COALESCE(SUM(aluminum), 0) AS totalaluminum,
      COALESCE(SUM(bakal), 0) AS totalbakal,
      COALESCE(SUM(yero), 0) AS totalyero,
      COALESCE(SUM(glass), 0) AS totalglass
   FROM scavenger
   WHERE archived = 0
     AND DATE(date) BETWEEN ? AND ?"
);

if ($stmtTotals) {
  $stmtTotals->bind_param('ss', $startBound, $endBound);
  $stmtTotals->execute();
  $resTotals = $stmtTotals->get_result();
  if ($resTotals && ($sumRow = $resTotals->fetch_assoc())) {
    $totalputi = (float)($sumRow['totalputi'] ?? 0);
    $totalassorted = (float)($sumRow['totalassorted'] ?? 0);
    $totalkarton = (float)($sumRow['totalkarton'] ?? 0);
    $totalpet = (float)($sumRow['totalpet'] ?? 0);
    $totalsibak = (float)($sumRow['totalsibak'] ?? 0);
    $totallata = (float)($sumRow['totallata'] ?? 0);
    $totalaluminum = (float)($sumRow['totalaluminum'] ?? 0);
    $totalbakal = (float)($sumRow['totalbakal'] ?? 0);
    $totalyero = (float)($sumRow['totalyero'] ?? 0);
    $totalglass = (float)($sumRow['totalglass'] ?? 0);
  }
  $stmtTotals->close();
} else {
  error_log('sorters totals query prepare failed: ' . $conn->error);
}

$phpputi = $totalputi * (float)($rates['puti'] ?? 0);
$phpassorted = $totalassorted * (float)($rates['assorted'] ?? 0);
$phpkarton = $totalkarton * (float)($rates['karton'] ?? 0);
$phppet = $totalpet * (float)($rates['pet'] ?? 0);
$phpsibak = $totalsibak * (float)($rates['sibak'] ?? 0);
$phplata = $totallata * (float)($rates['lata'] ?? 0);
$phpaluminum = $totalaluminum * (float)($rates['aluminum'] ?? 0);
$phpbakal = $totalbakal * (float)($rates['bakal'] ?? 0);
$phpyero = $totalyero * (float)($rates['yero'] ?? 0);
$phpglass = $totalglass * (float)($rates['glass'] ?? 0);

$overallkls = $totalputi + $totalassorted + $totalkarton + $totalpet + $totalsibak + $totallata + $totalaluminum + $totalbakal + $totalyero + $totalglass;
$overallphp = $phpputi + $phpassorted + $phpkarton + $phppet + $phpsibak + $phplata + $phpaluminum + $phpbakal + $phpyero + $phpglass;
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
              <li class="breadcrumb-item active" aria-current="page">Sorters</li>
            </ol>
          </nav>
        </div>
      </div>
    </div>

    <form method="GET" action="<?= url_with_base('modules/mrf/sorters/index.php') ?>" class="row g-3 align-items-end mb-4 px-3 form-group-card">
      <div class="col-md-4">
        <label for="date_start_input" class="form-label fw-semibold"><i class="fa fa-calendar-alt me-1 text-primary"></i> Date Start</label>
        <div class="input-clear-wrapper position-relative">
          <input type="text" name="date_start" id="date_start_input" class="form-control form-control-sm rounded shadow-sm"
            placeholder="Select start date"
            value="<?= htmlspecialchars($displayDateStart, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" readonly>
          <button type="button" class="clear-btn position-absolute top-50 end-0 translate-middle-y me-2 btn btn-sm btn-light border"
            data-clear="start" aria-label="Clear start date">&times;</button>
        </div>
      </div>

      <div class="col-md-4">
        <label for="date_end_input" class="form-label fw-semibold"><i class="fa fa-calendar-alt me-1 text-primary"></i> Date End</label>
        <div class="input-clear-wrapper position-relative">
          <input type="text" name="date_end" id="date_end_input" class="form-control form-control-sm rounded shadow-sm"
            placeholder="Select end date"
            value="<?= htmlspecialchars($displayDateEnd, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" readonly>
          <button type="button" class="clear-btn position-absolute top-50 end-0 translate-middle-y me-2 btn btn-sm btn-light border"
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
      <div class="alert alert-warning mx-3 mt-1 mb-3 py-2">Invalid date filter ignored. Please use a valid date.</div>
    <?php endif; ?>

    <div class="container-fluid">
      <div class="row">
        <div class="col-12">
          <div class="card fade-in">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="card-title mb-0">Sorter In and Out Record</h4>
                <button type="button" class="btn btn-add-record" data-bs-toggle="modal" data-bs-target="#addRecordModal">
                  <i class="fa fa-plus me-2"></i> Add Record
                </button>
              </div>

        <div class="table-modern-wrap sorters-no-scroll-shell">
          <table
            id="file_export"
            data-source-url="<?= htmlspecialchars(url_with_base('modules/mrf/sorters/sorter_record_table_data.php'), ENT_QUOTES, 'UTF-8') ?>"
            data-date-start="<?= htmlspecialchars($startBound, ENT_QUOTES, 'UTF-8') ?>"
            data-date-end="<?= htmlspecialchars($endBound, ENT_QUOTES, 'UTF-8') ?>"
            class="table table-modern table-bordered table-hover table-sm text-center align-middle w-100 sorters-no-scroll-table"
          >
            <thead class="table-light">
                    <tr>
                      <th>#</th>
                      <th>Name</th>
                      <th>Date</th>
                      <th>Type of Sorter</th>
                      <th>Papel Puti</th>
                      <th>Papel Assorted</th>
                      <th>Karton</th>
                      <th>PET Bottle</th>
                      <th>Sibak (Plastic)</th>
                      <th>Lata</th>
                      <th>Aluminum</th>
                      <th>Bakal</th>
                      <th>Yero</th>
                      <th>Glass Bottles</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                <tbody></tbody>
                  <tfoot>
                    <tr class="table-light fw-semibold">
                      <td class="text-start fw-bold" colspan="4">
                        TOTAL (BEFORE<br>&amp; AFTER<br>MULTIPLICATION)<br>(KLS | PHP):
                      </td>
                      <td><?= formatNumberShort($totalputi) ?> KLS | <?= formatNumberShort($phpputi) ?> PHP</td>
                      <td><?= formatNumberShort($totalassorted) ?> KLS | <?= formatNumberShort($phpassorted) ?> PHP</td>
                      <td><?= formatNumberShort($totalkarton) ?> KLS | <?= formatNumberShort($phpkarton) ?> PHP</td>
                      <td><?= formatNumberShort($totalpet) ?> KLS | <?= formatNumberShort($phppet) ?> PHP</td>
                      <td><?= formatNumberShort($totalsibak) ?> KLS | <?= formatNumberShort($phpsibak) ?> PHP</td>
                      <td><?= formatNumberShort($totallata) ?> KLS | <?= formatNumberShort($phplata) ?> PHP</td>
                      <td><?= formatNumberShort($totalaluminum) ?> KLS | <?= formatNumberShort($phpaluminum) ?> PHP</td>
                      <td><?= formatNumberShort($totalbakal) ?> KLS | <?= formatNumberShort($phpbakal) ?> PHP</td>
                      <td><?= formatNumberShort($totalyero) ?> KLS | <?= formatNumberShort($phpyero) ?> PHP</td>
                      <td><?= formatNumberShort($totalglass) ?> KLS | <?= formatNumberShort($phpglass) ?> PHP</td>
                      <td></td>
                    </tr>
                    <tr class="table-light fw-bold">
                      <td class="text-start fw-bold" colspan="4">
                        OVERALL<br>COMPUTED<br>TOTAL (KLS | PHP):
                      </td>
                      <td class="text-center fw-bold" colspan="11">
                        <?= formatNumberShort($overallkls) ?> KLS | <?= formatNumberShort($overallphp) ?> PHP
                      </td>
                    </tr>
                  </tfoot>
                </table>
              </div> <!-- table-modern-wrap -->
            </div> <!-- card-body -->
          </div> <!-- card -->
        </div> <!-- col -->
      </div> <!-- row -->
    </div> <!-- container-fluid -->
  </div> <!-- page-breadcrumb -->
</div> <!-- page-wrapper -->

<script>
const archivedData = <?= json_encode($data ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>


<?php include dirname(__DIR__, 3) . '/includes/footer_scripts.php'; ?>
</body>
</html>


