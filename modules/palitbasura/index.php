<?php
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';
require_once __DIR__ . '/helpers.php';

requirePermission('palitbasura.view');

$activeCols = [
    'pet_bottles',
    'sachet',
    'galon_tubig',
    'carton',
    'papel',
    'bote_litro',
    'bote_mantika',
    'bote_longneck',
    'bakal',
    'lata',
];
pb_handle_actions($conn, 'palit_basura', $activeCols);

$range = pb_date_range();
$rows = pb_fetch_active($conn, 'palit_basura', $range['start'], $range['end']);
$archivedRows = pb_fetch_archived($conn, 'palit_basura');
$flash = pb_pop_flash();

$totalPet = pb_sum($rows, 'pet_bottles');
$totalSachet = pb_sum($rows, 'sachet');
$totalGalon = pb_sum($rows, 'galon_tubig');
$totalCarton = pb_sum($rows, 'carton');
$totalPapel = pb_sum($rows, 'papel');
$totalBoteLitro = pb_sum($rows, 'bote_litro');
$totalBoteMantika = pb_sum($rows, 'bote_mantika');
$totalBoteLongneck = pb_sum($rows, 'bote_longneck');
$totalBakal = pb_sum($rows, 'bakal');
$totalLata = pb_sum($rows, 'lata');

include dirname(__DIR__, 2) . '/includes/header.php';
include dirname(__DIR__, 2) . '/includes/topbar_sidebar.php';
?>

<style>
  .table-modern-wrap {
    width: 100%;
  }
</style>

