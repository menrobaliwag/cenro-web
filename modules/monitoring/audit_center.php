<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';
require_once dirname(__DIR__, 2) . '/includes/role_utils.php';
require_once dirname(__DIR__, 2) . '/includes/session_activity_audit.php';

requirePermission('monitoring.view');

$roleKey = normalize_role_key((string)($_SESSION['role'] ?? ''));
if ($roleKey !== 'head_admin') {
    header('Location: ' . url_with_base('includes/403.php'));
    exit;
}

function ac_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ac_bind_and_fetch(mysqli $conn, string $sql, string $types, array $params): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if ($types !== '' && !empty($params)) {
        $bind = [];
        $bind[] = $types;
        foreach ($params as $key => $val) {
            $bind[] = &$params[$key];
        }
        @call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $res = $stmt->get_result();
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();
    return $rows;
}

function ac_date_start(string $input): string
{
    $input = trim($input);
    if ($input === '') {
        return '';
    }
    $ts = strtotime($input);
    if ($ts === false) {
        return '';
    }
    return date('Y-m-d 00:00:00', $ts);
}

function ac_date_end(string $input): string
{
    $input = trim($input);
    if ($input === '') {
        return '';
    }
    $ts = strtotime($input);
    if ($ts === false) {
        return '';
    }
    return date('Y-m-d 23:59:59', $ts);
}

$flash = ['type' => '', 'text' => ''];
if (isset($_SESSION['audit_center_flash']) && is_array($_SESSION['audit_center_flash'])) {
    $flash = [
        'type' => (string)($_SESSION['audit_center_flash']['type'] ?? ''),
        'text' => (string)($_SESSION['audit_center_flash']['text'] ?? ''),
    ];
    unset($_SESSION['audit_center_flash']);
}

$allowedTabs = ['sessions', 'activity', 'deleted'];
$activeTab = (string)($_GET['tab'] ?? 'sessions');
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'sessions';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();

    $action = (string)($_POST['action'] ?? '');
    $postTab = (string)($_POST['active_tab'] ?? 'sessions');
    if (!in_array($postTab, $allowedTabs, true)) {
        $postTab = 'sessions';
    }

    if ($action === 'force_logout') {
        $sessionRowId = (int)($_POST['session_row_id'] ?? 0);
        $result = force_logout_user_session(
            $sessionRowId,
            (int)($_SESSION['user_id'] ?? 0),
            (string)($_SESSION['user_email'] ?? '')
        );
        $_SESSION['audit_center_flash'] = [
            'type' => $result['ok'] ? 'success' : 'danger',
            'text' => (string)$result['message'],
        ];
    }

    if ($action === 'restore_deleted') {
        $deletedItemId = (int)($_POST['deleted_item_id'] ?? 0);
        $result = restore_deleted_item_with_audit(
            $conn,
            $deletedItemId,
            (int)($_SESSION['user_id'] ?? 0),
            (string)($_SESSION['user_email'] ?? '')
        );
        $_SESSION['audit_center_flash'] = [
            'type' => $result['ok'] ? 'success' : 'danger',
            'text' => (string)$result['message'],
        ];
    }

    if ($action === 'purge_deleted') {
        $deletedItemId = (int)($_POST['deleted_item_id'] ?? 0);
        $result = purge_deleted_item_with_audit(
            $conn,
            $deletedItemId,
            (int)($_SESSION['user_id'] ?? 0),
            (string)($_SESSION['user_email'] ?? '')
        );
        $_SESSION['audit_center_flash'] = [
            'type' => $result['ok'] ? 'success' : 'danger',
            'text' => (string)$result['message'],
        ];
    }

    header('Location: ' . url_with_base('modules/monitoring/audit_center.php?tab=' . rawurlencode($postTab)));
    exit;
}

$sessionRoles = ac_bind_and_fetch($conn, "SELECT DISTINCT role_name FROM user_sessions WHERE role_name <> '' ORDER BY role_name ASC", '', []);
$activityActions = ac_bind_and_fetch($conn, "SELECT DISTINCT action FROM audit_logs WHERE action <> '' ORDER BY action ASC", '', []);
$activityModules = ac_bind_and_fetch($conn, "SELECT DISTINCT module FROM audit_logs WHERE module <> '' ORDER BY module ASC", '', []);
$deletedModules = ac_bind_and_fetch($conn, "SELECT DISTINCT module_name FROM deleted_items WHERE module_name <> '' ORDER BY module_name ASC", '', []);

