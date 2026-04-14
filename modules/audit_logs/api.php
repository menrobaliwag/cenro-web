<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/security.php';
secure_session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['message' => 'Unauthorized.']);
    exit;
}

require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

$entityType = (string)($_GET['entity_type'] ?? '');
$entityId = (string)($_GET['entity_id'] ?? '');

if ($entityType === '' || $entityId === '' || !ctype_digit($entityId)) {
    http_response_code(422);
    echo json_encode(['message' => 'Invalid parameters.']);
    exit;
}

// Entity -> permission mapping (extend per module as needed)
$permByEntity = [
    'truck_record' => 'mrf.truck',
];

if (!isset($permByEntity[$entityType])) {
    http_response_code(404);
    echo json_encode(['message' => 'Unknown entity type.']);
    exit;
}

requirePermission($permByEntity[$entityType]);

$stmt = $conn->prepare(
    'SELECT action, description, user_name, created_at
     FROM audit_logs
     WHERE entity_type = ? AND entity_id = ?
     ORDER BY created_at DESC
     LIMIT 30'
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['message' => 'Audit logs are not available.']);
    exit;
}

$stmt->bind_param('ss', $entityType, $entityId);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
    $items[] = [
        'action' => (string)($row['action'] ?? ''),
        'description' => (string)($row['description'] ?? ''),
        'user_name' => (string)($row['user_name'] ?? ''),
        'created_at' => $row['created_at'] ? date('M j, Y g:i A', strtotime($row['created_at'])) : '',
    ];
}

$stmt->close();

echo json_encode(['items' => $items], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

