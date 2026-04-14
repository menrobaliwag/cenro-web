<?php
require_once dirname(__DIR__, 3) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';
require_once dirname(__DIR__, 3) . '/includes/session_activity_audit.php';
header('Content-Type: application/json');

requirePermission('mrf.waste_reduction');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    require_csrf();
    $id = intval($_POST['id']);
    $result = restore_archived_record_with_audit(
        $conn,
        'MRF Waste Reduction',
        'waste_reduction',
        $id,
        (int)($_SESSION['user_id'] ?? 0),
        (string)($_SESSION['user_email'] ?? '')
    );
    echo json_encode(['success' => (bool)$result['ok'], 'message' => (string)$result['message']]);
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}