$sFromInput = (string)($_GET['s_from'] ?? '');
$sToInput = (string)($_GET['s_to'] ?? '');
$sRole = trim((string)($_GET['s_role'] ?? ''));
$sStatus = trim((string)($_GET['s_status'] ?? ''));
$sQuery = trim((string)($_GET['s_q'] ?? ''));

$sessionSql = "
    SELECT id, user_id, email, role_name, login_at, logout_at, status, ip_address, device, location, last_activity_at
    FROM user_sessions
    WHERE 1=1
";
$sessionTypes = '';
$sessionParams = [];

$sFrom = ac_date_start($sFromInput);
if ($sFrom !== '') {
    $sessionSql .= " AND login_at >= ?";
    $sessionTypes .= 's';
    $sessionParams[] = $sFrom;
}
$sTo = ac_date_end($sToInput);
if ($sTo !== '') {
    $sessionSql .= " AND login_at <= ?";
    $sessionTypes .= 's';
    $sessionParams[] = $sTo;
}
if ($sRole !== '') {
    $sessionSql .= " AND role_name = ?";
    $sessionTypes .= 's';
    $sessionParams[] = $sRole;
}
if (in_array($sStatus, ['active', 'expired', 'forced_logout'], true)) {
    $sessionSql .= " AND status = ?";
    $sessionTypes .= 's';
    $sessionParams[] = $sStatus;
}
if ($sQuery !== '') {
    $sessionSql .= " AND email LIKE ?";
    $sessionTypes .= 's';
    $sessionParams[] = '%' . $sQuery . '%';
}
$sessionSql .= " ORDER BY login_at DESC LIMIT 500";
$sessionRows = ac_bind_and_fetch($conn, $sessionSql, $sessionTypes, $sessionParams);

$aFromInput = (string)($_GET['a_from'] ?? '');
$aToInput = (string)($_GET['a_to'] ?? '');
$aAction = trim((string)($_GET['a_action'] ?? ''));
$aModule = trim((string)($_GET['a_module'] ?? ''));
$aQuery = trim((string)($_GET['a_q'] ?? ''));

$activitySql = "
    SELECT id, created_at, email, action, module, record_type, record_id, description, ip_address, location, device, snapshot_json
    FROM audit_logs
    WHERE 1=1
";
$activityTypes = '';
$activityParams = [];

$aFrom = ac_date_start($aFromInput);
if ($aFrom !== '') {
    $activitySql .= " AND created_at >= ?";
    $activityTypes .= 's';
    $activityParams[] = $aFrom;
}
$aTo = ac_date_end($aToInput);
if ($aTo !== '') {
    $activitySql .= " AND created_at <= ?";
    $activityTypes .= 's';
    $activityParams[] = $aTo;
}
if ($aAction !== '') {
    $activitySql .= " AND action = ?";
    $activityTypes .= 's';
    $activityParams[] = $aAction;
}
if ($aModule !== '') {
    $activitySql .= " AND module = ?";
    $activityTypes .= 's';
    $activityParams[] = $aModule;
}
if ($aQuery !== '') {
    $activitySql .= " AND (email LIKE ? OR description LIKE ? OR record_id LIKE ?)";
    $activityTypes .= 'sss';
    $activityParams[] = '%' . $aQuery . '%';
    $activityParams[] = '%' . $aQuery . '%';
    $activityParams[] = '%' . $aQuery . '%';
}
$activitySql .= " ORDER BY created_at DESC LIMIT 1000";
$activityRows = ac_bind_and_fetch($conn, $activitySql, $activityTypes, $activityParams);

$dFromInput = (string)($_GET['d_from'] ?? '');
$dToInput = (string)($_GET['d_to'] ?? '');
$dModule = trim((string)($_GET['d_module'] ?? ''));
$dStatus = trim((string)($_GET['d_status'] ?? ''));
$dQuery = trim((string)($_GET['d_q'] ?? ''));

