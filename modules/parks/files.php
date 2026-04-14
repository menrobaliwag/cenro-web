<?php
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

$roleKey = normalize_role_key((string)($_SESSION['role'] ?? 'guest'));
if ($roleKey !== 'head_admin') {
    requirePermission('parks.view');
}

function parks_file_categories(): array
{
    return [
        'wild_life' => 'Wild Life',
        'parks_and_monuments' => 'Parks and Monuments',
    ];
}

function parks_sanitize_category(string $category): string
{
    $allowed = parks_file_categories();
    $key = strtolower(trim($category));
    return array_key_exists($key, $allowed) ? $key : '';
}

function parks_sanitize_year($year, int $minYear, int $maxYear): ?int
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

function parks_redirect(string $category, int $year, string $status = ''): void
{
    $params = [
        'category' => $category,
        'year' => (string)$year,
    ];
    if ($status !== '') {
        $params['status'] = $status;
    }
    header('Location: ' . url_with_base('modules/parks/files.php?' . http_build_query($params)));
    exit;
}

function parks_bytes_label(int $bytes): string
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
$categories = parks_file_categories();
$categoryKeys = array_keys($categories);
$defaultCategory = $categoryKeys[0] ?? 'wild_life';
$canManage = can('parks.manage');
$statusCode = '';

$selectedCategory = parks_sanitize_category((string)($_GET['category'] ?? ''));
if ($selectedCategory === '') {
    $selectedCategory = $defaultCategory;
}

$categoryCounts = array_fill_keys(array_keys($categories), 0);
$catRes = $conn->query("SELECT category_key, COUNT(*) AS total FROM parks_files WHERE is_deleted = 0 GROUP BY category_key");
if ($catRes instanceof mysqli_result) {
    while ($row = $catRes->fetch_assoc()) {
        $key = parks_sanitize_category((string)($row['category_key'] ?? ''));
        if ($key !== '') {
            $categoryCounts[$key] = (int)($row['total'] ?? 0);
        }
    }
}

