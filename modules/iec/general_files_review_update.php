<?php
require_once dirname(__DIR__, 2) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

requirePermission('iec.manage');

$isAdmin = (($_SESSION['role'] ?? '') === 'admin');
if (!$isAdmin) { http_response_code(403); exit('Forbidden'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}
require_csrf();

$id = (int)($_POST['id'] ?? 0);
$submission_status = $_POST['submission_status'] ?? 'Pending';
$review_status = $_POST['review_status'] ?? 'pending';
$remarks = trim($_POST['remarks'] ?? '');

$allowedSubmission = ['Pending','Submitted','Late Submission','Approved','For Revision'];
$allowedReview = ['pending','reviewed','for_revision'];

if (
  $id <= 0 ||
  !in_array($submission_status, $allowedSubmission, true) ||
  !in_array($review_status, $allowedReview, true)
) {
  http_response_code(400);
  exit('Invalid input');
}

$adminId = (int)($_SESSION['user_id'] ?? 0);

$stmt = $conn->prepare("
  UPDATE iec_general_files
  SET submission_status = ?,
      review_status = ?,
      remarks = CASE WHEN ? <> '' THEN ? ELSE remarks END,
      reviewed_by = ?,
      reviewed_at = NOW()
  WHERE id = ? AND is_deleted = 0
");
if (!$stmt) {
  http_response_code(500);
  exit('Prepare failed: ' . $conn->error);
}

$stmt->bind_param("ssssii", $submission_status, $review_status, $remarks, $remarks, $adminId, $id);

if (!$stmt->execute()) {
  http_response_code(500);
  exit('Execute failed: ' . $stmt->error);
}

$stmt->close();

header('Location: ' . url_with_base('modules/iec/iec_data.php?section=general_files'));
exit;
