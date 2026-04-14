<?php
require_once dirname(__DIR__, 2) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

requirePermission('iec.manage');

$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
if (!$isPost) {
  http_response_code(405);
  exit('Method Not Allowed');
}
require_csrf();

$userId = (int)($_SESSION['user_id'] ?? 0);
$myBarangay = trim((string)($_SESSION['barangay'] ?? ''));
$roleKey = rbac_current_role();

$isHeadAdmin = is_super_role($roleKey);
$isIecAdmin = ($roleKey === 'iec_admin');
$isBarangay = ($roleKey === 'iec_secretary');

if ($isBarangay && $myBarangay === '') {
  http_response_code(403);
  exit('No barangay assigned to this account.');
}

// ✅ barangay source of truth
$barangay = $isBarangay ? $myBarangay : trim($_POST['barangay'] ?? '');
$quarter  = trim($_POST['quarter'] ?? '');
$year     = (int)($_POST['year'] ?? date('Y'));
$meeting_date = trim($_POST['meeting_date'] ?? '');
$prepared_by  = trim($_POST['prepared_by'] ?? '');

// basic validation
if ($barangay === '' || $quarter === '' || $year <= 0 || $meeting_date === '' || $prepared_by === '') {
  die("Missing required fields.");
}
if (!in_array($quarter, ['Q1','Q2','Q3','Q4'], true)) die("Invalid quarter.");

// ✅ deadline lookup (minutes_meeting)
$section_key = 'minutes_meeting';
$deadlineDate = null;

$deadlineSql = $isBarangay
  ? "
    SELECT d.deadline_date
    FROM iec_deadlines d
    LEFT JOIN user_form u ON u.id = d.created_by
    LEFT JOIN roles r ON r.id = u.role_id
    WHERE d.section_key=?
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
    WHERE section_key=?
      AND ( (scope='barangay' AND barangay=?) OR scope='global' )
    ORDER BY
      CASE WHEN scope='barangay' THEN 0 ELSE 1 END,
      deadline_date DESC,
      created_at DESC
    LIMIT 1
  ";

$stmtDL = $conn->prepare($deadlineSql);
$stmtDL->bind_param("ss", $section_key, $barangay);
$stmtDL->execute();
$dlRow = $stmtDL->get_result()->fetch_assoc();
$stmtDL->close();

$deadlineDate = $dlRow['deadline_date'] ?? null;

$today = date('Y-m-d');
$is_late = 0;
$status = 'Submitted';

// ✅ Barangay accounts: status is automatic only
if ($isBarangay) {
  if (!empty($deadlineDate) && $today > $deadlineDate) {
    $status = 'Late Submission';
    $is_late = 1;
  }
} else {
  // ✅ Admin can set status manually, but default still auto if blank
  $status = trim($_POST['submission_status'] ?? '');
  if ($status === '') $status = 'Submitted';
  $allowedStatus = ['Submitted','Pending','Late Submission','Approved','For Revision'];
  if (!in_array($status, $allowedStatus, true)) $status = 'Submitted';

  $is_late = (!empty($deadlineDate) && $today > $deadlineDate) ? 1 : 0;
  if ($is_late && $status === 'Submitted') $status = 'Late Submission';
}

// ✅ file upload
if (!isset($_FILES['minutes_pdf']) || $_FILES['minutes_pdf']['error'] !== UPLOAD_ERR_OK) {
  die("PDF required.");
}

$check = validate_upload(
  $_FILES['minutes_pdf'],
  ['pdf'],
  ['application/pdf'],
  20 * 1024 * 1024
);
if (!$check['ok']) {
  die($check['error']);
}

$origName = $_FILES['minutes_pdf']['name'];
$tmpPath  = $_FILES['minutes_pdf']['tmp_name'];
$fileSize = (int)$check['size'];
$ext = $check['ext'];

$uploadDir = APP_ROOT . '/uploads/iec/minutes/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
$storedName = "MIN_{$barangay}_{$quarter}_{$year}_".date('Ymd_His')."_".$safeBase.".pdf";

$destFs  = $uploadDir.$storedName;
$destUrl = url_with_base('uploads/iec/minutes/' . $storedName);

if (!move_uploaded_file($tmpPath, $destFs)) {
  die("Upload failed.");
}

$uploaded_by = $userId;

// ✅ insert
$stmt = $conn->prepare("
  INSERT INTO iec_minutes_meeting
  (barangay, quarter, year, meeting_date, prepared_by, submission_status, is_late,
   file_path, file_name, file_ext, file_size, uploaded_by, created_at, is_deleted)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0)
");
if (!$stmt) die("Prepare failed: ".$conn->error);

$stmt->bind_param(
  "ssisssisssii",
  $barangay,
  $quarter,
  $year,
  $meeting_date,
  $prepared_by,
  $status,
  $is_late,
  $destUrl,
  $origName,
  $ext,
  $fileSize,
  $uploaded_by
);

$stmt->execute();
$stmt->close();

header('Location: ' . url_with_base('modules/iec/index.php?section=minutes_meeting&success=1'));
exit;
