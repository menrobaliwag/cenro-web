<?php
require_once dirname(__DIR__) . '/includes/security_bootstrap.php';
require_once dirname(__DIR__) . '/includes/role_utils.php';
require_once dirname(__DIR__) . '/includes/head_admin_allowlist.php';
require_once dirname(__DIR__) . '/includes/user_ip_allowlist.php'; // ⭐ NEW
require_once dirname(__DIR__) . '/includes/auth_flow.php';
secure_session_start();

if (!auth_flow_is_fully_authenticated()) {
    auth_flow_clear_authenticated_identity();
    header('Location: ' . url_with_base('auth/index.php'));
    exit;
}

if (auth_flow_session_timed_out()) {
    auth_flow_clear_authenticated_identity();
    header('Location: ' . url_with_base('auth/index.php?session=expired'));
    exit;
}
auth_flow_touch_activity();

enforce_head_admin_ip_allowlist(); // existing
enforce_user_ip_allowlist($conn, (int)($_SESSION['user_id'] ?? 0)); // ⭐ NEW

$role = normalize_role_key((string)(
    $_SESSION['role_name']
    ?? ($_SESSION['role'] ?? ($_SESSION['user_role'] ?? ''))
));
$roleLanding = role_landing_map();

$target = $roleLanding[$role] ?? url_with_base('dashboard.php');

header("Location: " . $target);
exit;
