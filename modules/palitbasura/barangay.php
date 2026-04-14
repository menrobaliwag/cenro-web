<?php
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';
require_once __DIR__ . '/helpers.php';

requirePermission('palitbasura.view');

$activeCols = [
    'colored_plastic_bottles',
    'panligo',
    'tarpaulins',
    'cleared_plastic_bottles',
    'sachets_kg',
    'sando_bags',
    'styrofoam',
    'amount',
];
pb_handle_actions($conn, 'aling_tindera_barangay', $activeCols);

$range = pb_date_range();
$rows = pb_fetch_active($conn, 'aling_tindera_barangay', $range['start'], $range['end']);
$archivedRows = pb_fetch_archived($conn, 'aling_tindera_barangay');
$flash = pb_pop_flash();

$totalColored = pb_sum($rows, 'colored_plastic_bottles');
$totalPanligo = pb_sum($rows, 'panligo');
$totalTarpaulins = pb_sum($rows, 'tarpaulins');
$totalCleared = pb_sum($rows, 'cleared_plastic_bottles');
$totalSachets = pb_sum($rows, 'sachets_kg');
$totalSando = pb_sum($rows, 'sando_bags');
$totalStyro = pb_sum($rows, 'styrofoam');
$totalAmount = pb_sum($rows, 'amount');
$totalKls = pb_sum_many($rows, [
    'colored_plastic_bottles',
    'panligo',
    'tarpaulins',
    'cleared_plastic_bottles',
    'sachets_kg',
    'sando_bags',
    'styrofoam',
]);

include dirname(__DIR__, 2) . '/includes/header.php';
include dirname(__DIR__, 2) . '/includes/topbar_sidebar.php';
?>

