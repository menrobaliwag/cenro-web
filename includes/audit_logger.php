<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/security.php';
secure_session_start();

/**
 * Best-effort audit logger.
 *
 * This intentionally never throws/fatals. If the audit_logs table is missing,
 * or insertion fails, it will just return false.
 */
function audit_log(mysqli $conn, array $event): bool {
    $action = (string)($event['action'] ?? '');
    $module = (string)($event['module'] ?? '');

    if ($action === '' || $module === '') return false;

    $userId = (int)($event['user_id'] ?? ($_SESSION['user_id'] ?? 0));
    $userName = (string)($event['user_name'] ?? ($_SESSION['user_name'] ?? ''));
    $entityType = $event['entity_type'] ?? null;
    $entityId = $event['entity_id'] ?? null;
    $description = $event['description'] ?? null;

    $before = $event['before'] ?? null;
    $after = $event['after'] ?? null;

    $beforeJson = is_array($before) ? json_encode($before, JSON_UNESCAPED_UNICODE) : null;
    $afterJson = is_array($after) ? json_encode($after, JSON_UNESCAPED_UNICODE) : null;

    $ip = (string)($event['ip_address'] ?? security_client_ip());
    $ua = (string)($event['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (strlen($ua) > 255) $ua = substr($ua, 0, 255);

    $stmt = @$conn->prepare(
        'INSERT INTO audit_logs (
            user_id, user_name, action, module, entity_type, entity_id, description,
            before_data, after_data, ip_address, user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    if (!$stmt) return false;

    // Bind as strings where NULL may be passed; MySQL will coerce types as needed.
    $entityTypeStr = is_string($entityType) && $entityType !== '' ? $entityType : null;
    $entityIdStr = ($entityId === null || $entityId === '') ? null : (string)$entityId;
    $descriptionStr = is_string($description) && $description !== '' ? $description : null;

    $stmt->bind_param(
        'issssssssss',
        $userId,
        $userName,
        $action,
        $module,
        $entityTypeStr,
        $entityIdStr,
        $descriptionStr,
        $beforeJson,
        $afterJson,
        $ip,
        $ua
    );

    $ok = @$stmt->execute();
    $stmt->close();
    return (bool)$ok;
}
