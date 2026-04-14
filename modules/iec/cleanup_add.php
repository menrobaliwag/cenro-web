<?php
require_once dirname(__DIR__, 2) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

requirePermission('iec.manage');

$userId = (int)($_SESSION['user_id'] ?? 0);
$myBarangay = trim($_SESSION['barangay'] ?? '');

$isHeadAdmin = can('admin.full');
$isIecUser   = can('iec.manage');
$isIecAdmin  = (!$isHeadAdmin && $isIecUser && $myBarangay === '');
$isBarangay  = (!$isHeadAdmin && !$isIecAdmin && $myBarangay !== '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit("Method Not Allowed");
}
require_csrf();

$barangay = $isBarangay ? $myBarangay : trim($_POST['barangay'] ?? '');
$title = trim($_POST['activity_title'] ?? '');
$date  = trim($_POST['activity_date'] ?? '');
$time  = trim($_POST['activity_time'] ?? '');
$venue = trim($_POST['venue'] ?? '');
$participants = (int)($_POST['participants'] ?? 0);
$desc  = trim($_POST['description'] ?? '');

if ($barangay==='' || $title==='' || $date==='' || $time==='' || $venue==='') {
  die("Missing required fields.");
}

// status rules
$status = $isBarangay ? 'Submitted' : trim($_POST['status'] ?? 'Submitted');
if (!in_array($status, ['Draft','Submitted','Approved','For Revision'], true)) $status = 'Submitted';

/* ===========================
   ATTENDANCE (image/document)
=========================== */
$attendance_type = 'image';
$attendance_path = '';
$attendance_name = '';
$attendance_ext  = '';
$attendance_size = null;

if (isset($_FILES['attendance_file']) && $_FILES['attendance_file']['error'] === UPLOAD_ERR_OK) {
  $check = validate_upload(
    $_FILES['attendance_file'],
    ['jpg','jpeg','png','pdf','doc','docx'],
    [
      'image/jpeg',
      'image/png',
      'application/pdf',
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ],
    10 * 1024 * 1024
  );
  if (!$check['ok']) {
    die($check['error']);
  }

  $orig = $_FILES['attendance_file']['name'];
  $tmp  = $_FILES['attendance_file']['tmp_name'];
  $size = (int)$check['size'];
  $ext  = $check['ext'];

  $allowedImg = ['jpg','jpeg','png'];

  if (in_array($ext, $allowedImg, true)) {
    $attendance_type = 'image';
  } else {
    $attendance_type = 'document';
  }

  $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($orig, PATHINFO_FILENAME));
  $stored = "ATT_{$barangay}_".date('Ymd_His')."_{$safeBase}.{$ext}";

  $dir = APP_ROOT . '/uploads/iec/cleanup/attendance/';
  if (!is_dir($dir)) mkdir($dir, 0755, true);

  $fs  = $dir.$stored;
  $url = url_with_base('uploads/iec/cleanup/attendance/' . $stored);

  if (!move_uploaded_file($tmp, $fs)) die("Attendance upload failed.");

  $attendance_path = $url;
  $attendance_name = $orig;
  $attendance_ext  = $ext;
  $attendance_size = $size;
}

/* ===========================
   INSERT MAIN ROW
=========================== */
$stmt = $conn->prepare("
  INSERT INTO iec_cleanup_drive
  (barangay, activity_title, activity_date, activity_time, venue, participants, description,
   attendance_type, attendance_path, attendance_name, attendance_ext, attendance_size,
   created_by, created_at, status, is_deleted)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,0)
");

if (!$stmt) die("Prepare failed: ".$conn->error);

$stmt->bind_param(
  "sssssisissssii",
  $barangay,
  $title,
  $date,
  $time,
  $venue,
  $participants,
  $desc,
  $attendance_type,
  $attendance_path,
  $attendance_name,
  $attendance_ext,
  $attendance_size,
  $userId,
  $status
);

if (!$stmt->execute()) {
  $err = $stmt->error;
  $stmt->close();
  die("Insert failed: ".$err);
}

$cleanupId = (int)$stmt->insert_id;
$stmt->close();

/* ===========================
   MULTI PHOTOS -> attachments
=========================== */
if (isset($_FILES['photos']) && is_array($_FILES['photos']['name'])) {

  $photoDir = APP_ROOT . '/uploads/iec/cleanup/photos/';
  if (!is_dir($photoDir)) {
    mkdir($photoDir, 0755, true);
  }

  for ($i = 0; $i < count($_FILES['photos']['name']); $i++) {

    if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;

    $file = [
      'name' => $_FILES['photos']['name'][$i],
      'type' => $_FILES['photos']['type'][$i],
      'tmp_name' => $_FILES['photos']['tmp_name'][$i],
      'error' => $_FILES['photos']['error'][$i],
      'size' => $_FILES['photos']['size'][$i],
    ];
    $check = validate_upload(
      $file,
      ['jpg','jpeg','png'],
      ['image/jpeg','image/png'],
      5 * 1024 * 1024
    );
    if (!$check['ok']) continue;

    $orig = $file['name'];
    $tmp  = $file['tmp_name'];
    $size = (int)$check['size'];
    $ext  = $check['ext'];

    $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($orig, PATHINFO_FILENAME));
    $stored = "PHOTO_{$cleanupId}_" . date('Ymd_His') . "_{$safeBase}.{$ext}";

    $fs  = $photoDir . $stored;
    $url = url_with_base('uploads/iec/cleanup/photos/' . $stored);

    if (!move_uploaded_file($tmp, $fs)) continue;

    // ✅ INSERT INTO ATTACHMENTS TABLE
    $ins = $conn->prepare("
      INSERT INTO iec_cleanup_attachments
      (cleanup_id, attach_type, file_name, stored_name, file_path, file_ext, file_size, uploaded_at)
      VALUES (?, 'photo', ?, ?, ?, ?, ?, NOW())
    ");

    if (!$ins) continue;

    $ins->bind_param(
      "issssi",
      $cleanupId, // FK → iec_cleanup_drive.id
      $orig,      // file_name
      $stored,    // stored_name
      $url,       // file_path
      $ext,       // file_ext
      $size       // file_size
    );

    $ins->execute();
    $ins->close();
  }
}

// ✅ redirect AFTER loop
header('Location: ' . url_with_base('modules/iec/index.php?section=cleanup_drive&success=1'));
exit;
