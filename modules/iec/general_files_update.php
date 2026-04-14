<?php
require_once dirname(__DIR__, 2) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

requirePermission('iec.manage');

$isAdmin = (($_SESSION['role'] ?? '') === 'admin');
$myBarangay = $_SESSION['barangay'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}
require_csrf();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  exit("Invalid ID.");
}

// ----------------------
// ✅ INPUTS (basic fields)
// ----------------------
$barangay = trim($_POST['barangay'] ?? '');
$category = trim($_POST['category'] ?? '');
$title    = trim($_POST['file_title'] ?? '');
$remarks  = trim($_POST['remarks'] ?? '');

$allowedCats = ['Reports','Letters','Plans','Others'];
if ($title === '' || !in_array($category, $allowedCats, true)) {
  http_response_code(400);
  exit("Missing/Invalid fields.");
}

// non-admin: force own barangay
if (!$isAdmin) $barangay = $myBarangay;
if ($barangay === '') {
  http_response_code(400);
  exit("Barangay required.");
}

// ----------------------
// ✅ FETCH CURRENT RECORD (para may $row tayo)
// ----------------------
$stmt = $conn->prepare("SELECT * FROM iec_general_files WHERE id=? AND is_deleted=0 LIMIT 1");
if (!$stmt) exit("Prepare failed (select): ".$conn->error);

$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
  http_response_code(404);
  exit("Record not found (or deleted).");
}

// security: non-admin cannot edit other barangay records
if (!$isAdmin && ($row['barangay'] ?? '') !== $myBarangay) {
  http_response_code(403);
  exit('Forbidden');
}

// keep existing file values
$file_path  = $row['file_path'] ?? '';
$file_ext   = $row['file_ext'] ?? '';
$file_name  = $row['file_name'] ?? '';
$storedName = $row['stored_name'] ?? '';
$file_size  = (int)($row['file_size'] ?? 0);

// ✅ default: keep existing review_status
$review_status = $row['review_status'] ?? 'pending';

// ----------------------
// ✅ ADMIN ONLY: allow status change via dropdown (status_tag)
// ----------------------
if ($isAdmin) {
  $status_tag = trim($_POST['status_tag'] ?? 'Pending');

  $allowedStatus = ['Pending','Reviewed','For Revision'];
  if (!in_array($status_tag, $allowedStatus, true)) {
    http_response_code(400);
    exit("Invalid status.");
  }

  // map UI -> DB
  $map = [
    'Pending'      => 'pending',
    'Reviewed'     => 'reviewed',
    'For Revision' => 'revision'
  ];
  $review_status = $map[$status_tag];
}

// ----------------------
// ✅ OPTIONAL REPLACE FILE
// ----------------------
if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
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
    http_response_code(400);
    exit($check['error']);
  }

  $origName = $_FILES['doc_file']['name'];
  $tmpPath  = $_FILES['doc_file']['tmp_name'];
  $fileSize = (int)$check['size'];
  $ext = $check['ext'];

  $uploadDir = APP_ROOT . '/uploads/iec/general_files/';
  if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
      http_response_code(500);
      exit("Failed to create upload directory.");
    }
  }

  $storedName = "GF_".date('Ymd_His')."_".bin2hex(random_bytes(6)).".".$ext;

  $destFs  = $uploadDir.$storedName;
  $destUrl = url_with_base('uploads/iec/general_files/' . $storedName);

  if (!move_uploaded_file($tmpPath, $destFs)) {
    http_response_code(500);
    exit("Upload failed.");
  }

  // delete old file if exists
  if (!empty($file_path)) {
    $oldPhysical = APP_ROOT . '/' . ltrim(security_strip_legacy_base_path((string) $file_path), '/');
    if (is_file($oldPhysical)) @unlink($oldPhysical);
  }

  $file_path = $destUrl;
  $file_ext  = $ext;
  $file_name = $origName;
  $file_size = $fileSize;
}

// ----------------------
// ✅ UPDATE (NO status_tag column here)
// ----------------------
$stmt = $conn->prepare("
  UPDATE iec_general_files
  SET barangay=?,
      file_title=?,
      category=?,
      remarks=?,
      file_name=?,
      stored_name=?,
      file_path=?,
      file_ext=?,
      file_size=?,
      review_status=?
  WHERE id=? AND is_deleted=0
");
if (!$stmt) exit("Prepare failed (update): ".$conn->error);

$stmt->bind_param(
  "ssssssssisi",
  $barangay,
  $title,
  $category,
  $remarks,
  $file_name,
  $storedName,
  $file_path,
  $file_ext,
  $file_size,
  $review_status,
  $id
);

if (!$stmt->execute()) {
  $err = $stmt->error;
  $stmt->close();
  http_response_code(500);
  exit("Update failed: ".$err);
}

$affected = $stmt->affected_rows;
$stmt->close();

header('Location: ' . url_with_base('modules/iec/index.php?section=general_files&updated=1&affected=' . (int) $affected));
exit;
