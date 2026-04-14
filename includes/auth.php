<?php
require_once dirname(__DIR__) . '/includes/security_bootstrap.php';
require_once dirname(__DIR__) . '/includes/role_utils.php';
require_once dirname(__DIR__) . '/includes/auth_flow.php';
secure_session_start();

if (!isset($conn) || !($conn instanceof mysqli)) {
    require_once dirname(__DIR__) . '/config/db.php';
}
data_user_form_ensure_encryption_columns($conn);

require_once dirname(__DIR__) . '/includes/session_activity_audit.php';

if (!auth_flow_is_fully_authenticated()) {
    auth_flow_clear_authenticated_identity();
    header('Location: ' . url_with_base('auth/index.php'));
    exit;
}

if (auth_flow_session_timed_out()) {
    close_user_session_audit('expired', 'Session timed out.');
    auth_flow_clear_authenticated_identity();
    header('Location: ' . url_with_base('auth/index.php?session=expired'));
    exit;
}

auth_flow_touch_activity();
enforce_user_session_audit();
touch_user_session_audit();
if (function_exists('audit_register_auto_request_logger')) {
    audit_register_auto_request_logger();
}
require_once dirname(__DIR__) . '/includes/head_admin_allowlist.php';

function ensureUserSettingsTable(mysqli $conn): bool
{
    $createSql = "
        CREATE TABLE IF NOT EXISTS user_settings (
            user_id INT NOT NULL,
            ui_tone VARCHAR(20) NOT NULL DEFAULT 'green',
            topbar_tone VARCHAR(20) NOT NULL DEFAULT 'green',
            sidebar_tone VARCHAR(20) NOT NULL DEFAULT 'green',
            avatar_path VARCHAR(255) NULL DEFAULT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    if (!$conn->query($createSql)) {
        return false;
    }

    $requiredCols = [
        'ui_tone' => "VARCHAR(20) NOT NULL DEFAULT 'green'",
        'topbar_tone' => "VARCHAR(20) NOT NULL DEFAULT 'green'",
        'sidebar_tone' => "VARCHAR(20) NOT NULL DEFAULT 'green'",
        'avatar_path' => "VARCHAR(255) NULL DEFAULT NULL",
    ];
    foreach ($requiredCols as $colName => $colSql) {
        $col = $conn->query("SHOW COLUMNS FROM user_settings LIKE '" . $conn->real_escape_string($colName) . "'");
        if (!$col || $col->num_rows === 0) {
            if (!$conn->query("ALTER TABLE user_settings ADD COLUMN " . $colName . " " . $colSql)) {
                return false;
            }
        }
    }

    return true;
}

// Ensure barangay is always loaded in session.
if (!isset($_SESSION['barangay']) || $_SESSION['barangay'] === '') {
    $uid = (int)($_SESSION['user_id'] ?? 0);

    if ($uid > 0) {
        $stmt = $conn->prepare("SELECT barangay, barangay_enc, role_id FROM user_form WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($u) {
            $barangay = (string)($u['barangay'] ?? '');
            $barangayEnc = trim((string)($u['barangay_enc'] ?? ''));
            if ($barangayEnc !== '') {
                $decryptedBarangay = data_decrypt_text($barangayEnc, $uid, 'barangay');
                if ($decryptedBarangay !== '') {
                    $barangay = $decryptedBarangay;
                }
            }
            $_SESSION['barangay'] = $barangay;
            if (!isset($_SESSION['role_id'])) {
                $_SESSION['role_id'] = (int)($u['role_id'] ?? 0);
            }
        }
    }
}

// Ensure role identity is always loaded and normalized in session.
$sessionRole = normalize_role_key((string)(
    $_SESSION['role_name']
    ?? ($_SESSION['role'] ?? ($_SESSION['user_role'] ?? ''))
));

if ($sessionRole === '') {
    $roleId = (int)($_SESSION['role_id'] ?? 0);
    if ($roleId > 0) {
        $stmtRole = $conn->prepare('SELECT role_name FROM roles WHERE id = ? LIMIT 1');
        if ($stmtRole) {
            $stmtRole->bind_param('i', $roleId);
            $stmtRole->execute();
            $roleRes = $stmtRole->get_result();
            $roleRow = $roleRes ? $roleRes->fetch_assoc() : null;
            $stmtRole->close();

            $sessionRole = normalize_role_key((string)($roleRow['role_name'] ?? ''));
        }
    }
}

if ($sessionRole !== '') {
    $_SESSION['role_name'] = $sessionRole;
    $_SESSION['role'] = $sessionRole;
    $_SESSION['user_role'] = $sessionRole;
}
enforce_head_admin_ip_allowlist($conn);

// Keep legacy UI session defaults.
if (!isset($_SESSION['topbar_skin'])) $_SESSION['topbar_skin'] = 'skin6';
if (!isset($_SESSION['sidebar_skin'])) $_SESSION['sidebar_skin'] = 'skin6';
if (!isset($_SESSION['system_title'])) $_SESSION['system_title'] = 'Waste Management Dashboard';
if (!isset($_SESSION['ui_tone'])) $_SESSION['ui_tone'] = 'green';
if (!isset($_SESSION['topbar_tone'])) $_SESSION['topbar_tone'] = (string)($_SESSION['ui_tone'] ?? 'green');
if (!isset($_SESSION['sidebar_tone'])) $_SESSION['sidebar_tone'] = (string)($_SESSION['ui_tone'] ?? 'green');
if (!isset($_SESSION['user_avatar']) || (string)$_SESSION['user_avatar'] === '') $_SESSION['user_avatar'] = url_with_base('assets/images/user.png');

$allowedUiTones = [
    'green', 'navy', 'teal', 'forest',
    'emerald', 'royal', 'indigo', 'slate', 'charcoal',
    'maroon', 'sunset', 'plum', 'aqua', 'olive'
];
$uidTone = (int)($_SESSION['user_id'] ?? 0);
if ($uidTone > 0 && ensureUserSettingsTable($conn)) {
    $stmtTone = $conn->prepare(
        "SELECT ui_tone, topbar_tone, sidebar_tone, avatar_path
         FROM user_settings
         WHERE user_id = ?
         LIMIT 1"
    );
    $stmtTone->bind_param('i', $uidTone);
    $stmtTone->execute();
    $toneRow = $stmtTone->get_result()->fetch_assoc();
    $stmtTone->close();

    if ($toneRow) {
        $legacyTone = (string)($toneRow['ui_tone'] ?? 'green');
        $topbarTone = (string)($toneRow['topbar_tone'] ?? $legacyTone);
        $sidebarTone = (string)($toneRow['sidebar_tone'] ?? $legacyTone);
        if (!in_array($topbarTone, $allowedUiTones, true)) $topbarTone = 'green';
        if (!in_array($sidebarTone, $allowedUiTones, true)) $sidebarTone = 'green';
        $_SESSION['topbar_tone'] = $topbarTone;
        $_SESSION['sidebar_tone'] = $sidebarTone;
        $_SESSION['ui_tone'] = $topbarTone;

        $avatarPath = (string)($toneRow['avatar_path'] ?? '');
        if ($avatarPath !== '' && strpos($avatarPath, '..') === false) {
            $_SESSION['user_avatar'] = normalize_public_app_path($avatarPath);
        }
    }
}
