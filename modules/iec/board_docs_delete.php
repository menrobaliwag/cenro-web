<?php
require_once dirname(__DIR__, 2) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

requirePermission('iec.manage');

$isAdmin = (($_SESSION['role'] ?? '') === 'admin');
$myBarangay = $_SESSION['barangay'] ?? '';

$id = (int)($_POST['id'] ?? 0);
require_csrf();
if ($id <= 0) die("Invalid ID");

if ($isAdmin) {
  $stmt = $conn->prepare("UPDATE iec_board_docs SET is_deleted=1 WHERE id=?");
  $stmt->bind_param("i", $id);
} else {
  $stmt = $conn->prepare("UPDATE iec_board_docs SET is_deleted=1 WHERE id=? AND barangay=?");
  $stmt->bind_param("is", $id, $myBarangay);
}

if (!$stmt->execute()) die("DB error: ".$stmt->error);

$stmt->close();
$conn->close();

header('Location: ' . url_with_base('modules/iec/index.php?section=board_docs'));
exit;
