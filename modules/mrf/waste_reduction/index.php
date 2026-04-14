<?php
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';

requirePermission('mrf.waste_reduction');

include dirname(__DIR__, 3) . '/includes/header.php';
include dirname(__DIR__, 3) . '/includes/topbar_sidebar.php';

include dirname(__DIR__, 3) . '/components/waste_reduction/add_modal.php';
include dirname(__DIR__, 3) . '/components/waste_reduction/edit_modal.php';
include dirname(__DIR__, 3) . '/components/waste_reduction/archive_modal.php';
include dirname(__DIR__, 3) . '/components/waste_reduction/view_modal.php';

function formatReadableDate($raw)
{
  return !empty($raw) ? date('F j, Y', strtotime($raw)) : '';
}

$rawStartInput = trim((string)($_GET['date_start'] ?? ''));
$rawEndInput = trim((string)($_GET['date_end'] ?? ''));
$dateStart = $rawStartInput !== '' ? security_parse_date_to_ymd($rawStartInput) : null;
$dateEnd = $rawEndInput !== '' ? security_parse_date_to_ymd($rawEndInput) : null;
$displayDateStart = $dateStart ? formatReadableDate($dateStart) : '';
$displayDateEnd = $dateEnd ? formatReadableDate($dateEnd) : '';
$hasInvalidFilter = ($rawStartInput !== '' && $dateStart === null) || ($rawEndInput !== '' && $dateEnd === null);

$knownWasteTypes = [
  'SHREDDED PLASTIC WASTE',
  'SHREDDED COCO HUSK',
  'PULVERIZED GLASS',
  'WOOD CHIPPED',
];

$totalsByType = [];
foreach ($knownWasteTypes as $type) {
  $totalsByType[$type] = ['before' => 0.0, 'after' => 0.0];
}

$otherTotalsByType = [];
$overallBefore = 0.0;
$overallAfter = 0.0;
$records = [];

$startBound = $dateStart ?? '2000-01-01';
$endBound = $dateEnd ?? '2100-12-31';
if ($startBound > $endBound) {
  [$startBound, $endBound] = [$endBound, $startBound];
}
$stmtList = $conn->prepare(
  "SELECT *
   FROM `waste_reduction` r
   WHERE r.archived = 0
     AND DATE(r.date) BETWEEN ? AND ?
   ORDER BY r.date ASC"
);
if ($stmtList) {
  $stmtList->bind_param('ss', $startBound, $endBound);
  $stmtList->execute();
  $qry = $stmtList->get_result();
  while ($qry && ($row = $qry->fetch_assoc())) {
    $records[] = $row;

    $type = strtoupper(trim($row['other_waste'] ?? ''));
    $before = floatval($row['kgs_before'] ?? 0);
    $after = floatval($row['kgs_after'] ?? 0);

    $overallBefore += $before;
    $overallAfter += $after;

    if (isset($totalsByType[$type])) {
      $totalsByType[$type]['before'] += $before;
      $totalsByType[$type]['after'] += $after;
    } elseif ($type !== '') {
      if (!isset($otherTotalsByType[$type])) {
        $otherTotalsByType[$type] = ['before' => 0.0, 'after' => 0.0];
      }
      $otherTotalsByType[$type]['before'] += $before;
      $otherTotalsByType[$type]['after'] += $after;
    }
  }
  $stmtList->close();
} else {
  error_log('waste_reduction index query prepare failed: ' . $conn->error);
}