<div class="page-wrapper">
  <div class="page-breadcrumb">
    <div class="row">
      <div class="col-5 align-self-center">
        <h4 class="page-title fw-normal text-dark">ALING TINDERA</h4>
      </div>
      <div class="col-7 align-self-center">
        <div class="d-flex align-items-center justify-content-end">
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent mb-0 p-0">
              <li class="breadcrumb-item"><a href="<?= url_with_base('dashboard.php') ?>" class="text-primary">Home</a></li>
              <li class="breadcrumb-item"><a href="<?= url_with_base('modules/palitbasura/index.php') ?>" class="text-primary">Palit Basura</a></li>
              <li class="breadcrumb-item active text-muted" aria-current="page">Aling Tindera (Barangay)</li>
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
            <h4 class="card-title mb-0">Aling Tindera (Barangay) Record</h4>
            <p class="card-description">Daily barangay-level entries with computed total amount.</p>
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
                <th>COLORED</th>
                <th>PANLIGO</th>
                <th>TARPAULINS</th>
                <th>CLEARED</th>
                <th>SACHETS</th>
                <th>SANDO</th>
                <th>STYROFOAM</th>
                <th>AMOUNT</th>
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
                    <td><?= pb_fmt_qty((float)$row['colored_plastic_bottles']) ?> KLS</td>
                    <td><?= pb_fmt_qty((float)$row['panligo']) ?> KLS</td>
                    <td><?= pb_fmt_qty((float)$row['tarpaulins']) ?> KLS</td>
                    <td><?= pb_fmt_qty((float)$row['cleared_plastic_bottles']) ?> KLS</td>
                    <td><?= pb_fmt_qty((float)$row['sachets_kg']) ?> KLS</td>
                    <td><?= pb_fmt_qty((float)$row['sando_bags']) ?> KLS</td>
                    <td><?= pb_fmt_qty((float)$row['styrofoam']) ?> KLS</td>
                    <td><?= pb_fmt_money((float)$row['amount']) ?></td>
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
                              data-colored-plastic-bottles="<?= htmlspecialchars((string)$row['colored_plastic_bottles'], ENT_QUOTES, 'UTF-8') ?>"
                              data-panligo="<?= htmlspecialchars((string)$row['panligo'], ENT_QUOTES, 'UTF-8') ?>"
                              data-tarpaulins="<?= htmlspecialchars((string)$row['tarpaulins'], ENT_QUOTES, 'UTF-8') ?>"
                              data-cleared-plastic-bottles="<?= htmlspecialchars((string)$row['cleared_plastic_bottles'], ENT_QUOTES, 'UTF-8') ?>"
                              data-sachets-kg="<?= htmlspecialchars((string)$row['sachets_kg'], ENT_QUOTES, 'UTF-8') ?>"
                              data-sando-bags="<?= htmlspecialchars((string)$row['sando_bags'], ENT_QUOTES, 'UTF-8') ?>"
                              data-styrofoam="<?= htmlspecialchars((string)$row['styrofoam'], ENT_QUOTES, 'UTF-8') ?>">
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
                <td colspan="4" class="text-start fw-bold">TOTAL:</td>
                <td><?= pb_fmt_qty($totalColored) ?> KLS</td>
                <td><?= pb_fmt_qty($totalPanligo) ?> KLS</td>
                <td><?= pb_fmt_qty($totalTarpaulins) ?> KLS</td>
                <td><?= pb_fmt_qty($totalCleared) ?> KLS</td>
                <td><?= pb_fmt_qty($totalSachets) ?> KLS</td>
                <td><?= pb_fmt_qty($totalSando) ?> KLS</td>
                <td><?= pb_fmt_qty($totalStyro) ?> KLS</td>
                <td><?= pb_fmt_money($totalAmount) ?></td>
                <td></td>
              </tr>
              <tr class="table-light fw-semibold">
                <td colspan="4" class="text-start fw-bold">OVERALL TOTAL:</td>
                <td colspan="7"><?= pb_fmt_qty($totalKls) ?> KLS</td>
                <td><?= pb_fmt_money($totalAmount) ?></td>
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
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <form method="POST" class="modal-content rounded-4 shadow">
      <?= csrf_input(); ?>
      <input type="hidden" name="action" value="add">

      <div class="modal-header bg-primary text-white rounded-top-4">
        <h5 class="modal-title" id="addRecordModalLabel"><i class="fa fa-plus me-2"></i>Add Aling Tindera (Barangay) Record</h5>
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

          <div class="col-md-3"><label class="form-label">Colored Plastic Bottles (KLS)</label><input type="number" step="0.01" min="0" name="colored_plastic_bottles" id="add_colored_plastic_bottles" class="form-control" value="0.00"></div>
          <div class="col-md-3"><label class="form-label">Panligo (KLS)</label><input type="number" step="0.01" min="0" name="panligo" id="add_panligo" class="form-control" value="0.00"></div>
          <div class="col-md-3"><label class="form-label">Tarpaulins (KLS)</label><input type="number" step="0.01" min="0" name="tarpaulins" id="add_tarpaulins" class="form-control" value="0.00"></div>
          <div class="col-md-3"><label class="form-label">Cleared Plastic Bottles (KLS)</label><input type="number" step="0.01" min="0" name="cleared_plastic_bottles" id="add_cleared_plastic_bottles" class="form-control" value="0.00"></div>

          <div class="col-md-3"><label class="form-label">Sachets (KLS)</label><input type="number" step="0.01" min="0" name="sachets_kg" id="add_sachets_kg" class="form-control" value="0.00"></div>
          <div class="col-md-3"><label class="form-label">Sando Bags (KLS)</label><input type="number" step="0.01" min="0" name="sando_bags" id="add_sando_bags" class="form-control" value="0.00"></div>
          <div class="col-md-3"><label class="form-label">Styrofoam (KLS)</label><input type="number" step="0.01" min="0" name="styrofoam" id="add_styrofoam" class="form-control" value="0.00"></div>
          <div class="col-md-3"><label class="form-label">Total Amount</label><input type="number" step="0.01" min="0" name="amount" id="add_amount" class="form-control bg-light" value="0.00" readonly></div>
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
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <form method="POST" class="modal-content rounded-4 shadow">
      <?= csrf_input(); ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="edit_id">

      <div class="modal-header bg-primary text-white rounded-top-4">
        <h5 class="modal-title" id="editRecordModalLabel"><i class="fa fa-edit me-2"></i>Edit Aling Tindera (Barangay) Record</h5>
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

          <div class="col-md-3"><label class="form-label">Colored Plastic Bottles (KLS)</label><input type="number" step="0.01" min="0" name="colored_plastic_bottles" id="edit_colored_plastic_bottles" class="form-control"></div>
          <div class="col-md-3"><label class="form-label">Panligo (KLS)</label><input type="number" step="0.01" min="0" name="panligo" id="edit_panligo" class="form-control"></div>
          <div class="col-md-3"><label class="form-label">Tarpaulins (KLS)</label><input type="number" step="0.01" min="0" name="tarpaulins" id="edit_tarpaulins" class="form-control"></div>
          <div class="col-md-3"><label class="form-label">Cleared Plastic Bottles (KLS)</label><input type="number" step="0.01" min="0" name="cleared_plastic_bottles" id="edit_cleared_plastic_bottles" class="form-control"></div>

          <div class="col-md-3"><label class="form-label">Sachets (KLS)</label><input type="number" step="0.01" min="0" name="sachets_kg" id="edit_sachets_kg" class="form-control"></div>
          <div class="col-md-3"><label class="form-label">Sando Bags (KLS)</label><input type="number" step="0.01" min="0" name="sando_bags" id="edit_sando_bags" class="form-control"></div>
          <div class="col-md-3"><label class="form-label">Styrofoam (KLS)</label><input type="number" step="0.01" min="0" name="styrofoam" id="edit_styrofoam" class="form-control"></div>
          <div class="col-md-3"><label class="form-label">Total Amount</label><input type="number" step="0.01" min="0" name="amount" id="edit_amount" class="form-control bg-light" readonly></div>
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
        <h5 class="modal-title" id="archivedModalLabel"><i class="fa fa-archive me-2"></i>Archived Aling Tindera (Barangay) Records</h5>
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
                <th>COLORED</th>
                <th>PANLIGO</th>
                <th>TARPAULINS</th>
                <th>CLEARED</th>
                <th>SACHETS</th>
                <th>SANDO</th>
                <th>STYROFOAM</th>
                <th>AMOUNT</th>
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
                    <td><?= pb_fmt_qty((float)$row['colored_plastic_bottles']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['panligo']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['tarpaulins']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['cleared_plastic_bottles']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['sachets_kg']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['sando_bags']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['styrofoam']) ?></td>
                    <td><?= pb_fmt_money((float)$row['amount']) ?></td>
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
                  <td colspan="13" class="text-center text-muted py-3">No archived records.</td>
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
    const addFields = {
      colored_plastic_bottles: document.getElementById('add_colored_plastic_bottles'),
      panligo: document.getElementById('add_panligo'),
      tarpaulins: document.getElementById('add_tarpaulins'),
      cleared_plastic_bottles: document.getElementById('add_cleared_plastic_bottles'),
      sachets_kg: document.getElementById('add_sachets_kg'),
      sando_bags: document.getElementById('add_sando_bags'),
      styrofoam: document.getElementById('add_styrofoam'),
      amount: document.getElementById('add_amount')
    };
    const editFields = {
      id: document.getElementById('edit_id'),
      name: document.getElementById('edit_name'),
      address: document.getElementById('edit_address'),
      colored_plastic_bottles: document.getElementById('edit_colored_plastic_bottles'),
      panligo: document.getElementById('edit_panligo'),
      tarpaulins: document.getElementById('edit_tarpaulins'),
      cleared_plastic_bottles: document.getElementById('edit_cleared_plastic_bottles'),
      sachets_kg: document.getElementById('edit_sachets_kg'),
      sando_bags: document.getElementById('edit_sando_bags'),
      styrofoam: document.getElementById('edit_styrofoam'),
      amount: document.getElementById('edit_amount')
    };

    function toNumber(value) {
      const n = parseFloat(value);
      return Number.isFinite(n) ? n : 0;
    }

    function computeTotalAmount(fields) {
      if (!fields.amount) return;
      const total =
        toNumber(fields.colored_plastic_bottles && fields.colored_plastic_bottles.value) +
        toNumber(fields.panligo && fields.panligo.value) +
        toNumber(fields.tarpaulins && fields.tarpaulins.value) +
        toNumber(fields.cleared_plastic_bottles && fields.cleared_plastic_bottles.value) +
        toNumber(fields.sachets_kg && fields.sachets_kg.value) +
        toNumber(fields.sando_bags && fields.sando_bags.value) +
        toNumber(fields.styrofoam && fields.styrofoam.value);
      fields.amount.value = total.toFixed(2);
    }

    function bindTotalAmount(fields) {
      [
        fields.colored_plastic_bottles,
        fields.panligo,
        fields.tarpaulins,
        fields.cleared_plastic_bottles,
        fields.sachets_kg,
        fields.sando_bags,
        fields.styrofoam
      ].forEach(function (input) {
        if (input) {
          input.addEventListener('input', function () {
            computeTotalAmount(fields);
          });
        }
      });
      computeTotalAmount(fields);
    }

    bindTotalAmount(addFields);
    bindTotalAmount(editFields);

    document.querySelectorAll('.edit_btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!editModalEl) return;
        if (editFields.id) editFields.id.value = btn.dataset.id || '';
        if (editFields.name) editFields.name.value = btn.dataset.name || '';
        if (editFields.address) editFields.address.value = btn.dataset.address || '';
        if (editFields.colored_plastic_bottles) editFields.colored_plastic_bottles.value = btn.dataset.coloredPlasticBottles || '0';
        if (editFields.panligo) editFields.panligo.value = btn.dataset.panligo || '0';
        if (editFields.tarpaulins) editFields.tarpaulins.value = btn.dataset.tarpaulins || '0';
        if (editFields.cleared_plastic_bottles) editFields.cleared_plastic_bottles.value = btn.dataset.clearedPlasticBottles || '0';
        if (editFields.sachets_kg) editFields.sachets_kg.value = btn.dataset.sachetsKg || '0';
        if (editFields.sando_bags) editFields.sando_bags.value = btn.dataset.sandoBags || '0';
        if (editFields.styrofoam) editFields.styrofoam.value = btn.dataset.styrofoam || '0';
        computeTotalAmount(editFields);

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

