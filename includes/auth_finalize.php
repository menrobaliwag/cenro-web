<?php
declare(strict_types=1);

require_once __DIR__ . '/security_bootstrap.php';
require_once __DIR__ . '/role_utils.php';
require_once __DIR__ . '/session_activity_audit.php';
require_once __DIR__ . '/head_admin_allowlist.php';
require_once __DIR__ . '/auth_flow.php';
secure_session_start();

if (!function_exists('auth_set_remember_cookie')) {
    function auth_set_remember_cookie(string $email, bool $remember): void
    {
        $cookieOptions = [
            'expires' => $remember ? time() + (86400 * 30) : time() - 3600,
            'path' => '/',
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        setcookie('remember_email', $remember ? $email : '', $cookieOptions);
    }
}

if (!function_exists('clear_pending_auth_state')) {
    function clear_pending_auth_state(bool $resetState = true): void
    {
        unset(
            $_SESSION['pending_user_id'],
            $_SESSION['pending_email'],
            $_SESSION['pending_name'],
            $_SESSION['pending_role_id'],
            $_SESSION['pending_barangay'],
            $_SESSION['pending_role'],
            $_SESSION['pending_role_name'],
            $_SESSION['pending_permissions'],
            $_SESSION['pending_remember'],
            $_SESSION['pending_auth_ts'],
            $_SESSION['pending_flow'],
            $_SESSION['pending_otp_purpose'],
            $_SESSION['pending_requires_email_otp'],
            $_SESSION['pending_requires_totp_verify'],
            $_SESSION['pending_requires_totp_setup'],
            $_SESSION['pending_trust_device_eligible'],
            $_SESSION['pending_totp_setup_secret'],
            $_SESSION['pending_totp_setup_ts'],
            $_SESSION['pending_totp_failures'],
            $_SESSION['pending_otp_verified'],
            $_SESSION['pending_otp_verified_at'],
            $_SESSION['pending_totp_required'],
            $_SESSION['pending_totp_verified_at'],
            $_SESSION['resend_available_at']
        );

        if ($resetState) {
            auth_flow_reset_state();
        }
    }
}

if (!function_exists('finalize_login_and_redirect')) {
    function finalize_login_and_redirect(mysqli $conn, array $userRow, string $method = 'OTP'): void
    {
        $uid = (int)($userRow['id'] ?? 0);
        $email = data_normalize_email((string)($userRow['email'] ?? ''));
        if ($uid <= 0 || $email === '') {
            clear_pending_auth_state();
            header('Location: ' . url_with_base('auth/index.php'));
            exit();
        }

        $passwordVerified = (bool)($_SESSION['password_verified'] ?? false);
        $emailVerified = (bool)($_SESSION['email_verified'] ?? false);
        $twofaVerified = (bool)($_SESSION['twofa_verified'] ?? false);
        if (!$passwordVerified || !$emailVerified || !$twofaVerified) {
            clear_pending_auth_state();
            header('Location: ' . url_with_base('auth/index.php'));
            exit();
        }

        $roleId = (int)($_SESSION['pending_role_id'] ?? ($userRow['role_id'] ?? 0));
        $role = trim((string)($_SESSION['pending_role'] ?? ''));
        if ($role === '') {
            $role = 'guest';
        }
        $resolvedRole = normalize_role_key($role);

        if ($resolvedRole === 'head_admin') {
            $ip = security_client_ip();
            if (!head_admin_ip_is_allowed($conn, $ip)) {
                log_audit(
                    $uid,
                    $email,
                    'ACCESS_DENIED',
                    'Authentication',
                    'head_admin_login',
                    (string)$uid,
                    'Head admin login blocked by IP allowlist.',
                    ['ip' => $ip]
                );
                clear_pending_auth_state();
                header('Location: ' . url_with_base('auth/index.php?blocked=ip'));
                exit();
            }
        }

        $remember = (int)($_SESSION['pending_remember'] ?? 0) === 1;
        $trustDeviceEligible = (int)($_SESSION['pending_trust_device_eligible'] ?? 1) === 1;
        session_regenerate_id(true);

        $_SESSION['user_id'] = $uid;
        $_SESSION['user_email'] = $email;

        $defaultName = !empty($userRow['name']) ? (string)$userRow['name'] : ucfirst(explode('@', $email)[0]);
        $_SESSION['user_name'] = (string)($_SESSION['pending_name'] ?? $defaultName);
        $_SESSION['barangay'] = (string)($_SESSION['pending_barangay'] ?? ($userRow['barangay'] ?? ''));
        $_SESSION['role_id'] = $roleId;
        $_SESSION['role'] = $resolvedRole;
        $_SESSION['role_name'] = $resolvedRole;
        $_SESSION['user_role'] = $resolvedRole;

        $permissions = $_SESSION['pending_permissions'] ?? [];
        if (!is_array($permissions)) {
            $permissions = [];
        }
        $_SESSION['permissions'] = $permissions;
        $_SESSION['mfa_method'] = $method;
        auth_flow_set_state(true, true, true);
        auth_flow_touch_activity();

        auth_set_remember_cookie($email, $remember);
        if ($remember && $trustDeviceEligible) {
            auth_flow_register_trusted_device($conn, $uid, 30);
        }

        $sessionRowId = register_user_session_audit($uid, $email, (string)$_SESSION['role']);
        log_audit(
            $uid,
            $email,
            'LOGIN',
            'Authentication',
            'user',
            (string)$uid,
            'User login success via ' . $method . '.',
            ['session_row_id' => $sessionRowId, 'method' => $method]
        );

        clear_pending_auth_state(false);

        $roleLanding = role_landing_map();
        $target = $roleLanding[(string)$_SESSION['role']] ?? url_with_base('dashboard.php');
        header('Location: ' . $target);
        exit();
    }
}
