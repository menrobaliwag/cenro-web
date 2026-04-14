<?php
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

$roleKey = normalize_role_key((string)($_SESSION['role'] ?? 'guest'));
if ($roleKey !== 'head_admin') {
    requirePermission('mbcurp.view');
}

function mbcurp_allowed_folders(): array
{
    return [
        'liquid_waste' => 'Liquid Waste',
        'solid_waste' => 'Solid Waste',
        'isf' => 'ISF',
        'iec' => 'IEC',
    ];
}

function mbcurp_sanitize_folder(string $folder): string
{
    $allowed = mbcurp_allowed_folders();
    $key = strtolower(trim($folder));
    return array_key_exists($key, $allowed) ? $key : '';
}

function mbcurp_sanitize_year($year, int $minYear, int $maxYear): ?int
{
    $value = filter_var($year, FILTER_VALIDATE_INT);
    if ($value === false) {
        return null;
    }
    $parsed = (int)$value;
    if ($parsed < $minYear || $parsed > $maxYear) {
        return null;
    }
    return $parsed;
}

function mbcurp_redirect(string $folder, int $year, string $status = ''): void
{
    $params = [
        'folder' => $folder,
        'year' => (string)$year,
    ];
    if ($status !== '') {
        $params['status'] = $status;
    }
    header('Location: ' . url_with_base('modules/mbcurp/index.php?' . http_build_query($params)));
    exit;
}

function mbcurp_bytes_label(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    $units = ['KB', 'MB', 'GB'];
    $size = $bytes / 1024;
    $unitIndex = 0;
    while ($size >= 1024 && $unitIndex < count($units) - 1) {
        $size /= 1024;
        $unitIndex++;
    }
    return number_format($size, 1) . ' ' . $units[$unitIndex];
}

$minYear = 2016;
$maxYear = (int)date('Y');
$years = range($minYear, $maxYear);
$allowedFolders = mbcurp_allowed_folders();
$folderKeys = array_keys($allowedFolders);
$defaultFolder = $folderKeys[0] ?? 'liquid_waste';
$canManage = can('mbcurp.manage');
$statusCode = '';

$selectedFolder = mbcurp_sanitize_folder((string)($_GET['folder'] ?? ''));
if ($selectedFolder === '') {
    $selectedFolder = $defaultFolder;
}

$folderCounts = array_fill_keys(array_keys($allowedFolders), 0);
$folderCountSql = "SELECT folder_key, COUNT(*) AS total FROM mbcurp_files WHERE is_deleted = 0 GROUP BY folder_key";
$folderRes = $conn->query($folderCountSql);
if ($folderRes instanceof mysqli_result) {
    while ($row = $folderRes->fetch_assoc()) {
        $key = mbcurp_sanitize_folder((string)($row['folder_key'] ?? ''));
        if ($key !== '') {
            $folderCounts[$key] = (int)($row['total'] ?? 0);
        }
    }
}

$yearCounts = array_fill_keys($years, 0);
$stmtYear = $conn->prepare(
    "SELECT file_year, COUNT(*) AS total
     FROM mbcurp_files
     WHERE folder_key = ? AND is_deleted = 0
     GROUP BY file_year"
);
if ($stmtYear) {
    $stmtYear->bind_param('s', $selectedFolder);
    $stmtYear->execute();
    $resYear = $stmtYear->get_result();
    while ($resYear && ($row = $resYear->fetch_assoc())) {
        $y = (int)($row['file_year'] ?? 0);
        if (array_key_exists($y, $yearCounts)) {
            $yearCounts[$y] = (int)($row['total'] ?? 0);
        }
    }
    $stmtYear->close();
}

$latestWithFiles = $maxYear;
for ($i = count($years) - 1; $i >= 0; $i--) {
    $candidate = (int)$years[$i];
    if (($yearCounts[$candidate] ?? 0) > 0) {
        $latestWithFiles = $candidate;
        break;
    }
}

