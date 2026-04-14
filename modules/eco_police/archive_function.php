<?php
require_once dirname(__DIR__, 2) . '/includes/security.php';
secure_session_start();
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';
require_once dirname(__DIR__, 2) . '/includes/session_activity_audit.php';

requirePermission('eco.violators');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(["success" => false, "message" => "Invalid request method."]);
  exit;
}
require_csrf();

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(["success" => false, "message" => "Invalid ID."]);
  exit;
}

$result = archive_record_with_audit(
  $conn,
  'Eco Police Violations',
  'violations',
  $id,
  (int)($_SESSION['user_id'] ?? 0),
  (string)($_SESSION['user_email'] ?? '')
);
echo json_encode(["success" => (bool)$result['ok'], "message" => (string)$result['message']]);
exit;
