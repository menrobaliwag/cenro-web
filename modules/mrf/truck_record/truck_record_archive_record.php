<?php
require_once dirname(__DIR__, 3) . '/includes/security.php';
secure_session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';
require_once dirname(__DIR__, 3) . '/includes/session_activity_audit.php';

requirePermission('mrf.truck');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    require_csrf();
    $id = intval($_POST['id']);
    $result = archive_record_with_audit(
        $conn,
        'MRF Truck Record',
        'truck_record',
        $id,
        (int)($_SESSION['user_id'] ?? 0),
        (string)($_SESSION['user_email'] ?? '')
    );
    echo json_encode(["success" => (bool)$result['ok'], "message" => (string)$result['message']]);
    $conn->close();
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}
