<?php
require_once dirname(__DIR__) . '/includes/security_bootstrap.php';
require_once dirname(__DIR__) . '/includes/session_activity_audit.php';
require_once dirname(__DIR__) . '/includes/auth_flow.php';
secure_session_start();

if (isset($_SESSION['user_id'])) {
    close_user_session_audit('expired', 'User logged out.');
}

auth_flow_reset_state();

// Unset all session variables
$_SESSION = [];

// Delete session cookie (if exists)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to login
header("Location: index.php");
exit();
?>

