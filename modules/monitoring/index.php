<?php
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

requirePermission('monitoring.view');
$roleKey = normalize_role_key((string)($_SESSION['role'] ?? ''));
if ($roleKey !== 'head_admin') {
    header('Location: ' . url_with_base('includes/403.php'));
    exit;
}

include dirname(__DIR__, 2) . '/includes/header.php';
include dirname(__DIR__, 2) . '/includes/topbar_sidebar.php';

$totalLogs = 0;
$todayLogs = 0;
$last7DaysLogs = 0;
$rows = [];
$queryError = '';

if (isset($conn) && $conn instanceof mysqli) {
    $q1 = @$conn->query('SELECT COUNT(*) AS c FROM audit_logs');
    if ($q1) {
        $totalLogs = (int)($q1->fetch_assoc()['c'] ?? 0);
    }

    $q2 = @$conn->query('SELECT COUNT(*) AS c FROM audit_logs WHERE DATE(created_at) = CURDATE()');
    if ($q2) {
        $todayLogs = (int)($q2->fetch_assoc()['c'] ?? 0);
    }

    $q3 = @$conn->query('SELECT COUNT(*) AS c FROM audit_logs WHERE created_at >= (NOW() - INTERVAL 7 DAY)');
    if ($q3) {
        $last7DaysLogs = (int)($q3->fetch_assoc()['c'] ?? 0);
    }

    $q4 = @$conn->query(
        "SELECT id, created_at, user_name, module, action, entity_type, entity_id, description
         FROM audit_logs
         ORDER BY created_at DESC
         LIMIT 300"
    );

    if ($q4) {
        while ($r = $q4->fetch_assoc()) {
            $rows[] = $r;
        }
    } else {
        $queryError = 'Audit logs table is not available yet. Run sql/audit_logs.sql first.';
    }
}
?>

<div class="page-wrapper">
  <div class="page-breadcrumb">
    <div class="row">
      <div class="col-5 align-self-center">
        <h4 class="page-title fw-normal text-dark">System Monitoring</h4>
      </div>
      <div class="col-7 align-self-center">
        <div class="d-flex align-items-center justify-content-end">
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent mb-0 p-0">
              <li class="breadcrumb-item"><a href="<?= url_with_base('dashboard.php') ?>" class="text-primary">Home</a></li>
              <li class="breadcrumb-item active text-muted" aria-current="page">System Monitoring</li>
            </ol>
          </nav>
        </div>
      </div>
    </div>
  </div>

  <div class="container-fluid">
    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted small">Total Logs</div>
            <div class="h4 mb-0"><?= number_format($totalLogs) ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted small">Today</div>
            <div class="h4 mb-0"><?= number_format($todayLogs) ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted small">Last 7 Days</div>
            <div class="h4 mb-0"><?= number_format($last7DaysLogs) ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
              <h5 class="card-title mb-0">Recent Activity Logs</h5>
              <a href="<?= url_with_base('modules/monitoring/audit_center.php') ?>" class="btn btn-outline-primary btn-sm">Open Session & Audit Center</a>
            </div>

            <?php if ($queryError !== ''): ?>
              <div class="alert alert-warning mb-0"><?= htmlspecialchars($queryError, ENT_QUOTES, 'UTF-8') ?></div>
            <?php else: ?>
              <div class="table-responsive">
                <table id="file_export" class="table table-bordered table-striped align-middle text-center w-100">
                  <thead class="table-light text-nowrap">
                    <tr>
                      <th>ID</th>
                      <th>Date/Time</th>
                      <th>User</th>
                      <th>Module</th>
                      <th>Action</th>
                      <th>Entity</th>
                      <th>Description</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($rows)): ?>
                      <tr>
                        <td colspan="7" class="text-muted py-4">No activity logs found.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($rows as $r): ?>
                        <?php
                          $entity = trim((string)($r['entity_type'] ?? ''));
                          $entityId = trim((string)($r['entity_id'] ?? ''));
                          if ($entity !== '' && $entityId !== '') {
                              $entity .= ' #' . $entityId;
                          } elseif ($entityId !== '') {
                              $entity = '#' . $entityId;
                          }
                        ?>
                        <tr>
                          <td><?= (int)($r['id'] ?? 0) ?></td>
                          <td class="text-nowrap"><?= !empty($r['created_at']) ? htmlspecialchars(date('M j, Y g:i A', strtotime((string)$r['created_at'])), ENT_QUOTES, 'UTF-8') : '' ?></td>
                          <td><?= htmlspecialchars((string)($r['user_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                          <td><?= htmlspecialchars((string)($r['module'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                          <td><?= htmlspecialchars((string)($r['action'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                          <td><?= htmlspecialchars($entity, ENT_QUOTES, 'UTF-8') ?></td>
                          <td class="text-start"><?= htmlspecialchars((string)($r['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer_scripts.php'; ?>
</body>
</html>

