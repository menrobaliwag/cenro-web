<?php
require_once dirname(__DIR__, 2) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

requirePermission('iec.manage');

$id = (int)($_POST['id'] ?? 0);
require_csrf();
$stmt = $conn->prepare("UPDATE iec_minutes_meeting SET is_deleted=1 WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

header('Location: ' . url_with_base('modules/iec/iec_data.php?section=minutes_meeting&deleted=1'));
exit;
