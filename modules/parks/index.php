<?php
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

requirePermission('parks.view');

include dirname(__DIR__, 2) . '/includes/header.php';
include dirname(__DIR__, 2) . '/includes/topbar_sidebar.php';
?>

<div class="page-wrapper">
  <div class="page-breadcrumb">
    <div class="row">
      <div class="col-5 align-self-center">
        <h4 class="page-title fw-normal text-dark">Parks and Monument</h4>
      </div>
      <div class="col-7 align-self-center">
        <div class="d-flex align-items-center justify-content-end">
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent mb-0 p-0">
              <li class="breadcrumb-item"><a href="<?= url_with_base('dashboard.php') ?>" class="text-primary">Home</a></li>
              <li class="breadcrumb-item active text-muted" aria-current="page">Parks and Monument</li>
            </ol>
          </nav>
        </div>
      </div>
    </div>
  </div>

  <div class="container-fluid">
    <div class="row">
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
              <div>
                <h5 class="card-title mb-2">Parks Module</h5>
                <p class="text-muted mb-0">Manage land management files and records for parks and wildlife.</p>
              </div>
              <a href="<?= url_with_base('modules/parks/files.php') ?>" class="btn btn-outline-success btn-sm">Open Land Management Folder</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer_scripts.php'; ?>
</body>
</html>