<div class="page-wrapper">
  <div class="page-breadcrumb">
    <div class="row">
      <div class="col-5 align-self-center">
        <h4 class="page-title fw-normal text-dark">PALIT BASURA</h4>
      </div>
      <div class="col-7 align-self-center">
        <div class="d-flex align-items-center justify-content-end">
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent mb-0 p-0">
              <li class="breadcrumb-item"><a href="<?= url_with_base('dashboard.php') ?>" class="text-primary">Home</a></li>
              <li class="breadcrumb-item active text-muted" aria-current="page">Palit Basura</li>
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
            <h4 class="card-title mb-0">Palit Basura Record</h4>
            <p class="card-description">Track recyclable item counts and archive records when needed.</p>
          </div>
          <div class="app-page-actions">
            <button type="button" class="btn btn-add-record" data-bs-toggle="modal" data-bs-target="#addRecordModal">
              <i class="fa fa-plus me-2"></i> Add Record
            </button>
          </div>
        </div>

        <div class="table-modern-wrap">
          <table id="file_export" class="table table-modern table-bordered table-hover align-middle text-center table-sm w-100">
            <thead class="text-nowrap">
              <tr>
                <th>#</th>
                <th>DATE</th>
                <th>NAME</th>
                <th>ADDRESS</th>
                <th>PET</th>
                <th>SACHET</th>
                <th>GALON</th>
                <th>CARTON</th>
                <th>PAPEL</th>
                <th>LITRO</th>
                <th>MANTIKA</th>
                <th>LONGNECK</th>
                <th>BAKAL</th>
                <th>LATA</th>
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
                    <td><?= pb_fmt_qty((float)$row['pet_bottles']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['sachet']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['galon_tubig']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['carton']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['papel']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['bote_litro']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['bote_mantika']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['bote_longneck']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['bakal']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['lata']) ?></td>
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
                              data-pet-bottles="<?= htmlspecialchars((string)$row['pet_bottles'], ENT_QUOTES, 'UTF-8') ?>"
                              data-sachet="<?= htmlspecialchars((string)$row['sachet'], ENT_QUOTES, 'UTF-8') ?>"
                              data-galon-tubig="<?= htmlspecialchars((string)$row['galon_tubig'], ENT_QUOTES, 'UTF-8') ?>"
                              data-carton="<?= htmlspecialchars((string)$row['carton'], ENT_QUOTES, 'UTF-8') ?>"
                              data-papel="<?= htmlspecialchars((string)$row['papel'], ENT_QUOTES, 'UTF-8') ?>"
                              data-bote-litro="<?= htmlspecialchars((string)$row['bote_litro'], ENT_QUOTES, 'UTF-8') ?>"
                              data-bote-mantika="<?= htmlspecialchars((string)$row['bote_mantika'], ENT_QUOTES, 'UTF-8') ?>"
                              data-bote-longneck="<?= htmlspecialchars((string)$row['bote_longneck'], ENT_QUOTES, 'UTF-8') ?>"
                              data-bakal="<?= htmlspecialchars((string)$row['bakal'], ENT_QUOTES, 'UTF-8') ?>"
                              data-lata="<?= htmlspecialchars((string)$row['lata'], ENT_QUOTES, 'UTF-8') ?>">
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
                <td><?= pb_fmt_qty($totalPet) ?></td>
                <td><?= pb_fmt_qty($totalSachet) ?></td>
                <td><?= pb_fmt_qty($totalGalon) ?></td>
                <td><?= pb_fmt_qty($totalCarton) ?></td>
                <td><?= pb_fmt_qty($totalPapel) ?></td>
                <td><?= pb_fmt_qty($totalBoteLitro) ?></td>
                <td><?= pb_fmt_qty($totalBoteMantika) ?></td>
                <td><?= pb_fmt_qty($totalBoteLongneck) ?></td>
                <td><?= pb_fmt_qty($totalBakal) ?></td>
                <td><?= pb_fmt_qty($totalLata) ?></td>
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
        <h5 class="modal-title" id="addRecordModalLabel"><i class="fa fa-plus me-2"></i>Add Palit Basura Record</h5>
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

          <div class="col-md-3"><label class="form-label">PET Bottles</label><input type="number" step="1" min="0" name="pet_bottles" class="form-control" value="0"></div>
          <div class="col-md-3"><label class="form-label">Sachet</label><input type="number" step="1" min="0" name="sachet" class="form-control" value="0"></div>
          <div class="col-md-3"><label class="form-label">Galon ng Tubig</label><input type="number" step="1" min="0" name="galon_tubig" class="form-control" value="0"></div>
          <div class="col-md-3"><label class="form-label">Carton</label><input type="number" step="1" min="0" name="carton" class="form-control" value="0"></div>

          <div class="col-md-3"><label class="form-label">Papel</label><input type="number" step="1" min="0" name="papel" class="form-control" value="0"></div>
          <div class="col-md-3"><label class="form-label">Bote Litro</label><input type="number" step="1" min="0" name="bote_litro" class="form-control" value="0"></div>
          <div class="col-md-3"><label class="form-label">Bote ng Mantika</label><input type="number" step="1" min="0" name="bote_mantika" class="form-control" value="0"></div>
          <div class="col-md-3"><label class="form-label">Bote ng Longneck</label><input type="number" step="1" min="0" name="bote_longneck" class="form-control" value="0"></div>

          <div class="col-md-3"><label class="form-label">Bakal</label><input type="number" step="1" min="0" name="bakal" class="form-control" value="0"></div>
          <div class="col-md-3"><label class="form-label">Lata</label><input type="number" step="1" min="0" name="lata" class="form-control" value="0"></div>
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
        <h5 class="modal-title" id="editRecordModalLabel"><i class="fa fa-edit me-2"></i>Edit Palit Basura Record</h5>
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

          <div class="col-md-3"><label class="form-label">PET Bottles</label><input type="number" step="1" min="0" name="pet_bottles" id="edit_pet_bottles" class="form-control"></div>
          <div class="col-md-3"><label class="form-label">Sachet</label><input type="number" step="1" min="0" name="sachet" id="edit_sachet" class="form-control"></div>
          <div class="col-md-3"><label class="form-label">Galon ng Tubig</label><input type="number" step="1" min="0" name="galon_tubig" id="edit_galon_tubig" class="form-control"></div>
          <div class="col-md-3"><label class="form-label">Carton</label><input type="number" step="1" min="0" name="carton" id="edit_carton" class="form-control"></div>

          <div class="col-md-3"><label class="form-label">Papel</label><input type="number" step="1" min="0" name="papel" id="edit_papel" class="form-control"></div>
          <div class="col-md-3"><label class="form-label">Bote Litro</label><input type="number" step="1" min="0" name="bote_litro" id="edit_bote_litro" class="form-control"></div>
          <div class="col-md-3"><label class="form-label">Bote ng Mantika</label><input type="number" step="1" min="0" name="bote_mantika" id="edit_bote_mantika" class="form-control"></div>
          <div class="col-md-3"><label class="form-label">Bote ng Longneck</label><input type="number" step="1" min="0" name="bote_longneck" id="edit_bote_longneck" class="form-control"></div>

          <div class="col-md-3"><label class="form-label">Bakal</label><input type="number" step="1" min="0" name="bakal" id="edit_bakal" class="form-control"></div>
          <div class="col-md-3"><label class="form-label">Lata</label><input type="number" step="1" min="0" name="lata" id="edit_lata" class="form-control"></div>
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
        <h5 class="modal-title" id="archivedModalLabel"><i class="fa fa-archive me-2"></i>Archived Palit Basura Records</h5>
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
                <th>PET</th>
                <th>SACHET</th>
                <th>GALON</th>
                <th>CARTON</th>
                <th>PAPEL</th>
                <th>BOTE LITRO</th>
                <th>BOTE MANTIKA</th>
                <th>BOTE LONGNECK</th>
                <th>BAKAL</th>
                <th>LATA</th>
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
                    <td><?= pb_fmt_qty((float)$row['pet_bottles']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['sachet']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['galon_tubig']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['carton']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['papel']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['bote_litro']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['bote_mantika']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['bote_longneck']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['bakal']) ?></td>
                    <td><?= pb_fmt_qty((float)$row['lata']) ?></td>
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
                  <td colspan="15" class="text-center text-muted py-3">No archived records.</td>
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
      pet_bottles: document.getElementById('edit_pet_bottles'),
      sachet: document.getElementById('edit_sachet'),
      galon_tubig: document.getElementById('edit_galon_tubig'),
      carton: document.getElementById('edit_carton'),
      papel: document.getElementById('edit_papel'),
      bote_litro: document.getElementById('edit_bote_litro'),
      bote_mantika: document.getElementById('edit_bote_mantika'),
      bote_longneck: document.getElementById('edit_bote_longneck'),
      bakal: document.getElementById('edit_bakal'),
      lata: document.getElementById('edit_lata')
    };

    document.querySelectorAll('.edit_btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!editModalEl) return;
        if (editFields.id) editFields.id.value = btn.dataset.id || '';
        if (editFields.name) editFields.name.value = btn.dataset.name || '';
        if (editFields.address) editFields.address.value = btn.dataset.address || '';
        if (editFields.pet_bottles) editFields.pet_bottles.value = btn.dataset.petBottles || '0';
        if (editFields.sachet) editFields.sachet.value = btn.dataset.sachet || '0';
        if (editFields.galon_tubig) editFields.galon_tubig.value = btn.dataset.galonTubig || '0';
        if (editFields.carton) editFields.carton.value = btn.dataset.carton || '0';
        if (editFields.papel) editFields.papel.value = btn.dataset.papel || '0';
        if (editFields.bote_litro) editFields.bote_litro.value = btn.dataset.boteLitro || '0';
        if (editFields.bote_mantika) editFields.bote_mantika.value = btn.dataset.boteMantika || '0';
        if (editFields.bote_longneck) editFields.bote_longneck.value = btn.dataset.boteLongneck || '0';
        if (editFields.bakal) editFields.bakal.value = btn.dataset.bakal || '0';
        if (editFields.lata) editFields.lata.value = btn.dataset.lata || '0';

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