$selectedYear = mbcurp_sanitize_year($_GET['year'] ?? null, $minYear, $maxYear);
if ($selectedYear === null) {
    $selectedYear = $latestWithFiles;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    require_rate_limit('mbcurp:post:' . (int)($_SESSION['user_id'] ?? 0), 10, 600);
    $action = strtolower(trim((string)($_POST['action'] ?? '')));
    if ($action !== '') {
        requirePermission('mbcurp.manage');
    }

    if ($action === 'upload') {
        $postFolder = mbcurp_sanitize_folder((string)($_POST['folder'] ?? ''));
        $postYear = mbcurp_sanitize_year($_POST['file_year'] ?? null, $minYear, $maxYear);

        if ($postFolder === '' || $postYear === null) {
            mbcurp_redirect($selectedFolder, $selectedYear, 'invalid_target');
        }

        if (!isset($_FILES['mbcurp_file']) || !is_array($_FILES['mbcurp_file'])) {
            mbcurp_redirect($postFolder, $postYear, 'upload_missing');
        }

        $allowedExt = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
        $allowedMime = [
            'application/pdf',
            'application/msword',
            'application/vnd.ms-excel',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/octet-stream',
        ];
        $check = validate_upload(
            $_FILES['mbcurp_file'],
            $allowedExt,
            $allowedMime,
            20 * 1024 * 1024
        );

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file((string)$_FILES['mbcurp_file']['tmp_name']) ?: '';
        if (empty($check['ok']) || $check['size'] > 20 * 1024 * 1024 || !in_array($mime, $allowedMime, true)) {
            mbcurp_redirect($postFolder, $postYear, 'upload_invalid');
        }

        $uploadAbsDir = $_SERVER['DOCUMENT_ROOT'] . url_with_base('uploads/mbcurp/' . $postFolder . '/' . $postYear . '/');
        $uploadRelDir = url_with_base('uploads/mbcurp/' . $postFolder . '/' . $postYear . '/');
        if (!is_dir($uploadAbsDir) && !mkdir($uploadAbsDir, 0755, true) && !is_dir($uploadAbsDir)) {
            mbcurp_redirect($postFolder, $postYear, 'upload_dir_error');
        }

        $rawOriginal = basename((string)($_FILES['mbcurp_file']['name'] ?? 'document'));
        $safeOriginal = preg_replace('/[^A-Za-z0-9\.\-_ ]+/', '_', $rawOriginal);
        $safeOriginal = ltrim($safeOriginal, '.');
        if (strpos($safeOriginal, '..') !== false || $safeOriginal === '' || $safeOriginal[0] === '/') {
            $safeOriginal = 'document.' . (string)$check['ext'];
        }

        $storedName = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . (string)$check['ext'];
        $destAbs = $uploadAbsDir . $storedName;
        $destRel = $uploadRelDir . $storedName;

        if (!move_uploaded_file((string)$_FILES['mbcurp_file']['tmp_name'], $destAbs)) {
            mbcurp_redirect($postFolder, $postYear, 'upload_failed');
        }

        $uploadedBy = (int)($_SESSION['user_id'] ?? 0);
        $fileSize = (int)($check['size'] ?? 0);
        $fileExt = (string)($check['ext'] ?? pathinfo($safeOriginal, PATHINFO_EXTENSION));

        $stmt = $conn->prepare(
            "INSERT INTO mbcurp_files
              (folder_key, file_year, file_name, stored_name, file_path, file_ext, file_size, uploaded_by, uploaded_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), NOW())"
        );
        if (!$stmt) {
            @unlink($destAbs);
            mbcurp_redirect($postFolder, $postYear, 'db_error');
        }

        $stmt->bind_param(
            'sissssii',
            $postFolder,
            $postYear,
            $safeOriginal,
            $storedName,
            $destRel,
            $fileExt,
            $fileSize,
            $uploadedBy
        );
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            @unlink($destAbs);
            mbcurp_redirect($postFolder, $postYear, 'db_error');
        }

        mbcurp_redirect($postFolder, $postYear, 'upload_ok');
    }

    if ($action === 'delete') {
        $postFolder = mbcurp_sanitize_folder((string)($_POST['folder'] ?? ''));
        $postYear = mbcurp_sanitize_year($_POST['file_year'] ?? null, $minYear, $maxYear);
        $rowId = (int)($_POST['id'] ?? 0);

        if ($postFolder === '' || $postYear === null || $rowId <= 0) {
            mbcurp_redirect($selectedFolder, $selectedYear, 'delete_invalid');
        }

        $stmt = $conn->prepare(
            "UPDATE mbcurp_files
             SET is_deleted = 1, deleted_at = NOW(), deleted_by = NULLIF(?, 0)
             WHERE id = ? AND folder_key = ? AND file_year = ? AND is_deleted = 0"
        );
        if (!$stmt) {
            mbcurp_redirect($postFolder, $postYear, 'db_error');
        }

        $deletedBy = (int)($_SESSION['user_id'] ?? 0);
        $stmt->bind_param('iisi', $deletedBy, $rowId, $postFolder, $postYear);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();

        mbcurp_redirect($postFolder, $postYear, $changed > 0 ? 'delete_ok' : 'delete_missing');
    }
}

