<?php
require_once dirname(__DIR__, 2) . '/includes/security.php';
secure_session_start();
header('Content-Type: application/json');

require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

requirePermission('iec.manage');

$userId = (int)($_SESSION['user_id'] ?? 0);
$myBarangay = trim($_SESSION['barangay'] ?? '');

$isHeadAdmin = can('admin.full');
$isIecUser   = can('iec.manage');
$isIecAdmin  = (!$isHeadAdmin && $isIecUser && $myBarangay === '');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  echo json_encode(['success'=>false, 'message'=>'Invalid ID']);
  exit;
}

// Fetch cleanup main
if ($isHeadAdmin || $isIecAdmin) {
  $stmt = $conn->prepare("SELECT * FROM iec_cleanup_drive WHERE id=? AND is_deleted=0 LIMIT 1");
  $stmt->bind_param("i", $id);
} else {
  $stmt = $conn->prepare("SELECT * FROM iec_cleanup_drive WHERE id=? AND barangay=? AND is_deleted=0 LIMIT 1");
  $stmt->bind_param("is", $id, $myBarangay);
}
$stmt->execute();
$cleanup = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cleanup) {
  echo json_encode(['success'=>false, 'message'=>'Not found / no access']);
  exit;
}

// Fetch photos (supports either schema: cleanup_id or activity_id)
$photos = [];

// Try cleanup_id first
$q1 = $conn->prepare("SELECT * FROM iec_cleanup_attachments WHERE cleanup_id=? ORDER BY id ASC");
if ($q1) {
  $q1->bind_param("i", $id);
  $q1->execute();
  $r = $q1->get_result();
  while ($row = $r->fetch_assoc()) $photos[] = $row;
  $q1->close();
} else {
  // fallback: activity_id
  $q2 = $conn->prepare("SELECT * FROM iec_cleanup_attachments WHERE activity_id=? ORDER BY id ASC");
  if ($q2) {
    $q2->bind_param("i", $id);
    $q2->execute();
    $r = $q2->get_result();
    while ($row = $r->fetch_assoc()) $photos[] = $row;
    $q2->close();
  }
}

echo json_encode([
  'success' => true,
  'cleanup' => $cleanup,
  'photos'  => $photos
]);