$deletedSql = "
    SELECT id, module_name, source_table, record_type, record_id, item_identifier,
           deleted_by_email, deleted_reason, record_snapshot, deleted_at, status,
           restored_at, permanently_deleted_at
    FROM deleted_items
    WHERE 1=1
";
$deletedTypes = '';
$deletedParams = [];

$dFrom = ac_date_start($dFromInput);
if ($dFrom !== '') {
    $deletedSql .= " AND deleted_at >= ?";
    $deletedTypes .= 's';
    $deletedParams[] = $dFrom;
}
$dTo = ac_date_end($dToInput);
if ($dTo !== '') {
    $deletedSql .= " AND deleted_at <= ?";
    $deletedTypes .= 's';
    $deletedParams[] = $dTo;
}
if ($dModule !== '') {
    $deletedSql .= " AND module_name = ?";
    $deletedTypes .= 's';
    $deletedParams[] = $dModule;
}
if (in_array($dStatus, ['deleted', 'restored', 'purged'], true)) {
    $deletedSql .= " AND status = ?";
    $deletedTypes .= 's';
    $deletedParams[] = $dStatus;
}
if ($dQuery !== '') {
    $deletedSql .= " AND (item_identifier LIKE ? OR deleted_by_email LIKE ? OR record_id LIKE ?)";
    $deletedTypes .= 'sss';
    $deletedParams[] = '%' . $dQuery . '%';
    $deletedParams[] = '%' . $dQuery . '%';
    $deletedParams[] = '%' . $dQuery . '%';
}
$deletedSql .= " ORDER BY deleted_at DESC LIMIT 700";
$deletedRows = ac_bind_and_fetch($conn, $deletedSql, $deletedTypes, $deletedParams);

$export = (string)($_GET['export'] ?? '');
if ($export === 'activity_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Email', 'Action', 'Module', 'Record Type', 'Record ID', 'Description', 'IP Address', 'Location', 'Device']);
    foreach ($activityRows as $row) {
        fputcsv($out, [
            (string)($row['created_at'] ?? ''),
            (string)($row['email'] ?? ''),
            (string)($row['action'] ?? ''),
            (string)($row['module'] ?? ''),
            (string)($row['record_type'] ?? ''),
            (string)($row['record_id'] ?? ''),
            (string)($row['description'] ?? ''),
            (string)($row['ip_address'] ?? ''),
            (string)($row['location'] ?? ''),
            (string)($row['device'] ?? ''),
        ]);
    }
    fclose($out);
    exit;
}

if ($export === 'activity_pdf') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Activity Logs Export</title>';
    echo '<style>body{font-family:Arial,sans-serif;padding:20px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #d9dee6;padding:6px 8px;font-size:12px;vertical-align:top}th{background:#f1f5f9}h2{margin:0 0 12px}</style>';
    echo '</head><body>';
    echo '<h2>Activity Logs</h2>';
    echo '<table><thead><tr><th>Date</th><th>Email</th><th>Action</th><th>Module</th><th>Record</th><th>Description</th><th>IP</th><th>Location</th><th>Device</th></tr></thead><tbody>';
    foreach ($activityRows as $row) {
        echo '<tr>';
        echo '<td>' . ac_h((string)($row['created_at'] ?? '')) . '</td>';
        echo '<td>' . ac_h((string)($row['email'] ?? '')) . '</td>';
        echo '<td>' . ac_h((string)($row['action'] ?? '')) . '</td>';
        echo '<td>' . ac_h((string)($row['module'] ?? '')) . '</td>';
        echo '<td>' . ac_h((string)($row['record_type'] ?? '') . ' #' . (string)($row['record_id'] ?? '')) . '</td>';
        echo '<td>' . ac_h((string)($row['description'] ?? '')) . '</td>';
        echo '<td>' . ac_h((string)($row['ip_address'] ?? '')) . '</td>';
        echo '<td>' . ac_h((string)($row['location'] ?? '')) . '</td>';
        echo '<td>' . ac_h((string)($row['device'] ?? '')) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table><script>window.print();</script></body></html>';
    exit;
}

