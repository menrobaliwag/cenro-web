<?php
require_once dirname(__DIR__, 2) . '/includes/security.php';
secure_session_start();

require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

requirePermission('iec.manage');

// ✅ Only Head Admin / IEC Admin can set deadlines (IEC Secretary cannot)
$roleKey = rbac_current_role();
$canSetDeadline = is_super_role($roleKey) || $roleKey === 'iec_admin';
if (!$canSetDeadline) {
  abort_403();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . url_with_base('modules/iec/index.php'));
  exit;
}
require_csrf();

// inputs
$section_key   = trim($_POST['section_key'] ?? '');
$scope         = trim($_POST['scope'] ?? 'global'); // global | barangay
$barangay      = trim($_POST['barangay'] ?? '');
$deadline_date = trim($_POST['deadline_date'] ?? '');
$notes         = trim($_POST['notes'] ?? '');

$allowedSections = ['iec_activities','board_docs','general_files','minutes_meeting','cleanup_drive'];
if (!in_array($section_key, $allowedSections, true)) {
  die("Invalid section_key.");
}

if ($scope !== 'global' && $scope !== 'barangay') {
  die("Invalid scope.");
}

if ($deadline_date === '') {
  die("Deadline date required.");
}

// validate date format Y-m-d
$dt = DateTime::createFromFormat('Y-m-d', $deadline_date);
if (!$dt || $dt->format('Y-m-d') !== $deadline_date) {
  die("Invalid deadline date.");
}

if ($scope === 'barangay' && $barangay === '') {
  die("Barangay is required when scope is barangay.");
}

$created_by = (int)($_SESSION['user_id'] ?? 0);

/**
 * ✅ IMPORTANT:
 * Para laging lumabas ang latest deadline,
 * insert lang tayo ng row (history).
 * Fetch mo naman order by deadline_date desc limit 1 (ok na)
 */

if ($scope === 'global') {

  // ✅ global insert (walang barangay)
  $stmt = $conn->prepare("
    INSERT INTO iec_deadlines (section_key, scope, deadline_date, notes, created_by, created_at)
    VALUES (?, 'global', ?, ?, ?, NOW())
  ");
  if (!$stmt) die("Prepare failed: ".$conn->error);

  $stmt->bind_param("sssi", $section_key, $deadline_date, $notes, $created_by);

} else {

  // ✅ barangay insert (may barangay)
  $stmt = $conn->prepare("
    INSERT INTO iec_deadlines (section_key, scope, barangay, deadline_date, notes, created_by, created_at)
    VALUES (?, 'barangay', ?, ?, ?, ?, NOW())
  ");
  if (!$stmt) die("Prepare failed: ".$conn->error);

  $stmt->bind_param("ssssi", $section_key, $barangay, $deadline_date, $notes, $created_by);
}

if (!$stmt->execute()) {
  die("Execute failed: ".$stmt->error);
}

$stmt->close();

// balik sa same section para makita agad
header('Location: ' . url_with_base('modules/iec/index.php?section=' . rawurlencode((string) $section_key) . '&deadline_saved=1'));
exit;
