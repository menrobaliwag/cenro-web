<?php
require_once dirname(__DIR__) . '/includes/security_bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth_finalize.php';
secure_session_start();

// Legacy PIN flow is retired.
if (auth_flow_is_pending_auth_valid()) {
    header('Location: ' . url_with_base('auth/authenticator.php'));
    exit();
}

clear_pending_auth_state();
header('Location: ' . url_with_base('auth/index.php'));
exit();