$files = [];
$stmtFiles = $conn->prepare(
    "SELECT f.id, f.file_name, f.file_path, f.file_ext, f.file_size, f.uploaded_at, u.id AS uploader_id, u.name AS uploader_name, u.name_enc AS uploader_name_enc
     FROM mbcurp_files f
     LEFT JOIN user_form u ON u.id = f.uploaded_by
     WHERE f.folder_key = ? AND f.file_year = ? AND f.is_deleted = 0
     ORDER BY f.uploaded_at DESC, f.id DESC"
);
if ($stmtFiles) {
    $stmtFiles->bind_param('si', $selectedFolder, $selectedYear);
    $stmtFiles->execute();
    $resFiles = $stmtFiles->get_result();
    while ($resFiles && ($row = $resFiles->fetch_assoc())) {
        $encName = trim((string)($row['uploader_name_enc'] ?? ''));
        if ($encName !== '') {
            $uploaderId = (int)($row['uploader_id'] ?? 0);
            $decryptedName = data_decrypt_text($encName, $uploaderId, 'name');
            if ($decryptedName !== '') {
                $row['uploader_name'] = $decryptedName;
            }
        }
        $files[] = $row;
    }
    $stmtFiles->close();
}

if ($statusCode === '') {
    $statusCode = strtolower(trim((string)($_GET['status'] ?? '')));
}

$flashMap = [
    'upload_ok' => ['type' => 'success', 'text' => 'File uploaded successfully.'],
    'delete_ok' => ['type' => 'success', 'text' => 'File deleted successfully.'],
    'upload_missing' => ['type' => 'warning', 'text' => 'Please choose a file before uploading.'],
    'upload_invalid' => ['type' => 'warning', 'text' => 'Invalid file. Allowed: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX (max 20MB).'],
    'upload_dir_error' => ['type' => 'danger', 'text' => 'Unable to create upload directory.'],
    'upload_failed' => ['type' => 'danger', 'text' => 'Upload failed. Please try again.'],
    'db_error' => ['type' => 'danger', 'text' => 'Database error. Please try again.'],
    'invalid_target' => ['type' => 'warning', 'text' => 'Invalid folder/year selected.'],
    'delete_invalid' => ['type' => 'warning', 'text' => 'Invalid delete request.'],
    'delete_missing' => ['type' => 'warning', 'text' => 'The file was not found or already removed.'],
    'table_error' => ['type' => 'danger', 'text' => 'MBCURP table is not ready. Please check database connection.'],
];
$flash = $flashMap[$statusCode] ?? null;

$bannerText = 'Reminder: Upload official files under the correct year to keep MBCURP records organized.';
$emptyState = count($files) === 0;

include dirname(__DIR__, 2) . '/includes/header.php';
include dirname(__DIR__, 2) . '/includes/topbar_sidebar.php';
?>

