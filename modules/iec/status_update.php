<?php
require_once dirname(__DIR__, 2) . '/includes/security.php';
secure_session_start();

require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

requirePermission('iec.manage');

$isAdmin = (($_SESSION['role'] ?? '') === 'admin');
if (!$isAdmin) {
  http_response_code(403);
  die("Forbidden");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . url_with_base('modules/iec/index.php'));
  exit;
}
require_csrf();

$id = (int)($_POST['id'] ?? 0);
$section = trim($_POST['section'] ?? '');
$status = trim($_POST['submission_status'] ?? '');

$allowedStatus = ['Pending','Submitted','Late Submission','Approved','For Revision'];
if ($id <= 0 || !in_array($status, $allowedStatus, true)) {
  die("Invalid input.");
}

// map section -> table + redirect section key
$map = [
  'board_docs'      => ['table' => 'iec_board_docs',       'redirect' => 'board_docs'],
  'general_files'   => ['table' => 'iec_general_files',    'redirect' => 'general_files'],
  'minutes_meeting' => ['table' => 'iec_minutes_meeting',  'redirect' => 'minutes_meeting'],
];

if (!isset($map[$section])) {
  die("Invalid section.");
}

$table = $map[$section]['table'];
$redirectSection = $map[$section]['redirect'];

// update
$sql = "UPDATE {$table} SET submission_status = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) die("Prepare failed: ".$conn->error);

$stmt->bind_param("si", $status, $id);

if (!$stmt->execute()) {
  die("Execute failed: ".$stmt->error);
}

$stmt->close();

header('Location: ' . url_with_base('modules/iec/index.php?section=' . rawurlencode((string) $redirectSection) . '&status_updated=1'));
exit;
