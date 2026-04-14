<?php
require_once dirname(__DIR__) . '/includes/role_utils.php';
require_once dirname(__DIR__) . '/includes/rbac.php';

function resolve_session_role_key(): string
{
    $role = normalize_role_key((string)(
        $_SESSION['role_name']
        ?? ($_SESSION['role'] ?? ($_SESSION['user_role'] ?? ''))
    ));
    if ($role !== '') {
        $_SESSION['role_name'] = $role;
        $_SESSION['role'] = $role;
        $_SESSION['user_role'] = $role;
        return $role;
    }

    $roleId = (int)($_SESSION['role_id'] ?? 0);
    if ($roleId <= 0) {
        return '';
    }

    if (!isset($GLOBALS['conn']) || !($GLOBALS['conn'] instanceof mysqli)) {
        require_once dirname(__DIR__) . '/config/db.php';
    }

    $conn = $GLOBALS['conn'] ?? null;
    if (!($conn instanceof mysqli)) {
        return '';
    }

    $stmt = $conn->prepare('SELECT role_name FROM roles WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return '';
    }

    $stmt->bind_param('i', $roleId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    $dbRole = normalize_role_key((string)($row['role_name'] ?? ''));
    if ($dbRole !== '') {
        $_SESSION['role_name'] = $dbRole;
        $_SESSION['role'] = $dbRole;
        $_SESSION['user_role'] = $dbRole;
    }

    return $dbRole;
}

function hydrate_session_permissions(): void
{
    $existing = $_SESSION['permissions'] ?? null;
    if (is_array($existing) && count($existing) > 0) {
        return;
    }

    $roleId = (int)($_SESSION['role_id'] ?? 0);
    if ($roleId <= 0) {
        $_SESSION['permissions'] = [];
        return;
    }

    if (!isset($GLOBALS['conn']) || !($GLOBALS['conn'] instanceof mysqli)) {
        require_once dirname(__DIR__) . '/config/db.php';
    }

    $conn = $GLOBALS['conn'] ?? null;
    if (!($conn instanceof mysqli)) {
        $_SESSION['permissions'] = [];
        return;
    }

    $stmt = $conn->prepare(
        'SELECT p.perm_key
         FROM role_permissions rp
         JOIN permissions p ON p.id = rp.permission_id
         WHERE rp.role_id = ?'
    );

    if (!$stmt) {
        $_SESSION['permissions'] = [];
        return;
    }

    $stmt->bind_param('i', $roleId);
    $stmt->execute();
    $res = $stmt->get_result();

    $perms = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $perm = trim((string)($row['perm_key'] ?? ''));
        if ($perm !== '') {
            $perms[] = $perm;
        }
    }
    $stmt->close();

    $_SESSION['permissions'] = $perms;
}

/**
 * Check if user can access a permission key
 */
function can(string $perm): bool {
    require_login();
    $perm = trim($perm);
    if ($perm === '') {
        return false;
    }

    $role = resolve_session_role_key();
    if (!rbac_role_can_access_permission($role, $perm)) {
        return false;
    }

    hydrate_session_permissions();
    $perms = $_SESSION['permissions'] ?? [];
    if (!is_array($perms)) {
        $perms = [];
    }
    if (is_super_role($role) || in_array('admin.full', $perms, true)) {
        return true;
    }
    if (in_array($perm, $perms, true)) {
        return true;
    }

    // Wildcard permissions (explicit):
    // - "<prefix>.*" grants all permissions under that prefix.
    //   Example: "mrf.*" grants "mrf.truck", "mrf.delete", etc.
    $parts = explode('.', $perm);
    if (count($parts) >= 2) {
        // Check "mrf.*", then "mrf.truck.*", etc (hierarchical wildcard).
        for ($i = 1; $i < count($parts); $i++) {
            $wildcard = implode('.', array_slice($parts, 0, $i)) . '.*';
            if (in_array($wildcard, $perms, true)) {
                return true;
            }
        }
    }

    // Deny by default.
    return false;
}

/**
 * Require permission or redirect
 */
function requirePermission(string $perm): void {
    require_login();
    if (!can($perm)) {
        abort_403();
    }
}
