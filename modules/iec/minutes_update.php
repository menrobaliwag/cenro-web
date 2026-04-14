<?php
require_once dirname(__DIR__, 2) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

requirePermission('iec.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit("Invalid ID"); }

// ✅ fetch record
$stmt = $conn->prepare("SELECT * FROM iec_minutes_meeting WHERE id=? AND is_deleted=0 LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) { http_response_code(404); exit("Record not found."); }

// ✅ Barangay scoping: barangay users can only edit their own records
if ($isBarangay && ($row['barangay'] ?? '') !== $myBarangay) {
  http_response_code(403);
  exit("Forbidden.");
}

// ✅ lock rule: barangay cannot edit if Approved
$currentStatus = $row['submission_status'] ?? 'Submitted';
if ($isBarangay && $currentStatus === 'Approved') {
  http_response_code(403);
  exit("This record is already approved and cannot be edited.");
}

// ✅ source of truth fields
$barangay     = $isBarangay ? $myBarangay : trim($_POST['barangay'] ?? $row['barangay']);
$quarter      = trim($_POST['quarter'] ?? $row['quarter']);
$year         = (int)($_POST['year'] ?? $row['year']);
$meeting_date = trim($_POST['meeting_date'] ?? $row['meeting_date']);
$prepared_by  = trim($_POST['prepared_by'] ?? $row['prepared_by']);

if ($barangay === '' || $quarter === '' || $year <= 0 || $meeting_date === '' || $prepared_by === '') {
  http_response_code(400);
  exit("Missing required fields.");
}
if (!in_array($quarter, ['Q1','Q2','Q3','Q4'], true)) {
  http_response_code(400);
  exit("Invalid quarter.");
}

// ✅ deadline lookup again (minutes_meeting)
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
$is_late = (!empty($deadlineDate) && $today > $deadlineDate) ? 1 : 0;

// ✅ status control
$allowedStatus = ['Submitted','Pending','Late Submission','Approved','For Revision'];
$status = $currentStatus;

// Barangay users cannot change status manually:
if ($isBarangay) {
  // auto update status to Late Submission if late and currently Submitted
  if ($is_late && ($currentStatus === 'Submitted' || $currentStatus === 'Late Submission')) {
    $status = 'Late Submission';
  } else {
    // keep whatever admin set (Pending/For Revision/etc)
    $status = $currentStatus;
  }
} else {
  // Admin can change status
  $incoming = trim($_POST['submission_status'] ?? $currentStatus);
  if (in_array($incoming, $allowedStatus, true)) $status = $incoming;
  if ($is_late && $status === 'Submitted') $status = 'Late Submission';
}

// keep existing file values
$file_path = $row['file_path'] ?? '';
$file_name = $row['file_name'] ?? '';
$file_ext  = $row['file_ext'] ?? '';
$file_size = (int)($row['file_size'] ?? 0);

// ✅ optional replace pdf
if (isset($_FILES['minutes_pdf']) && $_FILES['minutes_pdf']['error'] === UPLOAD_ERR_OK) {
  $check = validate_upload(
    $_FILES['minutes_pdf'],
    ['pdf'],
    ['application/pdf'],
    20 * 1024 * 1024
  );
  if (!$check['ok']) { http_response_code(400); exit($check['error']); }

  $origName = $_FILES['minutes_pdf']['name'];
  $tmpPath  = $_FILES['minutes_pdf']['tmp_name'];
  $newSize  = (int)$check['size'];

  $ext = $check['ext'];

  $uploadDir = APP_ROOT . '/uploads/iec/minutes/';
  if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

  $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
  $storedName = "MIN_{$barangay}_{$quarter}_{$year}_".date('Ymd_His')."_".$safeBase.".pdf";

  $destFs  = $uploadDir.$storedName;
  $destUrl = url_with_base('uploads/iec/minutes/' . $storedName);

  if (!move_uploaded_file($tmpPath, $destFs)) {
    http_response_code(500);
    exit("Upload failed.");
  }

  // delete old file
  if (!empty($file_path)) {
    $oldPhysical = APP_ROOT . '/' . ltrim(security_strip_legacy_base_path((string) $file_path), '/');
    if (is_file($oldPhysical)) @unlink($oldPhysical);
  }

  $file_path = $destUrl;
  $file_name = $origName;
  $file_ext  = $ext;
  $file_size = $newSize;
}

// ✅ update
$stmt = $conn->prepare("
  UPDATE iec_minutes_meeting
  SET barangay=?,
      quarter=?,
      year=?,
      meeting_date=?,
      prepared_by=?,
      submission_status=?,
      is_late=?,
      file_path=?,
      file_name=?,
      file_ext=?,
      file_size=?
  WHERE id=? AND is_deleted=0
");
if (!$stmt) { http_response_code(500); exit("Prepare failed (update): ".$conn->error); }

$stmt->bind_param(
  "ssisssisssii",
  $barangay,
  $quarter,
  $year,
  $meeting_date,
  $prepared_by,
  $status,
  $is_late,
  $file_path,
  $file_name,
  $file_ext,
  $file_size,
  $id
);

if (!$stmt->execute()) {
  $err = $stmt->error;
  $stmt->close();
  http_response_code(500);
  exit("Update failed: ".$err);
}
$stmt->close();

header('Location: ' . url_with_base('modules/iec/index.php?section=minutes_meeting&updated=1'));
exit;
