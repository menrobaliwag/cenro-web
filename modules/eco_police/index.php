<?php
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

requirePermission('eco.violators');

// Layout includes (absolute paths)
include dirname(__DIR__, 2) . '/includes/header.php';
include dirname(__DIR__, 2) . '/includes/topbar_sidebar.php';

// Modals (adjust if needed)
include dirname(__DIR__, 2) . '/components/eco_police/add_modal.php';
include dirname(__DIR__, 2) . '/components/eco_police/edit_modal.php';
include dirname(__DIR__, 2) . '/components/eco_police/archive_modal.php';
include dirname(__DIR__, 2) . '/components/eco_police/view_modal.php';

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
                            <li class="breadcrumb-item active" aria-current="page">Violator List</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <!-- FILTER FORM -->
        <form method="GET" class="row g-3 align-items-end mb-4 px-3 form-group-card">
            <div class="col-md-4">
                <label for="date_start_input" class="form-label fw-semibold"><i class="fa fa-calendar-alt me-1 text-primary"></i> Date Start</label>
                <div class="input-clear-wrapper position-relative">
                    <input type="text" name="date_start" id="date_start_input" 
                        class="form-control form-control-sm rounded shadow-sm"
                        placeholder="Select start date"
                        value="<?php echo formatReadableDate($_GET['date_start'] ?? date('Y-m-d')); ?>" 
                        autocomplete="off" readonly>
                    <button type="button" class="clear-btn position-absolute top-50 end-0 translate-middle-y me-2 btn btn-sm btn-light border"
                        data-clear="start" aria-label="Clear start date">&times;</button>
                </div>
            </div>

            <div class="col-md-4">
                <label for="date_end_input" class="form-label fw-semibold"><i class="fa fa-calendar-alt me-1 text-primary"></i> Date End</label>
                <div class="input-clear-wrapper position-relative">
                    <input type="text" name="date_end" id="date_end_input" 
                        class="form-control form-control-sm rounded shadow-sm"
                        placeholder="Select end date"
                        value="<?php echo formatReadableDate($_GET['date_end'] ?? date('Y-m-d')); ?>" 
                        autocomplete="off" readonly>
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
    </div>

    <!-- TABLE -->
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card fade-in">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="card-title mb-0">Violator Record</h4>
                            <button type="button" class="btn btn-add-record" data-bs-toggle="modal" data-bs-target="#addRecordModal">
                                <i class="fa fa-plus me-2"></i> Add Record
                            </button>
                        </div>

                        <div class="scrollable-table-wrapper">
                            <table id="file_export" class="table table-bordered table-hover align-middle text-center table-sm nowrap w-100">
                                <colgroup>
                                    <col style="width: 4%;">
                                    <col style="width: 12%;">
                                    <col style="width: 12%;">
                                    <col style="width: 10%;">
                                    <col style="width: 12%;">
                                    <col style="width: 10%;">
                                    <col style="width: 10%;">
                                </colgroup>
                                <thead class="table-light text-nowrap">
                                    <tr>
                                        <th>#</th>
                                        <th>Date Of Violation</th>
                                        <th>Full Name</th>
                                        <th>ID Type</th>
                                        <th>Place of Violation</th>
                                        <th>Penalty Type</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody class="text-nowrap">
                                <?php
                                    $raw_start = (string)($_GET['date_start'] ?? '');
                                    $raw_end   = (string)($_GET['date_end'] ?? '');

                                    $date_start = security_parse_date_to_ymd($raw_start) ?? date('Y-m-d');
                                    $date_end   = security_parse_date_to_ymd($raw_end) ?? date('Y-m-d');
                                    if ($date_start > $date_end) {
                                        [$date_start, $date_end] = [$date_end, $date_start];
                                    }

                                    $i = 1;

                                    $stmt = $conn->prepare(
                                        "SELECT * FROM violations r
                                         WHERE r.archived = 0 AND DATE(r.date_created) BETWEEN ? AND ?
                                         ORDER BY r.date_created ASC"
                                    );
                                    $qry = false;
                                    if ($stmt) {
                                        $stmt->bind_param('ss', $date_start, $date_end);
                                        $stmt->execute();
                                        $qry = $stmt->get_result();
                                    }

                                    while ($qry && ($row = $qry->fetch_assoc())):
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
                                        <span><?= htmlspecialchars((string)($row['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </td>
                                        <td><?= htmlspecialchars((string)($row['id_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string)($row['place_of_violation'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string)($row['penalty_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-outline-dark btn-sm dropdown-toggle rounded-pill px-2"
                                                        type="button" data-bs-toggle="dropdown">
                                                        <i class="fa fa-cog spinning-gear always-spin"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow rounded-3 p-1">
                                                        <li>
                                                            <a class="dropdown-item edit_btn"
                                                                href="javascript:void(0)"
                                                                data-id="<?= $row['id'] ?>"
                                                                data-id_number="<?= htmlspecialchars((string)($row['id_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                                data-full_name="<?= htmlspecialchars((string)($row['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                                data-date_created="<?= date('Y-m-d', strtotime($row['date_created'])) ?>"
                                                                data-address="<?= htmlspecialchars((string)($row['address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                                data-contact_number="<?= htmlspecialchars((string)($row['contact_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                                data-id_type="<?= htmlspecialchars((string)($row['id_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                                data-other_id="<?= htmlspecialchars((string)($row['other_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                                data-place_of_violation="<?= htmlspecialchars((string)($row['place_of_violation'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                                data-penalty_type="<?= htmlspecialchars((string)($row['penalty_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                                data-id_image="<?= htmlspecialchars((string)($row['id_image'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                <i class="fa fa-pen text-primary"></i> Edit
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider my-1"></li>
                                                        <li>
                                                            <a class="dropdown-item text-warning archive_data"
                                                                href="javascript:void(0)"
                                                                data-id="<?= $row['id'] ?>">
                                                                <i class="fa fa-archive"></i> Archive
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; if ($stmt) { $stmt->close(); } ?>
                                </tbody>
                            </table>
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
<?php include dirname(__DIR__, 2) . '/includes/footer_scripts.php'; ?>
</body>
</html>