$yearCounts = array_fill_keys($years, 0);
$stmtYear = $conn->prepare(
    "SELECT file_year, COUNT(*) AS total
     FROM parks_files
     WHERE category_key = ? AND is_deleted = 0
     GROUP BY file_year"
);
if ($stmtYear) {
    $stmtYear->bind_param('s', $selectedCategory);
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

$selectedYear = parks_sanitize_year($_GET['year'] ?? null, $minYear, $maxYear);
if ($selectedYear === null) {
    $selectedYear = $latestWithFiles;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    require_rate_limit('parks:post:' . (int)($_SESSION['user_id'] ?? 0), 10, 600);
    $action = strtolower(trim((string)($_POST['action'] ?? '')));
    if ($action !== '') {
        requirePermission('parks.manage');
    }

    if ($action === 'upload') {
        $postCategory = parks_sanitize_category((string)($_POST['category'] ?? ''));
        $postYear = parks_sanitize_year($_POST['file_year'] ?? null, $minYear, $maxYear);

        if ($postCategory === '' || $postYear === null) {
            parks_redirect($selectedCategory, $selectedYear, 'invalid_target');
        }

        if (!isset($_FILES['parks_file']) || !is_array($_FILES['parks_file'])) {
            parks_redirect($postCategory, $postYear, 'upload_missing');
        }

        $allowedExt = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'webp'];
        $allowedMime = [
            'application/pdf',
            'application/msword',
            'application/vnd.ms-excel',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/octet-stream',
        ];
        $check = validate_upload(
            $_FILES['parks_file'],
            $allowedExt,
            $allowedMime,
            20 * 1024 * 1024
        );

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file((string)$_FILES['parks_file']['tmp_name']) ?: '';
        if (empty($check['ok']) || $check['size'] > 20 * 1024 * 1024 || !in_array($mime, $allowedMime, true)) {
            parks_redirect($postCategory, $postYear, 'upload_invalid');
        }

        if (empty($check['ok'])) {
            parks_redirect($postCategory, $postYear, 'upload_invalid');
        }

        $uploadAbsDir = $_SERVER['DOCUMENT_ROOT'] . url_with_base('uploads/parks/' . $postCategory . '/' . $postYear . '/');
        $uploadRelDir = url_with_base('uploads/parks/' . $postCategory . '/' . $postYear . '/');
        if (!is_dir($uploadAbsDir) && !mkdir($uploadAbsDir, 0755, true) && !is_dir($uploadAbsDir)) {
            parks_redirect($postCategory, $postYear, 'upload_dir_error');
        }

        $rawOriginal = basename((string)($_FILES['parks_file']['name'] ?? 'document'));
        $safeOriginal = preg_replace('/[^A-Za-z0-9\.\-_ ]+/', '_', $rawOriginal);
        $safeOriginal = ltrim($safeOriginal, '.');
        if (strpos($safeOriginal, '..') !== false || $safeOriginal === '' || $safeOriginal[0] === '/') {
            $safeOriginal = 'document.' . (string)$check['ext'];
        }

        $storedName = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . (string)$check['ext'];
        $destAbs = $uploadAbsDir . $storedName;
        $destRel = $uploadRelDir . $storedName;
        if (!move_uploaded_file((string)$_FILES['parks_file']['tmp_name'], $destAbs)) {
            parks_redirect($postCategory, $postYear, 'upload_failed');
        }

        $uploadedBy = (int)($_SESSION['user_id'] ?? 0);
        $fileSize = (int)($check['size'] ?? 0);
        $fileExt = (string)($check['ext'] ?? pathinfo($safeOriginal, PATHINFO_EXTENSION));

        $stmt = $conn->prepare(
            "INSERT INTO parks_files
              (category_key, file_year, file_name, stored_name, file_path, file_ext, file_size, uploaded_by, uploaded_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), NOW())"
        );
        if (!$stmt) {
            @unlink($destAbs);
            parks_redirect($postCategory, $postYear, 'db_error');
        }

        $stmt->bind_param(
            'sissssii',
            $postCategory,
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
            parks_redirect($postCategory, $postYear, 'db_error');
        }

        parks_redirect($postCategory, $postYear, 'upload_ok');
    }

    if ($action === 'delete') {
        $postCategory = parks_sanitize_category((string)($_POST['category'] ?? ''));
        $postYear = parks_sanitize_year($_POST['file_year'] ?? null, $minYear, $maxYear);
        $rowId = (int)($_POST['id'] ?? 0);
        if ($postCategory === '' || $postYear === null || $rowId <= 0) {
            parks_redirect($selectedCategory, $selectedYear, 'delete_invalid');
        }

        $stmt = $conn->prepare(
            "UPDATE parks_files
             SET is_deleted = 1, deleted_at = NOW(), deleted_by = NULLIF(?, 0)
             WHERE id = ? AND category_key = ? AND file_year = ? AND is_deleted = 0"
        );
        if (!$stmt) {
            parks_redirect($postCategory, $postYear, 'db_error');
        }
        $deletedBy = (int)($_SESSION['user_id'] ?? 0);
        $stmt->bind_param('iisi', $deletedBy, $rowId, $postCategory, $postYear);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();

        parks_redirect($postCategory, $postYear, $changed > 0 ? 'delete_ok' : 'delete_missing');
    }
}