$summaryTypes = array_merge($knownWasteTypes, array_keys($otherTotalsByType));
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
              <li class="breadcrumb-item active" aria-current="page">Composting Machine</li>
            </ol>
          </nav>
        </div>
      </div>
    </div>

    <form method="GET" class="row g-3 align-items-end mb-4 px-3 form-group-card">
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
                <h4 class="card-title mb-0">Composting Machine</h4>
                <button type="button" class="btn btn-add-record" data-bs-toggle="modal" data-bs-target="#addRecordModal">
                  <i class="fa fa-plus me-2"></i> Add Record
                </button>
              </div>

              <div class="table-modern-wrap">
                <table id="file_export" class="table table-modern table-bordered table-hover align-middle text-center table-sm w-100">
                  <colgroup>
                    <col style="width: 4%;">
                    <col style="width: 14%;">
                    <col style="width: 18%;">
                    <col style="width: 22%;">
                    <col style="width: 12%;">
                    <col style="width: 12%;">
                    <col style="width: 10%;">
                  </colgroup>
                  <thead class="table-light">
                    <tr>
                      <th>#</th>
                      <th>Date</th>
                      <th>Name</th>
                      <th>Other Wastes</th>
                      <th>Kgs Before</th>
                      <th>Kgs After</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($records)): ?>
                      <?php $i = 1; foreach ($records as $row): ?>
                        <tr>
                          <td><?= $i++ ?></td>
                          <td><?= formatReadableDate($row['date'] ?? '') ?></td>
                          <td class="enro-name-cell">
                            <div class="enro-name-inline">
                              <button class="btn-eye view_details mb-2"
                                data-bs-toggle="tooltip" data-bs-placement="top" title="View Record"
                                data-id="<?= $row['id'] ?>">
                                <i class="fa fa-eye"></i>
                              </button>
                              <span class="enro-name-text" title="<?= htmlspecialchars((string)($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars((string)($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                              </span>
                            </div>
                          </td>
                          <td><?= htmlspecialchars($row['other_waste'] ?? '') ?></td>
                          <td><?= number_format(floatval($row['kgs_before'] ?? 0), 2) ?></td>
                          <td><?= number_format(floatval($row['kgs_after'] ?? 0), 2) ?></td>
                          <td>
                            <div class="dropdown">
                              <button class="btn btn-outline-dark btn-sm dropdown-toggle rounded-pill px-3"
                                type="button" id="dropdownMenu<?= $row['id'] ?>" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions">
                                <i class="fa fa-cog me-1 spinning-gear always-spin"></i>
                              </button>
                              <ul class="dropdown-menu dropdown-menu-end shadow rounded-3 p-1" aria-labelledby="dropdownMenu<?= $row['id'] ?>">
                                <li>
                                  <a class="dropdown-item d-flex align-items-center gap-2 edit_btn" href="javascript:void(0)"
                                    data-id="<?= $row['id'] ?>"
                                    data-date="<?= htmlspecialchars($row['date'] ?? '') ?>"
                                    data-name="<?= htmlspecialchars($row['name'] ?? '') ?>"
                                    data-other_waste="<?= htmlspecialchars($row['other_waste'] ?? '') ?>"
                                    data-kgs_before="<?= htmlspecialchars($row['kgs_before'] ?? 0) ?>"
                                    data-kgs_after="<?= htmlspecialchars($row['kgs_after'] ?? 0) ?>">
                                    <i class="fa fa-pen text-primary"></i> <span>Edit</span>
                                  </a>
                                </li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li>
                                  <a class="dropdown-item d-flex align-items-center gap-2 archive_data text-warning" href="javascript:void(0)"
                                    data-id="<?= $row['id'] ?>">
                                    <i class="fa fa-archive"></i> <span>Archive</span>
                                  </a>
                                </li>
                              </ul>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="7" class="text-center text-muted py-4">No records found for the selected filters.</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="container-fluid mt-4">
      <div class="row">
        <div class="col-12">
          <div class="card fade-in">
            <div class="card-body">
              <div class="table-modern-wrap">
                <table class="table table-modern table-bordered table-hover align-middle text-center table-sm w-100">
                  <thead class="table-light">
                    <tr>
                      <th>Waste Type</th>
                      <th>Before (Kgs)</th>
                      <th>After (Kgs)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($summaryTypes as $type): ?>
                      <?php
                        $bucket = $totalsByType[$type] ?? ($otherTotalsByType[$type] ?? ['before' => 0.0, 'after' => 0.0]);
                        $before = floatval($bucket['before'] ?? 0);
                        $after = floatval($bucket['after'] ?? 0);
                      ?>
                      <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($type) ?></td>
                        <td class="fw-semibold"><?= number_format($before, 2) ?> KGS</td>
                        <td class="fw-semibold"><?= number_format($after, 2) ?> KGS</td>
                      </tr>
                    <?php endforeach; ?>

                    <tr class="table-light">
                      <td class="fw-bold">TOTAL</td>
                      <td class="fw-bold"><?= number_format($overallBefore, 2) ?> KGS</td>
                      <td class="fw-bold"><?= number_format($overallAfter, 2) ?> KGS</td>
                    </tr>
                    <tr>
                      <td class="fw-bold" colspan="2">OVERALL TOTAL (KGS BEFORE + AFTER)</td>
                      <td class="fw-bold"><?= number_format($overallBefore + $overallAfter, 2) ?> KGS</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  const archivedData = <?= json_encode($data ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<?php include dirname(__DIR__, 3) . '/includes/footer_scripts.php'; ?>
</body>
</html>

