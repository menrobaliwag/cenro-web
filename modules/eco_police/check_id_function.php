<?php
require_once dirname(__DIR__, 2) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

requirePermission('eco.violators');

$id_number = trim($_GET['id_number'] ?? '');
$response = ['exists' => false];

if($id_number !== '') {
    require_rate_limit('check_id:' . strtolower($id_number), 10, 60);
    $stmt = $conn->prepare("SELECT id FROM violations WHERE id_number = ? LIMIT 1");
    $stmt->bind_param("s", $id_number);
    $stmt->execute();
    $stmt->store_result();
    if($stmt->num_rows > 0){
        $response['exists'] = true;
    }
    $stmt->close();
}

header('Content-Type: application/json');
echo json_encode($response);
?>