$files = [];
$stmtFiles = $conn->prepare(
    "SELECT f.id, f.file_name, f.file_path, f.file_ext, f.file_size, f.uploaded_at, u.id AS uploader_id, u.name AS uploader_name, u.name_enc AS uploader_name_enc
     FROM parks_files f
     LEFT JOIN user_form u ON u.id = f.uploaded_by
     WHERE f.category_key = ? AND f.file_year = ? AND f.is_deleted = 0
     ORDER BY f.uploaded_at DESC, f.id DESC"
);
if ($stmtFiles) {
    $stmtFiles->bind_param('si', $selectedCategory, $selectedYear);
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
    'upload_invalid' => ['type' => 'warning', 'text' => 'Invalid file. Allowed: Office files, PDF, JPG/PNG/WEBP (max 20MB).'],
    'upload_dir_error' => ['type' => 'danger', 'text' => 'Unable to create upload directory.'],
    'upload_failed' => ['type' => 'danger', 'text' => 'Upload failed. Please try again.'],
    'db_error' => ['type' => 'danger', 'text' => 'Database error. Please try again.'],
    'invalid_target' => ['type' => 'warning', 'text' => 'Invalid category/year selected.'],
    'delete_invalid' => ['type' => 'warning', 'text' => 'Invalid delete request.'],
    'delete_missing' => ['type' => 'warning', 'text' => 'The file was not found or already removed.'],
    'table_error' => ['type' => 'danger', 'text' => 'Land management files table is not ready. Please check database connection.'],
];
$flash = $flashMap[$statusCode] ?? null;
$emptyState = count($files) === 0;

include dirname(__DIR__, 2) . '/includes/header.php';
include dirname(__DIR__, 2) . '/includes/topbar_sidebar.php';
?>

