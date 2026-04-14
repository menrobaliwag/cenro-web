<?php
require_once dirname(__DIR__, 2) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

requirePermission('iec.manage');

$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
if (!$isPost) {
  http_response_code(405);
  exit('Method Not Allowed');
}
require_csrf();

$isAdmin = (($_SESSION['role'] ?? '') === 'admin');
$myBarangay = $_SESSION['barangay'] ?? '';

$barangay    = trim($_POST['barangay'] ?? '');
$doc_title   = trim($_POST['doc_title'] ?? '');
$doc_type    = trim($_POST['doc_type'] ?? 'Others');
$date_issued = trim($_POST['date_issued'] ?? '');
$notes       = trim($_POST['notes'] ?? '');

if (!$isAdmin) $barangay = $myBarangay;

if ($barangay === '' || $doc_title === '' || empty($_FILES['doc_file']['name'])) {
  die("Missing required fields.");
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) die("No user session.");

$uploadDirRel = 'uploads/iec/board_docs/';
$uploadDirAbs = APP_ROOT . '/uploads/iec/board_docs/';

if (!is_dir($uploadDirAbs)) {
  mkdir($uploadDirAbs, 0755, true);
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

$fileTmp  = $_FILES['doc_file']['tmp_name'];
$fileName = $_FILES['doc_file']['name'];
$fileSize = (int)$check['size'];

$ext = $check['ext'];

// ✅ Safe filename + unique
$safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
$unique   = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
$newName  = $safeBase . '_' . $unique . '.' . $ext;

$destAbs = $uploadDirAbs . $newName;
$destRel = url_with_base($uploadDirRel . $newName);

if (!move_uploaded_file($fileTmp, $destAbs)) {
  die("Upload failed.");
}

$dateVal = ($date_issued !== '') ? $date_issued : null;

$stmt = $conn->prepare("
  INSERT INTO iec_board_docs
  (barangay, doc_title, doc_type, date_issued, notes, file_path, file_name, file_ext, file_size, uploaded_by)
  VALUES (?,?,?,?,?,?,?,?,?,?)
");

$stmt->bind_param(
  "ssssssssii",
  $barangay,
  $doc_title,
  $doc_type,
  $dateVal,
  $notes,
  $destRel,
  $fileName,
  $ext,
  $fileSize,
  $userId
);

if (!$stmt->execute()) {
  die("DB error: " . $stmt->error);
}

$stmt->close();
$conn->close();

header('Location: ' . url_with_base('modules/iec/index.php?section=board_docs'));
exit;
