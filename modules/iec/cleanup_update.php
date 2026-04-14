<?php
require_once dirname(__DIR__, 2) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

requirePermission('iec.manage');

$isHeadAdmin = can('admin.full');
$isIecAdmin = (!$isHeadAdmin && can('iec.manage') && empty($_SESSION['barangay']));

// ❌ Only Admin / IEC Admin can edit
if (!$isHeadAdmin && !$isIecAdmin) {
  die("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  die("Invalid request");
}
require_csrf();

$id = (int)($_POST['id'] ?? 0);
$title = trim($_POST['activity_title'] ?? '');
$date = trim($_POST['activity_date'] ?? '');
$time = trim($_POST['activity_time'] ?? '');
$venue = trim($_POST['venue'] ?? '');
$participants = (int)($_POST['participants'] ?? 0);
$status = trim($_POST['status'] ?? 'Submitted');
$desc = trim($_POST['description'] ?? '');

if ($id <= 0 || $title === '' || $date === '' || $time === '' || $venue === '') {
  die("Missing required fields");
}

$allowedStatus = ['Draft','Submitted','Approved','For Revision'];
if (!in_array($status, $allowedStatus, true)) {
  $status = 'Submitted';
}

$stmt = $conn->prepare("
  UPDATE iec_cleanup_drive
  SET activity_title = ?,
      activity_date = ?,
      activity_time = ?,
      venue = ?,
      participants = ?,
      description = ?,
      status = ?,
      updated_at = NOW()
  WHERE id = ?
  LIMIT 1
");

$stmt->bind_param(
  "ssssissi",
  $title,
  $date,
  $time,
  $venue,
  $participants,
  $desc,
  $status,
  $id
);

if (!$stmt->execute()) {
  $stmt->close();
  die("Update failed");
}

$stmt->close();

header('Location: ' . url_with_base('modules/iec/index.php?section=cleanup_drive&updated=1'));
exit;