<div class="page-wrapper parks-files-page">
  <div class="page-breadcrumb">
    <div class="row">
      <div class="col-6 align-self-center">
        <h4 class="page-title fw-semibold text-dark mb-0">Land Management Folder</h4>
      </div>
      <div class="col-6 align-self-center">
        <div class="d-flex align-items-center justify-content-end gap-2">
          <a href="<?= url_with_base('modules/parks/index.php') ?>" class="btn parks-back-btn">Back</a>
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent mb-0 p-0">
              <li class="breadcrumb-item"><a href="<?= url_with_base('dashboard.php') ?>" class="text-primary">Home</a></li>
              <li class="breadcrumb-item"><a href="<?= url_with_base('modules/parks/index.php') ?>" class="text-primary">Parks and Monument</a></li>
              <li class="breadcrumb-item active text-muted" aria-current="page">Land Management Folder</li>
            </ol>
          </nav>
        </div>
      </div>
    </div>
  </div>

  <div class="container-fluid">
    <?php if ($flash): ?>
      <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?> mb-3 parks-flash-alert" role="alert">
        <?= htmlspecialchars($flash['text'], ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <div class="card parks-files-card mb-3">
      <div class="card-body">
        <div class="parks-toolbar mb-3">
          <ul class="nav nav-pills parks-category-tabs">
            <?php foreach ($categories as $key => $label): ?>
              <li class="nav-item">
                <a class="nav-link <?= $selectedCategory === $key ? 'active' : '' ?>"
                   href="<?= url_with_base('modules/parks/files.php?category=' . urlencode($key) . '&year=' . (int)$selectedYear) ?>">
                  <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                  <span class="parks-badge"><?= (int)($categoryCounts[$key] ?? 0) ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>

          <?php if ($canManage): ?>
            <form method="POST" enctype="multipart/form-data" class="parks-upload-inline">
              <?= csrf_input(); ?>
              <input type="hidden" name="action" value="upload">
              <input type="hidden" name="category" value="<?= htmlspecialchars($selectedCategory, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="file_year" value="<?= (int)$selectedYear ?>">

              <input type="file" name="parks_file" id="parksFileInput" class="parks-file-input-hidden" required>
              <button type="button" class="btn parks-pick-btn" id="parksPickBtn">Select File</button>
              <span class="parks-selected-file" id="parksSelectedFile" aria-live="polite">No file selected</span>
              <button type="submit" class="btn parks-upload-btn" id="parksUploadBtn" disabled>Upload</button>
            </form>
          <?php endif; ?>
        </div>

        <div class="parks-year-row">
          <ul class="nav nav-pills parks-year-tabs">
            <?php foreach ($years as $year): ?>
              <li class="nav-item">
                <a class="nav-link <?= (int)$selectedYear === (int)$year ? 'active' : '' ?>"
                   href="<?= url_with_base('modules/parks/files.php?category=' . urlencode($selectedCategory) . '&year=' . (int)$year) ?>">
                  <?= (int)$year ?>
                  <span class="parks-year-pill-count"><?= (int)($yearCounts[$year] ?? 0) ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>

          <div class="parks-year-select-wrap">
            <label for="parksYearSelect" class="visually-hidden">Select year</label>
            <select id="parksYearSelect" class="form-select form-select-sm" data-category="<?= htmlspecialchars($selectedCategory, ENT_QUOTES, 'UTF-8') ?>">
              <?php foreach ($years as $year): ?>
                <option value="<?= (int)$year ?>" <?= (int)$selectedYear === (int)$year ? 'selected' : '' ?>>
                  <?= (int)$year ?> (<?= (int)($yearCounts[$year] ?? 0) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="card parks-files-card">
      <div class="card-body">
        <h5 class="mb-3">
          <?= htmlspecialchars($categories[$selectedCategory] ?? 'Category', ENT_QUOTES, 'UTF-8') ?> - <?= (int)$selectedYear ?>
        </h5>

        <?php if ($emptyState): ?>
          <div class="parks-empty-state">
            <p class="mb-0">No files yet - upload here.</p>
            <?php if ($canManage): ?><small class="d-block mt-2 text-muted">Use the upload panel above.</small><?php endif; ?>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table id="parksFilesTable" class="table table-striped table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th style="width: 80px;">ID</th>
                  <th>File Name</th>
                  <th style="width: 180px;">Uploaded By</th>
                  <th style="width: 220px;">Date and Time</th>
                  <th style="width: 190px;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($files as $row): ?>
                  <tr>
                    <td><?= (int)$row['id'] ?></td>
                    <td>
                      <a href="<?= htmlspecialchars((string)$row['file_path'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="parks-file-link">
                        <?= htmlspecialchars((string)($row['file_name'] ?? 'Untitled file'), ENT_QUOTES, 'UTF-8') ?>
                      </a>
                      <div class="small text-muted">
                        <?= htmlspecialchars(strtoupper((string)($row['file_ext'] ?? '')), ENT_QUOTES, 'UTF-8') ?> -
                        <?= htmlspecialchars(parks_bytes_label((int)($row['file_size'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>
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
                          <input type="hidden" name="category" value="<?= htmlspecialchars($selectedCategory, ENT_QUOTES, 'UTF-8') ?>">
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
    (function cleanupParksUrl() {
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
      } catch (e) {}
    })();

    const $flash = $('.parks-flash-alert');
    if ($flash.length) {
      setTimeout(function () {
        $flash.fadeOut(300, function () { $(this).remove(); });
      }, 3000);
    }

    const fileInput = document.getElementById('parksFileInput');
    const pickBtn = document.getElementById('parksPickBtn');
    const selectedFile = document.getElementById('parksSelectedFile');
    const uploadBtn = document.getElementById('parksUploadBtn');

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

    const yearSelect = document.getElementById('parksYearSelect');
    if (yearSelect) {
      yearSelect.addEventListener('change', function () {
        const category = this.getAttribute('data-category') || '';
        const year = this.value || '';
        if (!category || !year) return;
        window.location.href = (window.BASE_URL || '') + '/modules/parks/files.php?category=' + encodeURIComponent(category) + '&year=' + encodeURIComponent(year);
      });
    }

    const $table = $('#parksFilesTable');
    if ($table.length && !$.fn.DataTable.isDataTable($table[0])) {
      $table.DataTable({
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        order: [[0, 'desc']],
        language: {
          search: '_INPUT_',
          searchPlaceholder: 'Search files...'
        }
      });
    }
  });
</script>
</body>
</html>