include dirname(__DIR__, 2) . '/includes/header.php';
include dirname(__DIR__, 2) . '/includes/topbar_sidebar.php';
?>
<style>
  .audit-center .card { border: 1px solid #dbe3ed; border-radius: 14px; box-shadow: 0 6px 18px rgba(18, 52, 88, 0.06); }
  .audit-center .card-title { color: #1a3e61; font-weight: 700; }
  .audit-center .nav-pills .nav-link { border: 1px solid #d3deea; border-radius: 999px; color: #234a73; font-weight: 600; }
  .audit-center .nav-pills .nav-link.active { color: #fff; border-color: transparent; background: linear-gradient(135deg, #198754, #2b6fd4); }
  .audit-center .filter-card { background: #f8fbff; border: 1px solid #dfebf7; border-radius: 12px; }
  .audit-center .table thead th { font-size: .82rem; color: #5d7388; text-transform: uppercase; letter-spacing: .03em; white-space: nowrap; }
  .audit-center .status-badge { display: inline-flex; align-items: center; padding: .25rem .55rem; border-radius: 999px; font-size: .75rem; font-weight: 700; }
  .audit-center .status-active { background: #def7e8; color: #0f6c3b; }
  .audit-center .status-expired { background: #eef2f7; color: #45596c; }
  .audit-center .status-forced_logout { background: #fde7ea; color: #ab2a3a; }
  .audit-center .status-deleted { background: #fff4e5; color: #925000; }
  .audit-center .status-restored { background: #e8f5ff; color: #0f5f9e; }
  .audit-center .status-purged { background: #f6f6f6; color: #5f646a; }
  .audit-center .btn { border-radius: 10px; }
  .audit-center .mono { font-family: Consolas, 'Courier New', monospace; }
  @media (max-width: 991px) {
    .audit-center .nav-pills { overflow-x: auto; flex-wrap: nowrap; }
    .audit-center .table { min-width: 980px; }
  }
</style>

<div class="page-wrapper">
  <div class="page-breadcrumb">
    <div class="row">
      <div class="col-5 align-self-center">
        <h4 class="page-title fw-normal text-dark">Session & Activity Audit Center</h4>
      </div>
      <div class="col-7 align-self-center">
        <div class="d-flex align-items-center justify-content-end">
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent mb-0 p-0">
              <li class="breadcrumb-item"><a href="<?= url_with_base('dashboard.php') ?>" class="text-primary">Home</a></li>
              <li class="breadcrumb-item active text-muted" aria-current="page">Audit Center</li>
            </ol>
          </nav>
        </div>
      </div>
    </div>
  </div>

  <div class="container-fluid audit-center">
    <?php if ($flash['text'] !== ''): ?>
      <div class="alert alert-<?= ac_h($flash['type']) ?> mb-3"><?= ac_h($flash['text']) ?></div>
    <?php endif; ?>

    <ul class="nav nav-pills gap-2 mb-3" id="auditCenterTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'sessions' ? 'active' : '' ?>" id="tab-sessions" data-bs-toggle="tab" data-bs-target="#pane-sessions" type="button" role="tab" aria-selected="<?= $activeTab === 'sessions' ? 'true' : 'false' ?>">Sessions</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'activity' ? 'active' : '' ?>" id="tab-activity" data-bs-toggle="tab" data-bs-target="#pane-activity" type="button" role="tab" aria-selected="<?= $activeTab === 'activity' ? 'true' : 'false' ?>">Activity Logs</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'deleted' ? 'active' : '' ?>" id="tab-deleted" data-bs-toggle="tab" data-bs-target="#pane-deleted" type="button" role="tab" aria-selected="<?= $activeTab === 'deleted' ? 'true' : 'false' ?>">Deleted Items Review</button>
      </li>
    </ul>

    <div class="tab-content">
      <div class="tab-pane fade <?= $activeTab === 'sessions' ? 'show active' : '' ?>" id="pane-sessions" role="tabpanel" aria-labelledby="tab-sessions">
        <div class="card mb-3 filter-card">
          <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
              <input type="hidden" name="tab" value="sessions">
              <div class="col-sm-6 col-lg-2"><label class="form-label">From</label><input type="date" class="form-control" name="s_from" value="<?= ac_h($sFromInput) ?>"></div>
              <div class="col-sm-6 col-lg-2"><label class="form-label">To</label><input type="date" class="form-control" name="s_to" value="<?= ac_h($sToInput) ?>"></div>
              <div class="col-sm-6 col-lg-2">
                <label class="form-label">Role</label>
                <select class="form-select" name="s_role">
                  <option value="">All</option>
                  <?php foreach ($sessionRoles as $roleRow): $roleVal = (string)($roleRow['role_name'] ?? ''); ?>
                    <option value="<?= ac_h($roleVal) ?>" <?= $sRole === $roleVal ? 'selected' : '' ?>><?= ac_h($roleVal) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-sm-6 col-lg-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="s_status">
                  <option value="">All</option>
                  <option value="active" <?= $sStatus === 'active' ? 'selected' : '' ?>>Active</option>
                  <option value="expired" <?= $sStatus === 'expired' ? 'selected' : '' ?>>Expired</option>
                  <option value="forced_logout" <?= $sStatus === 'forced_logout' ? 'selected' : '' ?>>Forced Logout</option>
                </select>
              </div>
              <div class="col-sm-12 col-lg-3"><label class="form-label">Email Search</label><input type="text" class="form-control" name="s_q" value="<?= ac_h($sQuery) ?>" placeholder="Search by email"></div>
              <div class="col-sm-12 col-lg-1 d-grid"><button class="btn btn-primary" type="submit">Filter</button></div>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-body">
            <h5 class="card-title mb-3">All User Sessions</h5>
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Login Date/Time</th>
                    <th>Logout Date/Time</th>
                    <th>Status</th>
                    <th>IP Address</th>
                    <th>Device/Browser</th>
                    <th>Location</th>
                    <th>Last Activity</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($sessionRows)): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">No session records found.</td></tr>
                  <?php else: ?>
                    <?php foreach ($sessionRows as $row): ?>
                      <?php $statusKey = (string)($row['status'] ?? 'expired'); ?>
                      <tr>
                        <td><?= ac_h((string)($row['email'] ?? '')) ?></td>
                        <td><?= ac_h((string)($row['role_name'] ?? '')) ?></td>
                        <td class="text-nowrap"><?= ac_h((string)($row['login_at'] ?? '')) ?></td>
                        <td class="text-nowrap"><?= ac_h((string)($row['logout_at'] ?? '-')) ?></td>
                        <td><span class="status-badge status-<?= ac_h($statusKey) ?>"><?= ac_h(ucwords(str_replace('_', ' ', $statusKey))) ?></span></td>
                        <td class="mono"><?= ac_h((string)($row['ip_address'] ?? '')) ?></td>
                        <td><?= ac_h((string)($row['device'] ?? '')) ?></td>
                        <td><?= ac_h((string)($row['location'] ?? 'Unknown')) ?></td>
                        <td class="text-nowrap"><?= ac_h((string)($row['last_activity_at'] ?? '')) ?></td>
                        <td>
                          <div class="d-flex flex-wrap gap-2">
                            <?php if ($statusKey === 'active'): ?>
                              <form method="POST" onsubmit="return confirm('Force logout this session?');">
                                <?= csrf_input(); ?>
                                <input type="hidden" name="active_tab" value="sessions">
                                <input type="hidden" name="action" value="force_logout">
                                <input type="hidden" name="session_row_id" value="<?= (int)($row['id'] ?? 0) ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Force Logout</button>
                              </form>
                            <?php endif; ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= url_with_base('modules/monitoring/audit_center.php?tab=activity&a_q=' . rawurlencode((string)($row['email'] ?? ''))) ?>">View User Timeline</a>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div class="tab-pane fade <?= $activeTab === 'activity' ? 'show active' : '' ?>" id="pane-activity" role="tabpanel" aria-labelledby="tab-activity">
        <div class="card mb-3 filter-card">
          <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
              <input type="hidden" name="tab" value="activity">
              <div class="col-sm-6 col-lg-2"><label class="form-label">From</label><input type="date" class="form-control" name="a_from" value="<?= ac_h($aFromInput) ?>"></div>
              <div class="col-sm-6 col-lg-2"><label class="form-label">To</label><input type="date" class="form-control" name="a_to" value="<?= ac_h($aToInput) ?>"></div>
              <div class="col-sm-6 col-lg-2">
                <label class="form-label">Action</label>
                <select class="form-select" name="a_action">
                  <option value="">All</option>
                  <?php foreach ($activityActions as $actionRow): $actionVal = (string)($actionRow['action'] ?? ''); ?>
                    <option value="<?= ac_h($actionVal) ?>" <?= $aAction === $actionVal ? 'selected' : '' ?>><?= ac_h($actionVal) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-sm-6 col-lg-2">
                <label class="form-label">Module</label>
                <select class="form-select" name="a_module">
                  <option value="">All</option>
                  <?php foreach ($activityModules as $moduleRow): $moduleVal = (string)($moduleRow['module'] ?? ''); ?>
                    <option value="<?= ac_h($moduleVal) ?>" <?= $aModule === $moduleVal ? 'selected' : '' ?>><?= ac_h($moduleVal) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-sm-12 col-lg-3"><label class="form-label">Search</label><input type="text" class="form-control" name="a_q" value="<?= ac_h($aQuery) ?>" placeholder="Email, description, record id"></div>
              <div class="col-sm-12 col-lg-1 d-grid"><button class="btn btn-primary" type="submit">Filter</button></div>
            </form>
            <div class="d-flex flex-wrap gap-2 mt-3">
              <a class="btn btn-outline-secondary btn-sm" href="<?= url_with_base('modules/monitoring/audit_center.php?tab=activity&export=activity_csv&a_from=' . rawurlencode($aFromInput) . '&a_to=' . rawurlencode($aToInput) . '&a_action=' . rawurlencode($aAction) . '&a_module=' . rawurlencode($aModule) . '&a_q=' . rawurlencode($aQuery)) ?>">Export CSV</a>
              <a class="btn btn-outline-secondary btn-sm" target="_blank" href="<?= url_with_base('modules/monitoring/audit_center.php?tab=activity&export=activity_pdf&a_from=' . rawurlencode($aFromInput) . '&a_to=' . rawurlencode($aToInput) . '&a_action=' . rawurlencode($aAction) . '&a_module=' . rawurlencode($aModule) . '&a_q=' . rawurlencode($aQuery)) ?>">Export PDF</a>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-body">
            <h5 class="card-title mb-3">Enhanced Activity Logs</h5>
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Email</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th>Record Type</th>
                    <th>Record ID</th>
                    <th>Description</th>
                    <th>IP</th>
                    <th>Location</th>
                    <th>Device</th>
                    <th>Details</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($activityRows)): ?>
                    <tr><td colspan="11" class="text-center text-muted py-4">No activity logs found.</td></tr>
                  <?php else: ?>
                    <?php foreach ($activityRows as $row): ?>
                      <?php $snapshot64 = base64_encode((string)($row['snapshot_json'] ?? '')); ?>
                      <tr>
                        <td class="text-nowrap"><?= ac_h((string)($row['created_at'] ?? '')) ?></td>
                        <td><?= ac_h((string)($row['email'] ?? '')) ?></td>
                        <td><?= ac_h((string)($row['action'] ?? '')) ?></td>
                        <td><?= ac_h((string)($row['module'] ?? '')) ?></td>
                        <td><?= ac_h((string)($row['record_type'] ?? '')) ?></td>
                        <td><?= ac_h((string)($row['record_id'] ?? '')) ?></td>
                        <td><?= ac_h((string)($row['description'] ?? '')) ?></td>
                        <td class="mono"><?= ac_h((string)($row['ip_address'] ?? '')) ?></td>
                        <td><?= ac_h((string)($row['location'] ?? 'Unknown')) ?></td>
                        <td><?= ac_h((string)($row['device'] ?? '')) ?></td>
                        <td>
                          <button
                            type="button"
                            class="btn btn-sm btn-outline-primary js-activity-detail"
                            data-bs-toggle="modal"
                            data-bs-target="#activityDetailsModal"
                            data-id="<?= (int)($row['id'] ?? 0) ?>"
                            data-date="<?= ac_h((string)($row['created_at'] ?? '')) ?>"
                            data-email="<?= ac_h((string)($row['email'] ?? '')) ?>"
                            data-action="<?= ac_h((string)($row['action'] ?? '')) ?>"
                            data-module="<?= ac_h((string)($row['module'] ?? '')) ?>"
                            data-record-type="<?= ac_h((string)($row['record_type'] ?? '')) ?>"
                            data-record-id="<?= ac_h((string)($row['record_id'] ?? '')) ?>"
                            data-description="<?= ac_h((string)($row['description'] ?? '')) ?>"
                            data-ip="<?= ac_h((string)($row['ip_address'] ?? '')) ?>"
                            data-location="<?= ac_h((string)($row['location'] ?? '')) ?>"
                            data-device="<?= ac_h((string)($row['device'] ?? '')) ?>"
                            data-snapshot="<?= ac_h($snapshot64) ?>"
                          >View</button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="tab-pane fade <?= $activeTab === 'deleted' ? 'show active' : '' ?>" id="pane-deleted" role="tabpanel" aria-labelledby="tab-deleted">
        <div class="card mb-3 filter-card">
          <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
              <input type="hidden" name="tab" value="deleted">
              <div class="col-sm-6 col-lg-2"><label class="form-label">From</label><input type="date" class="form-control" name="d_from" value="<?= ac_h($dFromInput) ?>"></div>
              <div class="col-sm-6 col-lg-2"><label class="form-label">To</label><input type="date" class="form-control" name="d_to" value="<?= ac_h($dToInput) ?>"></div>
              <div class="col-sm-6 col-lg-2">
                <label class="form-label">Module</label>
                <select class="form-select" name="d_module">
                  <option value="">All</option>
                  <?php foreach ($deletedModules as $moduleRow): $moduleVal = (string)($moduleRow['module_name'] ?? ''); ?>
                    <option value="<?= ac_h($moduleVal) ?>" <?= $dModule === $moduleVal ? 'selected' : '' ?>><?= ac_h($moduleVal) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-sm-6 col-lg-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="d_status">
                  <option value="">All</option>
                  <option value="deleted" <?= $dStatus === 'deleted' ? 'selected' : '' ?>>Deleted</option>
                  <option value="restored" <?= $dStatus === 'restored' ? 'selected' : '' ?>>Restored</option>
                  <option value="purged" <?= $dStatus === 'purged' ? 'selected' : '' ?>>Purged</option>
                </select>
              </div>
              <div class="col-sm-12 col-lg-3"><label class="form-label">Search</label><input type="text" class="form-control" name="d_q" value="<?= ac_h($dQuery) ?>" placeholder="Item, email, record id"></div>
              <div class="col-sm-12 col-lg-1 d-grid"><button class="btn btn-primary" type="submit">Filter</button></div>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-body">
            <h5 class="card-title mb-3">Deleted Items Review</h5>
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th>Deleted By</th>
                    <th>Module</th>
                    <th>Item Name/Identifier</th>
                    <th>Record ID</th>
                    <th>Deleted At</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($deletedRows)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No deleted items found.</td></tr>
                  <?php else: ?>
                    <?php foreach ($deletedRows as $row): ?>
                      <?php $snapshot64 = base64_encode((string)($row['record_snapshot'] ?? '')); $status = (string)($row['status'] ?? 'deleted'); ?>
                      <tr>
                        <td><?= ac_h((string)($row['deleted_by_email'] ?? '')) ?></td>
                        <td><?= ac_h((string)($row['module_name'] ?? '')) ?></td>
                        <td><?= ac_h((string)($row['item_identifier'] ?? '')) ?></td>
                        <td><?= ac_h((string)($row['record_id'] ?? '')) ?></td>
                        <td class="text-nowrap"><?= ac_h((string)($row['deleted_at'] ?? '')) ?></td>
                        <td><?= ac_h((string)($row['deleted_reason'] ?? '-')) ?></td>
                        <td><span class="status-badge status-<?= ac_h($status) ?>"><?= ac_h(ucwords($status)) ?></span></td>
                        <td>
                          <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary js-snapshot-view" data-bs-toggle="modal" data-bs-target="#snapshotModal" data-snapshot="<?= ac_h($snapshot64) ?>" data-item="<?= ac_h((string)($row['item_identifier'] ?? '')) ?>">View Snapshot</button>
                            <?php if ($status === 'deleted'): ?>
                              <form method="POST" onsubmit="return confirm('Restore this item?');">
                                <?= csrf_input(); ?>
                                <input type="hidden" name="active_tab" value="deleted">
                                <input type="hidden" name="action" value="restore_deleted">
                                <input type="hidden" name="deleted_item_id" value="<?= (int)($row['id'] ?? 0) ?>">
                                <button class="btn btn-sm btn-outline-success" type="submit">Restore</button>
                              </form>
                              <form method="POST" onsubmit="return confirm('Permanently delete this item? This cannot be undone.');">
                                <?= csrf_input(); ?>
                                <input type="hidden" name="active_tab" value="deleted">
                                <input type="hidden" name="action" value="purge_deleted">
                                <input type="hidden" name="deleted_item_id" value="<?= (int)($row['id'] ?? 0) ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Permanently Delete</button>
                              </form>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="activityDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Activity Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <div class="row g-2 mb-3">
          <div class="col-md-6"><div class="small text-muted">Date</div><div id="ad_date" class="fw-semibold"></div></div>
          <div class="col-md-6"><div class="small text-muted">Email</div><div id="ad_email" class="fw-semibold"></div></div>
          <div class="col-md-4"><div class="small text-muted">Action</div><div id="ad_action"></div></div>
          <div class="col-md-4"><div class="small text-muted">Module</div><div id="ad_module"></div></div>
          <div class="col-md-4"><div class="small text-muted">Record</div><div id="ad_record"></div></div>
          <div class="col-md-12"><div class="small text-muted">Description</div><div id="ad_desc"></div></div>
          <div class="col-md-4"><div class="small text-muted">IP</div><div id="ad_ip" class="mono"></div></div>
          <div class="col-md-4"><div class="small text-muted">Location</div><div id="ad_location"></div></div>
          <div class="col-md-4"><div class="small text-muted">Device</div><div id="ad_device"></div></div>
        </div>
        <div class="small text-muted mb-1">Snapshot JSON</div>
        <pre id="ad_snapshot" class="bg-light border rounded p-2 small mb-0" style="max-height:260px;overflow:auto;"></pre>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="snapshotModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="snapshotTitle">Record Snapshot</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body"><pre id="snapshotBody" class="bg-light border rounded p-2 small mb-0" style="max-height:420px;overflow:auto;"></pre></div>
    </div>
  </div>
</div>

<script>
(function () {
  function safeParseSnapshot(encoded) {
    if (!encoded) return '{}';
    try {
      const raw = atob(encoded);
      const parsed = JSON.parse(raw);
      return JSON.stringify(parsed, null, 2);
    } catch (err) {
      try {
        return atob(encoded);
      } catch (e) {
        return '{}';
      }
    }
  }

  document.querySelectorAll('.js-activity-detail').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('ad_date').textContent = btn.dataset.date || '';
      document.getElementById('ad_email').textContent = btn.dataset.email || '';
      document.getElementById('ad_action').textContent = btn.dataset.action || '';
      document.getElementById('ad_module').textContent = btn.dataset.module || '';
      document.getElementById('ad_record').textContent = (btn.dataset.recordType || '') + ' #' + (btn.dataset.recordId || '');
      document.getElementById('ad_desc').textContent = btn.dataset.description || '';
      document.getElementById('ad_ip').textContent = btn.dataset.ip || '';
      document.getElementById('ad_location').textContent = btn.dataset.location || '';
      document.getElementById('ad_device').textContent = btn.dataset.device || '';
      document.getElementById('ad_snapshot').textContent = safeParseSnapshot(btn.dataset.snapshot || '');
    });
  });

  document.querySelectorAll('.js-snapshot-view').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('snapshotTitle').textContent = 'Record Snapshot - ' + (btn.dataset.item || '');
      document.getElementById('snapshotBody').textContent = safeParseSnapshot(btn.dataset.snapshot || '');
    });
  });
})();
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer_scripts.php'; ?>
</body>
</html>

