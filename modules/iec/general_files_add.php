<?php
require_once dirname(__DIR__, 2) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

requirePermission('iec.manage');

$roleKey = rbac_current_role();
$isAdmin = is_super_role($roleKey) || $roleKey === 'iec_admin';
$isIecSecretary = ($roleKey === 'iec_secretary');
$myBarangay = trim((string)($_SESSION['barangay'] ?? ''));
$userId = (int)($_SESSION['user_id'] ?? 0);

if (!$isAdmin && $myBarangay === '') {
  die("No barangay assigned.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . url_with_base('modules/iec/iec_data.php?section=general_files'));
  exit;
}
require_csrf();

// Inputs
$barangay   = trim($_POST['barangay'] ?? '');
$category   = trim($_POST['category'] ?? '');
$file_title = trim($_POST['file_title'] ?? '');
$remarks    = trim($_POST['remarks'] ?? '');

if (!$isAdmin) $barangay = $myBarangay;

$allowedCats = ['Reports','Letters','Plans','Others'];
if ($barangay === '' || $file_title === '' || !in_array($category, $allowedCats, true)) {
  die("Missing/Invalid fields.");
}

// Upload
if (!isset($_FILES['doc_file']) || $_FILES['doc_file']['error'] !== UPLOAD_ERR_OK) {
  die("File upload required.");
}

$check = validate_upload(
  $_FILES['doc_file'],
  ['pdf','doc','docx'],
  [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
  ],
  10 * 1024 * 1024
);
if (!$check['ok']) {
  die($check['error']);
}

$origName = $_FILES['doc_file']['name'];
$tmpPath  = $_FILES['doc_file']['tmp_name'];
$fileSize = (int)$check['size'];
$ext = $check['ext'];

// Save file
$uploadDir = APP_ROOT . '/uploads/iec/general_files/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$storedName = "GF_".date('Ymd_His')."_".bin2hex(random_bytes(6)).".".$ext;
$destFs  = $uploadDir.$storedName;
$destUrl = url_with_base('uploads/iec/general_files/' . $storedName);

if (!move_uploaded_file($tmpPath, $destFs)) {
  die("Failed to save file.");
}

// ✅ Deadline check -> submission_status
$deadlineDate = null;
$deadlineSql = $isIecSecretary
  ? "
    SELECT d.deadline_date
    FROM iec_deadlines d
    LEFT JOIN user_form u ON u.id = d.created_by
    LEFT JOIN roles r ON r.id = u.role_id
    WHERE d.section_key='general_files'
      AND ( (d.scope='barangay' AND d.barangay=?) OR d.scope='global' )
      AND r.role_name = 'iec_admin'
    ORDER BY
      CASE WHEN d.scope='barangay' THEN 0 ELSE 1 END,
      d.deadline_date DESC,
      d.created_at DESC
    LIMIT 1
  "
  : "
    SELECT deadline_date
    FROM iec_deadlines
    WHERE section_key='general_files'
      AND ( (scope='barangay' AND barangay=?) OR scope='global' )
    ORDER BY
      CASE WHEN scope='barangay' THEN 0 ELSE 1 END,
      deadline_date DESC,
      created_at DESC
    LIMIT 1
  ";

$stmtDL = $conn->prepare($deadlineSql);
$stmtDL->bind_param("s", $barangay);
$stmtDL->execute();
$dlRow = $stmtDL->get_result()->fetch_assoc();
$stmtDL->close();

if ($dlRow && !empty($dlRow['deadline_date'])) {
  $deadlineDate = $dlRow['deadline_date'];
}

$today = date('Y-m-d');
$submission_status = 'Submitted';
if ($deadlineDate && $today > $deadlineDate) {
  $submission_status = 'Late Submission';
}

// ✅ Default review_status
$review_status = 'pending';

// Insert
$stmt = $conn->prepare("
  INSERT INTO iec_general_files
  (barangay, file_title, category, remarks,
   file_name, stored_name, file_path, file_ext, file_size,
   review_status, uploaded_by, uploaded_at,
   is_deleted, submission_status)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0, ?)
");
if (!$stmt) die("Prepare failed: ".$conn->error);

$stmt->bind_param(
  "ssssssssisis",
  $barangay,
  $file_title,
  $category,
  $remarks,
  $origName,
  $storedName,
  $destUrl,
  $ext,
  $fileSize,
  $review_status,
  $userId,
  $submission_status
);

if (!$stmt->execute()) {
  @unlink($destFs);
  die("Insert failed: ".$stmt->error);
}
$stmt->close();

header('Location: ' . url_with_base('modules/iec/index.php?section=general_files&success=1'));
exit;
