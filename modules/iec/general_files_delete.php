<?php
require_once dirname(__DIR__, 2) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

requirePermission('iec.manage');

$id = (int)($_POST['id'] ?? 0);
require_csrf();
if ($id <= 0) die("Invalid ID.");

$stmt = $conn->prepare("UPDATE iec_general_files SET is_deleted=1 WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

header('Location: ' . url_with_base('modules/iec/iec_data.php?section=general_files&deleted=1'));
exit;
