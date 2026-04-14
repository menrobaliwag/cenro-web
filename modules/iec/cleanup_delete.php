<?php
require_once dirname(__DIR__, 2) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';
require_once dirname(__DIR__, 2) . '/includes/session_activity_audit.php';

requirePermission('iec.manage');

// Only Head Admin / IEC Admin can delete
require_once dirname(__DIR__, 2) . '/includes/permissions.php';
$isHeadAdmin = can('admin.full');
$isIecUser   = can('iec.manage');
$myBarangay  = $_SESSION['barangay'] ?? '';
$isIecAdmin  = (!$isHeadAdmin && $isIecUser && $myBarangay === '');

if (!$isHeadAdmin && !$isIecAdmin) {
  die('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . url_with_base('modules/iec/iec_data.php?section=cleanup_drive'));
  exit;
}
require_csrf();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  die('Invalid ID');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$userEmail = (string)($_SESSION['user_email'] ?? '');
$reason = trim((string)($_POST['delete_reason'] ?? ''));

$result = soft_delete_record_with_audit(
  $conn,
  'IEC Cleanup Drive',
  'iec_cleanup_drive',
  $id,
  $userId,
  $userEmail,
  $reason
);

if (!$result['ok']) {
  header('Location: ' . url_with_base('modules/iec/iec_data.php?section=cleanup_drive&error=' . rawurlencode((string) $result['message'])));
  exit;
}

header('Location: ' . url_with_base('modules/iec/iec_data.php?section=cleanup_drive&deleted=1'));
exit;
