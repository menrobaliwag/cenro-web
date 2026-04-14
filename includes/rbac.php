<?php
require_once dirname(__DIR__) . '/includes/security_bootstrap.php';
require_once dirname(__DIR__) . '/includes/role_utils.php';
require_once dirname(__DIR__) . '/includes/auth_flow.php';
secure_session_start();

if (!function_exists('rbac_normalize_roles')) {
    function rbac_normalize_roles(array $roles): array
    {
        $out = [];
        foreach ($roles as $role) {
            if (!is_scalar($role)) {
                continue;
            }
            $key = normalize_role_key((string)$role);
            if ($key !== '') {
                $out[$key] = true;
            }
        }
        return array_keys($out);
    }
}

if (!function_exists('rbac_current_role')) {
    function rbac_current_role(): string
    {
        $role = normalize_role_key((string)(
            $_SESSION['role_name']
            ?? ($_SESSION['role'] ?? ($_SESSION['user_role'] ?? ''))
        ));
        return $role;
    }
}

if (!function_exists('abort_403')) {
    function abort_403(string $message = 'Access denied.'): void
    {
        if (!headers_sent()) {
            http_response_code(403);
        }

        $isAjax = (
            (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false)
        );

        if ($isAjax) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        require_once dirname(__DIR__) . '/includes/403.php';
        exit;
    }
}

if (!function_exists('require_login')) {
    function require_login(): void
    {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($uid <= 0 || !auth_flow_is_fully_authenticated()) {
            auth_flow_clear_authenticated_identity();
            if (!headers_sent()) {
                header('Location: ' . url_with_base('auth/index.php'));
            }
            exit;
        }

        if (auth_flow_session_timed_out()) {
            if (function_exists('close_user_session_audit')) {
                close_user_session_audit('expired', 'Session timed out.');
            }
            auth_flow_clear_authenticated_identity();
            if (!headers_sent()) {
                header('Location: ' . url_with_base('auth/index.php?session=expired'));
            }
            exit;
        }

        auth_flow_touch_activity();
    }
}

if (!function_exists('require_role')) {
    function require_role(array $allowedRoles): void
    {
        require_login();
        $allowed = rbac_normalize_roles($allowedRoles);
        $current = rbac_current_role();
        if ($current === '' || !in_array($current, $allowed, true)) {
            abort_403('You are not allowed to access this resource.');
        }
    }
}

if (!function_exists('require_any_role')) {
    function require_any_role(array $allowedRoles): void
    {
        require_role($allowedRoles);
    }
}

if (!function_exists('rbac_allowed_roles_for_permission')) {
    function rbac_allowed_roles_for_permission(string $perm): array
    {
        $perm = trim($perm);
        if ($perm === '') {
            return [];
        }

        // Deny by default: permissions not mapped here are denied to non-super roles.
        $prefixMap = [
            'mrf.' => ['mrf_staff'],
            'monitoring.' => ['monitoring'],
            'parks.' => ['parks'],
            'mbcurp.' => ['mbcurp'],
            'iec.' => ['iec_admin', 'iec_secretary'],
            'eco.' => ['eco_police'],
            'palitbasura.' => ['palitbasura'],
        ];

        foreach ($prefixMap as $prefix => $roles) {
            if (str_starts_with($perm, $prefix)) {
                return rbac_normalize_roles($roles);
            }
        }

        return [];
    }
}

if (!function_exists('rbac_role_can_access_permission')) {
    function rbac_role_can_access_permission(string $role, string $perm): bool
    {
        $roleKey = normalize_role_key($role);
        if ($roleKey === '') {
            return false;
        }

        if (is_super_role($roleKey)) {
            return true;
        }

        $allowed = rbac_allowed_roles_for_permission($perm);
        if ($allowed === []) {
            return false;
        }

        return in_array($roleKey, $allowed, true);
    }
}