<div class="page-wrapper mbcurp-page">
  <div class="page-breadcrumb">
    <div class="row">
      <div class="col-6 align-self-center">
        <h4 class="page-title fw-semibold text-dark mb-0">MBCURP Files</h4>
      </div>
      <div class="col-6 align-self-center">
        <div class="d-flex align-items-center justify-content-end gap-2">
          <a href="<?= url_with_base('dashboard.php') ?>" class="btn mbcurp-back-btn">Back</a>
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent mb-0 p-0">
              <li class="breadcrumb-item"><a href="<?= url_with_base('dashboard.php') ?>" class="text-primary">Home</a></li>
              <li class="breadcrumb-item active text-muted" aria-current="page">MBCURP</li>
            </ol>
          </nav>
        </div>
      </div>
    </div>
  </div>

  <div class="container-fluid">
    <?php if ($flash): ?>
      <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?> mb-3 mbcurp-flash-alert" role="alert">
        <?= htmlspecialchars($flash['text'], ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <div class="card mbcurp-card mb-3">
      <div class="card-body">
        <div class="mbcurp-toolbar mb-3">
          <ul class="nav nav-pills mbcurp-folder-tabs">
            <?php foreach ($allowedFolders as $folderKey => $folderLabel): ?>
              <?php $isActiveFolder = $folderKey === $selectedFolder; ?>
              <li class="nav-item">
                <a class="nav-link <?= $isActiveFolder ? 'active' : '' ?>"
                   href="<?= url_with_base('modules/mbcurp/index.php?folder=' . urlencode($folderKey) . '&year=' . (int)$selectedYear) ?>">
                  <?= htmlspecialchars($folderLabel, ENT_QUOTES, 'UTF-8') ?>
                  <span class="mbcurp-badge"><?= (int)($folderCounts[$folderKey] ?? 0) ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>

          <?php if ($canManage): ?>
            <form method="POST" enctype="multipart/form-data" class="mbcurp-upload-inline" id="mbcurpUploadForm">
              <?= csrf_input(); ?>
              <input type="hidden" name="action" value="upload">
              <input type="hidden" name="folder" value="<?= htmlspecialchars($selectedFolder, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="file_year" value="<?= (int)$selectedYear ?>">
              <input type="file" name="mbcurp_file" id="mbcurpFileInput" class="mbcurp-file-input-hidden" required>
              <button type="button" class="btn mbcurp-pick-btn" id="mbcurpPickFileBtn">Select File</button>
              <span class="mbcurp-selected-file" id="mbcurpSelectedFile" aria-live="polite">No file selected</span>
              <button type="submit" class="btn mbcurp-upload-btn" id="mbcurpUploadBtn" disabled>Upload</button>
            </form>
          <?php endif; ?>
        </div>

        <div class="mbcurp-year-row">
          <ul class="nav nav-pills mbcurp-year-tabs" role="tablist" aria-label="Year filter tabs">
            <?php foreach ($years as $year): ?>
              <li class="nav-item">
                <a class="nav-link <?= (int)$selectedYear === (int)$year ? 'active' : '' ?>"
                   href="<?= url_with_base('modules/mbcurp/index.php?folder=' . urlencode($selectedFolder) . '&year=' . (int)$year) ?>">
                  <?= (int)$year ?>
                  <span class="mbcurp-year-pill-count"><?= (int)($yearCounts[$year] ?? 0) ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>

          <div class="mbcurp-year-select-wrap">
            <label for="mbcurpYearSelect" class="visually-hidden">Select year</label>
            <select id="mbcurpYearSelect" class="form-select form-select-sm" data-folder="<?= htmlspecialchars($selectedFolder, ENT_QUOTES, 'UTF-8') ?>">
              <?php foreach ($years as $year): ?>
                <option value="<?= (int)$year ?>" <?= (int)$selectedYear === (int)$year ? 'selected' : '' ?>>
                  <?= (int)$year ?> (<?= (int)($yearCounts[$year] ?? 0) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="mbcurp-banner mt-3">
          <strong>Note:</strong> <?= htmlspecialchars($bannerText, ENT_QUOTES, 'UTF-8') ?>
        </div>
      </div>
    </div>

    <div class="card mbcurp-card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h5 class="mb-0">
            <?= htmlspecialchars($allowedFolders[$selectedFolder] ?? 'Folder', ENT_QUOTES, 'UTF-8') ?> - <?= (int)$selectedYear ?>
          </h5>
        </div>

        <?php if ($emptyState): ?>
          <div class="mbcurp-empty-state">
            <p class="mb-0">No files yet - upload here.</p>
            <?php if ($canManage): ?><small class="d-block mt-2 text-muted">Use the upload panel above.</small><?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if (!$emptyState): ?>
          <div class="table-responsive">
            <table id="mbcurpFilesTable" class="table table-striped table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th style="width: 90px;">ID</th>
                  <th>File Name</th>
                  <th style="width: 190px;">Uploaded By</th>
                  <th style="width: 230px;">Date and Time</th>
                  <th style="width: 200px;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($files as $row): ?>
                  <tr>
                    <td><?= (int)$row['id'] ?></td>
                    <td>
                      <a href="<?= htmlspecialchars((string)$row['file_path'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="mbcurp-file-link">
                        <?= htmlspecialchars((string)($row['file_name'] ?? 'Untitled file'), ENT_QUOTES, 'UTF-8') ?>
                      </a>
                      <div class="small text-muted">
                        <?= htmlspecialchars(strtoupper((string)($row['file_ext'] ?? '')), ENT_QUOTES, 'UTF-8') ?> -
                        <?= htmlspecialchars(mbcurp_bytes_label((int)($row['file_size'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>
                      </div>
                    </td>
                    <td><?= htmlspecialchars((string)($row['uploader_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(date('F j, Y g:i A', strtotime((string)($row['uploaded_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                      <a href="<?= htmlspecialchars((string)$row['file_path'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary me-1">View</a>
                      <?php if ($canManage): ?>
                        <form method="POST" class="d-inline-block" onsubmit="return confirm('Delete this file?');">
                          <?= csrf_input(); ?>
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                          <input type="hidden" name="folder" value="<?= htmlspecialchars($selectedFolder, ENT_QUOTES, 'UTF-8') ?>">
                          <input type="hidden" name="file_year" value="<?= (int)$selectedYear ?>">
                          <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer_scripts.php'; ?>
<script>
  $(function () {
    (function cleanupMbcurpUrl() {
      try {
        const url = new URL(window.location.href);
        let changed = false;
        if (url.searchParams.has('status')) {
          url.searchParams.delete('status');
          changed = true;
        }
        if (url.hash) {
          url.hash = '';
          changed = true;
        }
        if (changed && window.history && typeof window.history.replaceState === 'function') {
          const cleanUrl = url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '');
          window.history.replaceState({}, document.title, cleanUrl);
        }
      } catch (e) {
        // no-op
      }
    })();

    const $flash = $('.mbcurp-flash-alert');
    if ($flash.length) {
      setTimeout(function () {
        $flash.fadeOut(300, function () { $(this).remove(); });
      }, 3000);
    }

    const fileInput = document.getElementById('mbcurpFileInput');
    const pickBtn = document.getElementById('mbcurpPickFileBtn');
    const selectedFile = document.getElementById('mbcurpSelectedFile');
    const uploadBtn = document.getElementById('mbcurpUploadBtn');

    function syncFileState() {
      const hasFile = !!(fileInput && fileInput.files && fileInput.files.length > 0);
      if (selectedFile) {
        selectedFile.textContent = hasFile ? fileInput.files[0].name : 'No file selected';
      }
      if (uploadBtn) {
        uploadBtn.disabled = !hasFile;
      }
    }

    if (pickBtn && fileInput) {
      pickBtn.addEventListener('click', function () {
        fileInput.click();
      });
    }

    if (fileInput) {
      fileInput.addEventListener('change', syncFileState);
      syncFileState();
    }

    const $table = $('#mbcurpFilesTable');
    if ($table.length && !$.fn.DataTable.isDataTable($table[0])) {
      $table.DataTable({
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        order: [[0, 'desc']],
        language: {
          search: '_INPUT_',
          searchPlaceholder: 'Search files...',
          emptyTable: 'No files yet - upload here.'
        }
      });
    }

    const yearSelect = document.getElementById('mbcurpYearSelect');
    if (yearSelect) {
      yearSelect.addEventListener('change', function () {
        const folder = this.getAttribute('data-folder') || '';
        const nextYear = this.value || '';
        if (!folder || !nextYear) return;
        const url = (window.BASE_URL || '') + '/modules/mbcurp/index.php?folder=' + encodeURIComponent(folder) + '&year=' + encodeURIComponent(nextYear);
        window.location.href = url;
      });
    }
  });
</script>
</body>
</html>
