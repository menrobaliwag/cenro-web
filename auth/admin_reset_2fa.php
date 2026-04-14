<?php
require_once dirname(__DIR__) . '/includes/security_bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth_flow.php';
require_once dirname(__DIR__) . '/includes/role_utils.php';
secure_session_start();
require_once('../config/db.php');

if (!auth_flow_is_fully_authenticated()) {
    header('Location: ' . url_with_base('auth/index.php'));
    exit();
}

$role = normalize_role_key((string)(
    $_SESSION['role_name']
    ?? ($_SESSION['role'] ?? ($_SESSION['user_role'] ?? ''))
));
if ($role !== 'head_admin') {
    http_response_code(403);
    exit('Forbidden');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

require_csrf();

$targetUserId = (int)($_POST['target_user_id'] ?? 0);
$result = auth_flow_admin_reset_twofa($conn, $targetUserId);

$_SESSION['settings_flash'] = [
    'type' => !empty($result['ok']) ? 'success' : 'danger',
    'text' => (string)($result['message'] ?? 'Unable to reset user 2FA.')
];

header('Location: ' . url_with_base('settings.php?tab=accounts'));
exit();
