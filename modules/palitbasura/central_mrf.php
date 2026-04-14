<?php
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';
require_once __DIR__ . '/helpers.php';

requirePermission('palitbasura.view');

$activeCols = [
    'soft_plastic',
    'aling_tindera_amount',
    'client_amount',
];
pb_handle_actions($conn, 'aling_tindera_central', $activeCols);

$range = pb_date_range();
$rows = pb_fetch_active($conn, 'aling_tindera_central', $range['start'], $range['end']);
$archivedRows = pb_fetch_archived($conn, 'aling_tindera_central');
$flash = pb_pop_flash();

$totalSoftPlastic = pb_sum($rows, 'soft_plastic');
$totalAlingTindera = pb_sum($rows, 'aling_tindera_amount');
$totalClient = pb_sum($rows, 'client_amount');

include dirname(__DIR__, 2) . '/includes/header.php';
include dirname(__DIR__, 2) . '/includes/topbar_sidebar.php';
?>

<div class="page-wrapper">
  <div class="page-breadcrumb">
    <div class="row">
      <div class="col-5 align-self-center">
        <h4 class="page-title fw-normal text-dark">ALING TINDERA (Central MRF)</h4>
      </div>
      <div class="col-7 align-self-center">
        <div class="d-flex align-items-center justify-content-end">
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent mb-0 p-0">
              <li class="breadcrumb-item"><a href="<?= url_with_base('dashboard.php') ?>" class="text-primary">Home</a></li>
              <li class="breadcrumb-item"><a href="<?= url_with_base('modules/palitbasura/index.php') ?>" class="text-primary">Palit Basura</a></li>
              <li class="breadcrumb-item active text-muted" aria-current="page">Aling Tindera (Central MRF)</li>
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
            value="<?= htmlspecialchars($range['start_display'], ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" readonly>
          <button type="button" class="clear-btn position-absolute top-50 end-0 translate-middle-y me-2 btn btn-sm btn-light border"
            data-clear="start" aria-label="Clear start date">&times;</button>
        </div>
      </div>

      <div class="col-md-4">
        <label for="date_end_input" class="form-label fw-semibold"><i class="fa fa-calendar-alt me-1 text-primary"></i> Date End</label>
        <div class="input-clear-wrapper position-relative">
          <input type="text" name="date_end" id="date_end_input" class="form-control form-control-sm rounded shadow-sm"
            placeholder="Select end date"
            value="<?= htmlspecialchars($range['end_display'], ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" readonly>
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

  <div class="container-fluid">
    <?php if (!empty($flash['message'])): ?>
      <div class="alert alert-<?= htmlspecialchars((string)($flash['variant'] ?? 'info'), ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars((string)$flash['message'], ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <div class="card fade-in">
      <div class="card-body">
        <div class="app-page-header">
          <div>
            <h4 class="card-title mb-0">Aling Tindera (Central MRF) Record</h4>
            <p class="card-description">Central MRF entries for soft plastic and payout totals.</p>
          </div>
          <div class="app-page-actions">
            <button type="button" class="btn btn-add-record" data-bs-toggle="modal" data-bs-target="#addRecordModal">
              <i class="fa fa-plus me-2"></i> Add Record
            </button>
          </div>
        </div>

        <div class="table-modern-wrap">
          <table id="file_export" class="table table-modern table-bordered table-hover align-middle text-center table-sm w-100">
            <thead class="table-light text-nowrap">
              <tr>
                <th>#</th>
                <th>DATE</th>
                <th>NAME</th>
                <th>ADDRESS</th>
                <th>SOFT PLASTIC</th>
                <th>ALING TINDERA</th>
                <th>CLIENT</th>
                <th>ACTION</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($rows)): ?>
                <?php $i = 1; foreach ($rows as $row): ?>
                  <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars(date('Y-m-d', strtotime((string)$row['record_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$row['address'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= pb_fmt_qty((float)$row['soft_plastic']) ?> KLS</td>
                    <td><?= pb_fmt_money((float)$row['aling_tindera_amount']) ?></td>
                    <td><?= pb_fmt_money((float)$row['client_amount']) ?></td>
                    <td>
                      <div class="dropdown">
                        <button class="btn btn-outline-dark btn-sm rounded-circle d-inline-flex align-items-center justify-content-center" type="button"
                          id="dropdownMenu<?= (int)$row['id'] ?>" data-bs-toggle="dropdown"
                          aria-expanded="false" aria-label="Actions">
                          <i class="fa fa-cog spinning-gear always-spin"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow rounded-3 p-1" aria-labelledby="dropdownMenu<?= (int)$row['id'] ?>">
                          <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 edit_btn" href="javascript:void(0)"
                              data-id="<?= (int)$row['id'] ?>"
                              data-record-date="<?= htmlspecialchars(date('m/d/Y', strtotime((string)$row['record_date'])), ENT_QUOTES, 'UTF-8') ?>"
                              data-name="<?= htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8') ?>"
                              data-address="<?= htmlspecialchars((string)$row['address'], ENT_QUOTES, 'UTF-8') ?>"
                              data-soft-plastic="<?= htmlspecialchars((string)$row['soft_plastic'], ENT_QUOTES, 'UTF-8') ?>"
                              data-aling-tindera-amount="<?= htmlspecialchars((string)$row['aling_tindera_amount'], ENT_QUOTES, 'UTF-8') ?>"
                              data-client-amount="<?= htmlspecialchars((string)$row['client_amount'], ENT_QUOTES, 'UTF-8') ?>">
                              <i class="fa fa-edit"></i>
                              <span>Edit</span>
                            </a>
                          </li>
                          <li><hr class="dropdown-divider my-1"></li>
                          <li>
                            <form method="POST" onsubmit="return confirm('Archive this record?');">
                              <?= csrf_input(); ?>
                              <input type="hidden" name="action" value="archive">
                              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                              <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-warning">
                                <i class="fa fa-archive"></i><span>Archive</span>
                              </button>
                            </form>
                          </li>
                        </ul>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
            <tfoot>
              <tr class="table-light fw-semibold text-nowrap">
                <td class="text-start fw-bold">TOTAL:</td>
                <td colspan="3"></td>
                <td><?= pb_fmt_qty($totalSoftPlastic) ?> KLS</td>
                <td><?= pb_fmt_money($totalAlingTindera) ?></td>
                <td><?= pb_fmt_money($totalClient) ?></td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addRecordModal" tabindex="-1" aria-labelledby="addRecordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <form method="POST" class="modal-content rounded-4 shadow">
      <?= csrf_input(); ?>
      <input type="hidden" name="action" value="add">

      <div class="modal-header bg-primary text-white rounded-top-4">
        <h5 class="modal-title" id="addRecordModalLabel"><i class="fa fa-plus me-2"></i>Add Aling Tindera (Central MRF) Record</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Date</label>
            <input type="text" name="record_date" id="record_date" class="form-control" value="<?= htmlspecialchars(date('m/d/Y'), ENT_QUOTES, 'UTF-8') ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Address</label>
            <input type="text" name="address" class="form-control" required>
          </div>

          <div class="col-md-4"><label class="form-label">Soft Plastic (KLS)</label><input type="number" step="0.01" min="0" name="soft_plastic" class="form-control" value="0.00"></div>
          <div class="col-md-4"><label class="form-label">Aling Tindera Amount</label><input type="number" step="0.01" min="0" name="aling_tindera_amount" class="form-control" value="0.00"></div>
          <div class="col-md-4"><label class="form-label">Client Amount</label><input type="number" step="0.01" min="0" name="client_amount" class="form-control" value="0.00"></div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Record</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="editRecordModal" tabindex="-1" aria-labelledby="editRecordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <form method="POST" class="modal-content rounded-4 shadow">
      <?= csrf_input(); ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="edit_id">

      <div class="modal-header bg-primary text-white rounded-top-4">
        <h5 class="modal-title" id="editRecordModalLabel"><i class="fa fa-edit me-2"></i>Edit Aling Tindera (Central MRF) Record</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Date</label>
            <input type="text" name="record_date" id="edit_record_date" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Name</label>
            <input type="text" name="name" id="edit_name" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Address</label>
            <input type="text" name="address" id="edit_address" class="form-control" required>
          </div>

          <div class="col-md-4"><label class="form-label">Soft Plastic (KLS)</label><input type="number" step="0.01" min="0" name="soft_plastic" id="edit_soft_plastic" class="form-control"></div>
          <div class="col-md-4"><label class="form-label">Aling Tindera Amount</label><input type="number" step="0.01" min="0" name="aling_tindera_amount" id="edit_aling_tindera_amount" class="form-control"></div>
          <div class="col-md-4"><label class="form-label">Client Amount</label><input type="number" step="0.01" min="0" name="client_amount" id="edit_client_amount" class="form-control"></div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Record</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="archivedModal" tabindex="-1" aria-labelledby="archivedModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title" id="archivedModalLabel"><i class="fa fa-archive me-2"></i>Archived Aling Tindera (Central MRF) Records</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle text-center">
            <thead class="table-light text-nowrap">
              <tr>
                <th>#</th>
                <th>DATE</th>
                <th>NAME</th>
                <th>ADDRESS</th>
                <th>SOFT PLASTIC</th>
                <th>ALING TINDERA</th>
                <th>CLIENT</th>
                <th>RESTORE</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($archivedRows)): ?>
                <?php $ai = 1; foreach ($archivedRows as $row): ?>
                  <tr>
                    <td><?= $ai++ ?></td>
                    <td><?= htmlspecialchars(date('Y-m-d', strtotime((string)$row['record_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$row['address'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= pb_fmt_qty((float)$row['soft_plastic']) ?></td>
                    <td><?= pb_fmt_money((float)$row['aling_tindera_amount']) ?></td>
                    <td><?= pb_fmt_money((float)$row['client_amount']) ?></td>
                    <td>
                      <form method="POST" onsubmit="return confirm('Restore this archived record?');">
                        <?= csrf_input(); ?>
                        <input type="hidden" name="action" value="unarchive">
                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-success"><i class="fa fa-undo me-1"></i> Restore</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="8" class="text-center text-muted py-3">No archived records.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer_scripts.php'; ?>
<script>
  (function () {
    if (typeof flatpickr !== 'function') return;

    const startInput = document.getElementById('date_start_input');
    const endInput = document.getElementById('date_end_input');
    const recordInput = document.getElementById('record_date');
    const editRecordInput = document.getElementById('edit_record_date');

    const startPicker = startInput ? flatpickr(startInput, { dateFormat: 'm/d/Y', allowInput: true }) : null;
    const endPicker = endInput ? flatpickr(endInput, { dateFormat: 'm/d/Y', allowInput: true }) : null;
    const editRecordPicker = editRecordInput ? flatpickr(editRecordInput, {
      altInput: true,
      altFormat: 'F j, Y',
      dateFormat: 'm/d/Y',
      allowInput: true
    }) : null;

    if (recordInput) {
      flatpickr(recordInput, {
        altInput: true,
        altFormat: 'F j, Y',
        dateFormat: 'm/d/Y',
        allowInput: true
      });
    }

    document.querySelectorAll('[data-clear]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (btn.dataset.clear === 'start' && startPicker) startPicker.clear();
        if (btn.dataset.clear === 'end' && endPicker) endPicker.clear();
      });
    });

    const editModalEl = document.getElementById('editRecordModal');
    const editFields = {
      id: document.getElementById('edit_id'),
      name: document.getElementById('edit_name'),
      address: document.getElementById('edit_address'),
      soft_plastic: document.getElementById('edit_soft_plastic'),
      aling_tindera_amount: document.getElementById('edit_aling_tindera_amount'),
      client_amount: document.getElementById('edit_client_amount')
    };

    document.querySelectorAll('.edit_btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!editModalEl) return;
        if (editFields.id) editFields.id.value = btn.dataset.id || '';
        if (editFields.name) editFields.name.value = btn.dataset.name || '';
        if (editFields.address) editFields.address.value = btn.dataset.address || '';
        if (editFields.soft_plastic) editFields.soft_plastic.value = btn.dataset.softPlastic || '0';
        if (editFields.aling_tindera_amount) editFields.aling_tindera_amount.value = btn.dataset.alingTinderaAmount || '0';
        if (editFields.client_amount) editFields.client_amount.value = btn.dataset.clientAmount || '0';

        const editDate = btn.dataset.recordDate || '';
        if (editRecordPicker) {
          editRecordPicker.setDate(editDate, true, 'm/d/Y');
        } else if (editRecordInput) {
          editRecordInput.value = editDate;
        }

        if (typeof bootstrap !== 'undefined') {
          bootstrap.Modal.getOrCreateInstance(editModalEl).show();
        }
      });
    });
  })();
</script>
</body>
</html>

