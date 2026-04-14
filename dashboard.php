<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/role_utils.php';
require_once __DIR__ . '/config/db.php';

include __DIR__ . '/includes/header.php';
// kung separate pa topbar_sidebar mo:
include __DIR__ . '/includes/topbar_sidebar.php';

// Example counts (only query if allowed)
$totaltruck_record = 0;
$totalscavenger = 0;
$currentRoleKey = resolve_session_role_key();
$modulePermissionKeys = [
  'mrf.truck',
  'mrf.sorters',
  'mrf.other_waste',
  'mrf.waste_reduction',
  'eco.violators',
  'iec.manage',
  'palitbasura.view',
  'parks.view',
  'mbcurp.view',
  'monitoring.view',
];
$hasAssignedModules = is_super_role($currentRoleKey);
if (!$hasAssignedModules) {
  foreach ($modulePermissionKeys as $permKey) {
    if (can($permKey)) {
      $hasAssignedModules = true;
      break;
    }
  }
}

if (can('mrf.truck')) {
  $q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM truck_record");
  $totaltruck_record = $q ? (int)mysqli_fetch_assoc($q)['total'] : 0;
}

if (can('mrf.sorters')) {
  $q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM scavenger");
  $totalscavenger = $q ? (int)mysqli_fetch_assoc($q)['total'] : 0;
}

?>

<div class="page-wrapper">
  <div class="page-breadcrumb">
    <div class="row">
      <div class="col-5 align-self-center">
        <h4 class="page-title fw-normal text-dark">Dashboard</h4>
      </div>
      <div class="col-7 align-self-center">
        <div class="d-flex align-items-center justify-content-end">
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent mb-0 p-0">
              <li class="breadcrumb-item"><a href="<?= url_with_base('dashboard.php') ?>" class="text-primary">Home</a></li>
              <li class="breadcrumb-item active text-muted" aria-current="page">Dashboard</li>
            </ol>
          </nav>
        </div>
      </div>
    </div>
  </div>

  <div class="container-fluid">
    <div class="row g-4">

      <?php if (can('mrf.truck')): ?>
      <div class="col-md-6 col-lg-3">
        <div class="card shadow-lg rounded-4 border-0 bg-light">
          <div class="card-body d-flex align-items-center p-4">
            <div class="me-3">
              <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:60px;height:60px;">
                <i class="mdi mdi-file-document fs-4"></i>
              </div>
            </div>
            <div>
              <h6 class="text-uppercase text-muted mb-1 fw-normal">MRF Uploaded Data</h6>
              <h2 class="mb-0 fw-normal text-dark"><?= $totaltruck_record ?></h2>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if (can('mrf.sorters')): ?>
      <div class="col-md-6 col-lg-3">
        <div class="card shadow-lg rounded-4 border-0 bg-light">
          <div class="card-body d-flex align-items-center p-4">
            <div class="me-3">
              <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width:60px;height:60px;">
                <i class="mdi mdi-image fs-4"></i>
              </div>
            </div>
            <div>
              <h6 class="text-uppercase text-muted mb-1 fw-normal">Sorter Uploaded Data</h6>
              <h2 class="mb-0 fw-normal text-dark"><?= $totalscavenger ?></h2>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- If walang permissions, show a simple message -->
      <?php if (!$hasAssignedModules): ?>
      <div class="col-12">
        <div class="alert alert-warning">
          No modules assigned to your account yet. Please contact the administrator.
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer_scripts.php'; ?>
