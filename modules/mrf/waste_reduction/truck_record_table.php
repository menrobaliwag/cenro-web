
<?php
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';

requirePermission('mrf.truck');

include dirname(__DIR__, 3) . '/includes/header.php';
include dirname(__DIR__, 3) . '/includes/topbar_sidebar.php';

include dirname(__DIR__, 3) . '/components/other_waste/add_modal.php';
include dirname(__DIR__, 3) . '/components/other_waste/edit_modal.php';
include dirname(__DIR__, 3) . '/components/other_waste/archive_modal.php';
include dirname(__DIR__, 3) . '/components/other_waste/view_modal.php';

function formatReadableDate($raw) {
  return !empty($raw) ? date('F j, Y', strtotime($raw)) : '';
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
        <label for="date_start_input" class="form-label fw-semibold"><i class="fa fa-calendar-alt me-1 text-primary"></i> Date Start</label>
        <div class="input-clear-wrapper position-relative">
          <input type="text" name="date_start" id="date_start_input" class="form-control form-control-sm rounded shadow-sm"
            placeholder="Select start date"
            value="<?php echo formatReadableDate($_GET['date_start'] ?? date('Y-m-d')); ?>" autocomplete="off" readonly>
          <button type="button" class="clear-btn position-absolute top-50 end-0 translate-middle-y me-2 btn btn-sm btn-light border"
            data-clear="start" aria-label="Clear start date">&times;</button>
        </div>
      </div>

      <div class="col-md-4">
        <label for="date_end_input" class="form-label fw-semibold"><i class="fa fa-calendar-alt me-1 text-primary"></i> Date End</label>
        <div class="input-clear-wrapper position-relative">
          <input type="text" name="date_end" id="date_end_input" class="form-control form-control-sm rounded shadow-sm"
            placeholder="Select end date"
            value="<?php echo formatReadableDate($_GET['date_end'] ?? date('Y-m-d')); ?>" autocomplete="off" readonly>
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
            <table id="file_export" class="table table-modern table-bordered table-hover align-middle text-center table-sm w-100">
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
              <tbody>
                  <?php
                    $raw_start = (string)($_GET['date_start'] ?? '');
                    $raw_end = (string)($_GET['date_end'] ?? '');

                    $date_start = security_parse_date_to_ymd($raw_start) ?? date('Y-m-d');
                    $date_end = security_parse_date_to_ymd($raw_end) ?? date('Y-m-d');
                    if ($date_start > $date_end) {
                      [$date_start, $date_end] = [$date_end, $date_start];
                    }

                    $i = 1;

                    $stmt = $conn->prepare(
                      "SELECT * FROM `files` r
                       WHERE r.archived = 0 AND DATE(r.date_created) BETWEEN ? AND ?
                       ORDER BY r.date_created ASC"
                    );
                    $qry = false;
                    if ($stmt) {
                      $stmt->bind_param('ss', $date_start, $date_end);
                      $stmt->execute();
                      $qry = $stmt->get_result();
                    }

                    while ($qry && ($row = $qry->fetch_assoc())) {
                      $time_in = strtotime($row['date_created'] . ' ' . $row['time_in']);
                      $date_out = !empty($row['date_out']) ? $row['date_out'] : $row['date_created'];
                      $time_out = strtotime($date_out . ' ' . $row['time_out']);
                      if ($time_out < $time_in) $time_out += 86400;

                      $total_seconds = $time_out - $time_in;
                      $total_hours = $total_seconds / 3600;
                      $hours = floor($total_hours);
                      $minutes = round(($total_hours - $hours) * 60);
                      $duration = "$hours hr" . ($minutes > 0 ? " $minutes min" : '');
                      $bg = 'text-dark';
                  ?>
                    <tr>
                      <td><?= $i++ ?></td>
                      <td><?= date('F j, Y', strtotime($row['date_created'])) ?></td>
                      <td>
                        <button class="btn-eye view_details mb-2"
                          data-bs-toggle="tooltip" data-bs-placement="top" title="View Record"
                          data-id="<?= $row['id'] ?>">
                          <i class="fa fa-eye"></i>
                        </button>
                        <small class="text-muted"><?= date('h:i A', $time_in) ?></small>
                      </td>
                      <td><small class="text-muted"><?= date('h:i A', $time_out) ?></small></td>
                      <td><span class="badge <?= $bg ?> px-3 py-2 rounded-pill"><?= $duration ?></span></td>
                      <td><?= htmlspecialchars((string)($row['truck'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars((string)($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars((string)($row['area'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars((string)($row['dumping'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                      <td>
                        <div class="dropdown">
                          <button class="btn btn-outline-dark btn-sm dropdown-toggle rounded-pill px-3"
                            type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-cog me-1 spinning-gear always-spin"></i>
                          </button>
                          <ul class="dropdown-menu dropdown-menu-end shadow rounded-3 p-1">
                            <li>
                              <a class="dropdown-item d-flex align-items-center gap-2 edit_btn" href="javascript:void(0)"
                                data-id="<?= $row['id'] ?>"
                                data-date="<?= date('Y-m-d', strtotime($row['date_created'])) ?>"
                                data-timein="<?= $row['time_in'] ?>"
                                data-timeout="<?= $row['time_out'] ?>"
                                data-truck="<?= htmlspecialchars((string)($row['truck'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-name="<?= htmlspecialchars((string)($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-area="<?= htmlspecialchars((string)($row['area'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-dumping="<?= htmlspecialchars((string)($row['dumping'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
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
                  <?php } if ($stmt) { $stmt->close(); } ?>
                  </tbody>
                </table>
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

