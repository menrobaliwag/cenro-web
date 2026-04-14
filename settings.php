<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/role_utils.php';
require_once __DIR__ . '/includes/totp.php';
require_once __DIR__ . '/includes/auth_flow.php';
require_once __DIR__ . '/includes/head_admin_allowlist.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: ' . url_with_base('auth/index.php'));
    exit();
}

$currentRoleKey = resolve_session_role_key();
$isHeadAdmin = $currentRoleKey === 'head_admin';
$isAdmin = $currentRoleKey === 'admin';
$canManageAccounts = $isHeadAdmin || $isAdmin;
$canViewActivity = $isHeadAdmin;
data_user_form_ensure_encryption_columns($conn);

$flash = [
    'type' => '',
    'text' => '',
];
if (isset($_SESSION['settings_flash']) && is_array($_SESSION['settings_flash'])) {
    $flash['type'] = (string)($_SESSION['settings_flash']['type'] ?? '');
    $flash['text'] = (string)($_SESSION['settings_flash']['text'] ?? '');
    unset($_SESSION['settings_flash']);
}
$allowedUiTones = [
    'green', 'navy', 'teal', 'forest',
    'emerald', 'royal', 'indigo', 'slate', 'charcoal',
    'maroon', 'sunset', 'plum', 'aqua', 'olive'
];
$tonePalette = [
    'green' => ['label' => 'Green', 'hex' => '#198754'],
    'navy' => ['label' => 'Navy Blue', 'hex' => '#202A44'],
    'teal' => ['label' => 'Teal', 'hex' => '#0f766e'],
    'forest' => ['label' => 'Forest', 'hex' => '#1e6b41'],
    'emerald' => ['label' => 'Emerald', 'hex' => '#10b981'],
    'royal' => ['label' => 'Royal Blue', 'hex' => '#2563eb'],
    'indigo' => ['label' => 'Indigo', 'hex' => '#4338ca'],
    'slate' => ['label' => 'Slate', 'hex' => '#334155'],
    'charcoal' => ['label' => 'Charcoal', 'hex' => '#1f2937'],
    'maroon' => ['label' => 'Maroon', 'hex' => '#7f1d1d'],
    'sunset' => ['label' => 'Sunset', 'hex' => '#ea580c'],
    'plum' => ['label' => 'Plum', 'hex' => '#7e22ce'],
    'aqua' => ['label' => 'Aqua', 'hex' => '#0891b2'],
    'olive' => ['label' => 'Olive', 'hex' => '#4d7c0f'],
];
$allowedLandingPages = ['dashboard', 'mrf', 'iec', 'reports'];
$allowedRowsPerPage = [10, 25, 50, 100];
$allowedTimeFormats = ['12hr', '24hr'];
$allowedDateFormats = ['MM/DD/YYYY', 'DD/MM/YYYY'];
$currentTopbarTone = (string)($_SESSION['topbar_tone'] ?? ($_SESSION['ui_tone'] ?? 'green'));
$currentSidebarTone = (string)($_SESSION['sidebar_tone'] ?? ($_SESSION['ui_tone'] ?? 'green'));
if (!in_array($currentTopbarTone, $allowedUiTones, true)) $currentTopbarTone = 'green';
if (!in_array($currentSidebarTone, $allowedUiTones, true)) $currentSidebarTone = 'green';

function loadCurrentUser(mysqli $conn, int $userId): ?array
{
    $stmt = $conn->prepare(
        "SELECT id, name, email, barangay, password, email_hash, email_enc, name_enc, barangay_enc
         FROM user_form
         WHERE id = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$row) {
        return null;
    }

    $row = data_user_row_apply_decryption($row);
    data_sync_user_row_if_needed($conn, $row);
    return $row;
}

function ensureUserAppearanceTable(mysqli $conn): bool
{
    $createSql = "
        CREATE TABLE IF NOT EXISTS user_settings (
            user_id INT NOT NULL,
            ui_tone VARCHAR(20) NOT NULL DEFAULT 'green',
            topbar_tone VARCHAR(20) NOT NULL DEFAULT 'green',
            sidebar_tone VARCHAR(20) NOT NULL DEFAULT 'green',
            avatar_path VARCHAR(255) NULL DEFAULT NULL,
            default_landing_page VARCHAR(60) NOT NULL DEFAULT 'dashboard',
            table_rows_per_page INT NOT NULL DEFAULT 25,
            time_format VARCHAR(8) NOT NULL DEFAULT '12hr',
            date_format VARCHAR(12) NOT NULL DEFAULT 'MM/DD/YYYY',
            compact_mode TINYINT(1) NOT NULL DEFAULT 0,
            larger_text TINYINT(1) NOT NULL DEFAULT 0,
            reduce_animation TINYINT(1) NOT NULL DEFAULT 0,
            notif_email TINYINT(1) NOT NULL DEFAULT 1,
            notif_system_alerts TINYINT(1) NOT NULL DEFAULT 1,
            notif_report_approvals TINYINT(1) NOT NULL DEFAULT 0,
            notif_record_updates TINYINT(1) NOT NULL DEFAULT 1,
            notif_maintenance TINYINT(1) NOT NULL DEFAULT 0,
            security_pin_hash VARCHAR(255) NULL DEFAULT NULL,
            otp_enabled TINYINT(1) NOT NULL DEFAULT 0,
            totp_secret_enc LONGTEXT NULL DEFAULT NULL,
            totp_enabled_at DATETIME NULL DEFAULT NULL,
            totp_last_used_at DATETIME NULL DEFAULT NULL,
            last_login_at DATETIME NULL DEFAULT NULL,
            last_login_ip VARCHAR(64) NULL DEFAULT NULL,
            last_login_device VARCHAR(190) NULL DEFAULT NULL,
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
        'default_landing_page' => "VARCHAR(60) NOT NULL DEFAULT 'dashboard'",
        'table_rows_per_page' => "INT NOT NULL DEFAULT 25",
        'time_format' => "VARCHAR(8) NOT NULL DEFAULT '12hr'",
        'date_format' => "VARCHAR(12) NOT NULL DEFAULT 'MM/DD/YYYY'",
        'compact_mode' => "TINYINT(1) NOT NULL DEFAULT 0",
        'larger_text' => "TINYINT(1) NOT NULL DEFAULT 0",
        'reduce_animation' => "TINYINT(1) NOT NULL DEFAULT 0",
        'notif_email' => "TINYINT(1) NOT NULL DEFAULT 1",
        'notif_system_alerts' => "TINYINT(1) NOT NULL DEFAULT 1",
        'notif_report_approvals' => "TINYINT(1) NOT NULL DEFAULT 0",
        'notif_record_updates' => "TINYINT(1) NOT NULL DEFAULT 1",
        'notif_maintenance' => "TINYINT(1) NOT NULL DEFAULT 0",
        'security_pin_hash' => "VARCHAR(255) NULL DEFAULT NULL",
        'otp_enabled' => "TINYINT(1) NOT NULL DEFAULT 0",
        'totp_secret_enc' => "LONGTEXT NULL DEFAULT NULL",
        'totp_enabled_at' => "DATETIME NULL DEFAULT NULL",
        'totp_last_used_at' => "DATETIME NULL DEFAULT NULL",
        'last_login_at' => "DATETIME NULL DEFAULT NULL",
        'last_login_ip' => "VARCHAR(64) NULL DEFAULT NULL",
        'last_login_device' => "VARCHAR(190) NULL DEFAULT NULL",
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

function loadUserAppearance(mysqli $conn, int $userId, array $allowedUiTones): array
{
    if (!ensureUserAppearanceTable($conn)) {
        return ['topbar_tone' => 'green', 'sidebar_tone' => 'green'];
    }

    $stmt = $conn->prepare(
        "SELECT ui_tone, topbar_tone, sidebar_tone
         FROM user_settings
         WHERE user_id = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    $legacy = (string)($row['ui_tone'] ?? 'green');
    $topbarTone = (string)($row['topbar_tone'] ?? $legacy);
    $sidebarTone = (string)($row['sidebar_tone'] ?? $legacy);

    if (!in_array($topbarTone, $allowedUiTones, true)) $topbarTone = 'green';
    if (!in_array($sidebarTone, $allowedUiTones, true)) $sidebarTone = 'green';

    return ['topbar_tone' => $topbarTone, 'sidebar_tone' => $sidebarTone];
}

function saveUserAppearance(mysqli $conn, int $userId, string $topbarTone, string $sidebarTone): bool
{
    if (!ensureUserAppearanceTable($conn)) {
        return false;
    }

    $stmt = $conn->prepare(
        "INSERT INTO user_settings (user_id, ui_tone, topbar_tone, sidebar_tone)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            ui_tone = VALUES(ui_tone),
            topbar_tone = VALUES(topbar_tone),
            sidebar_tone = VALUES(sidebar_tone)"
    );
    $stmt->bind_param('isss', $userId, $topbarTone, $topbarTone, $sidebarTone);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function loadUserAvatar(mysqli $conn, int $userId): string
{
    $fallback = url_with_base('assets/images/user.png');
    if (!ensureUserAppearanceTable($conn)) {
        return $fallback;
    }

    $stmt = $conn->prepare(
        "SELECT avatar_path
         FROM user_settings
         WHERE user_id = ?
         LIMIT 1"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    $avatarPath = (string)($row['avatar_path'] ?? '');
    if ($avatarPath === '' || strpos($avatarPath, '..') !== false) {
        return $fallback;
    }

    return normalize_public_app_path($avatarPath);
}

function saveUserAvatar(mysqli $conn, int $userId, string $avatarPath): bool
{
    if (!ensureUserAppearanceTable($conn)) {
        return false;
    }

    $stmt = $conn->prepare(
        "INSERT INTO user_settings (user_id, avatar_path)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE
            avatar_path = VALUES(avatar_path)"
    );
    $stmt->bind_param('is', $userId, $avatarPath);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function processAvatarUpload(int $userId, array $file): array
{
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'Image is too large.',
            UPLOAD_ERR_FORM_SIZE => 'Image is too large.',
            UPLOAD_ERR_PARTIAL => 'Image upload was interrupted.',
            UPLOAD_ERR_NO_FILE => 'Please select an image to upload.',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporary upload folder is missing.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write uploaded image.',
            UPLOAD_ERR_EXTENSION => 'Image upload blocked by extension.',
        ];
        return ['ok' => false, 'path' => '', 'message' => $messages[$uploadError] ?? 'Unable to upload image.'];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return ['ok' => false, 'path' => '', 'message' => 'Invalid uploaded image file.'];
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > (2 * 1024 * 1024)) {
        return ['ok' => false, 'path' => '', 'message' => 'Image must be up to 2MB only.'];
    }

    $mimeType = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mimeType = (string)finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
        }
    }
    if ($mimeType === '' && function_exists('mime_content_type')) {
        $mimeType = (string)mime_content_type($tmpPath);
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($allowed[$mimeType])) {
        return ['ok' => false, 'path' => '', 'message' => 'Only JPG, PNG, WEBP or GIF images are allowed.'];
    }

    $uploadDir = __DIR__ . '/uploads/profile';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        return ['ok' => false, 'path' => '', 'message' => 'Cannot create profile upload folder.'];
    }

    try {
        $token = bin2hex(random_bytes(4));
    } catch (Throwable $e) {
        $token = substr(md5((string)microtime(true)), 0, 8);
    }
    $filename = 'u' . $userId . '_' . date('YmdHis') . '_' . $token . '.' . $allowed[$mimeType];
    $targetAbs = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmpPath, $targetAbs)) {
        return ['ok' => false, 'path' => '', 'message' => 'Failed to move uploaded image.'];
    }

    return ['ok' => true, 'path' => url_with_base('uploads/profile/' . $filename), 'message' => ''];
}

function ensureUserActivityLogTable(mysqli $conn): bool
{
    $sql = "
        CREATE TABLE IF NOT EXISTS user_activity_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            action_text VARCHAR(190) NOT NULL,
            module_name VARCHAR(120) NOT NULL,
            device VARCHAR(190) NOT NULL,
            ip_address VARCHAR(64) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_created (user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    return (bool)$conn->query($sql);
}

function getClientIpAddress(): string
{
    return substr(security_client_ip(), 0, 64);
}

function getClientDeviceSummary(): string
{
    $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '') {
        return 'Unknown Device';
    }

    $device = 'Desktop';
    if (preg_match('/Mobile|Android|iPhone|iPad/i', $ua)) {
        $device = 'Mobile';
    }

    $browser = 'Browser';
    if (stripos($ua, 'Edg/') !== false) {
        $browser = 'Edge';
    } elseif (stripos($ua, 'Chrome/') !== false) {
        $browser = 'Chrome';
    } elseif (stripos($ua, 'Firefox/') !== false) {
        $browser = 'Firefox';
    } elseif (stripos($ua, 'Safari/') !== false) {
        $browser = 'Safari';
    }

    return substr($browser . ' on ' . $device, 0, 190);
}

function logUserActivity(mysqli $conn, int $userId, string $actionText, string $moduleName): void
{
    if ($userId <= 0 || $actionText === '') {
        return;
    }
    if (!ensureUserActivityLogTable($conn)) {
        return;
    }

    $device = getClientDeviceSummary();
    $ip = getClientIpAddress();

    $stmt = $conn->prepare(
        "INSERT INTO user_activity_logs (user_id, action_text, module_name, device, ip_address)
         VALUES (?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('issss', $userId, $actionText, $moduleName, $device, $ip);
    $stmt->execute();
    $stmt->close();
}

function loadUserRecentActivity(mysqli $conn, int $userId, int $limit = 20): array
{
    if ($userId <= 0 || !ensureUserActivityLogTable($conn)) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $stmt = $conn->prepare(
        "SELECT created_at, action_text, module_name, device, ip_address
         FROM user_activity_logs
         WHERE user_id = ?
         ORDER BY id DESC
         LIMIT " . $limit
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();
    return $rows;
}

function loadUserPortalSettings(mysqli $conn, int $userId): array
{
    $defaults = [
        'default_landing_page' => 'dashboard',
        'table_rows_per_page' => 25,
        'time_format' => '12hr',
        'date_format' => 'MM/DD/YYYY',
        'compact_mode' => 0,
        'larger_text' => 0,
        'reduce_animation' => 0,
        'notif_email' => 1,
        'notif_system_alerts' => 1,
        'notif_report_approvals' => 0,
        'notif_record_updates' => 1,
        'notif_maintenance' => 0,
        'security_pin_hash' => '',
        'otp_enabled' => 0,
        'totp_secret_enc' => '',
        'totp_enabled_at' => null,
        'totp_last_used_at' => null,
        'last_login_at' => null,
        'last_login_ip' => '',
        'last_login_device' => '',
    ];

    if (!ensureUserAppearanceTable($conn)) {
        return $defaults;
    }

    $stmt = $conn->prepare(
        "SELECT default_landing_page, table_rows_per_page, time_format, date_format,
                compact_mode, larger_text, reduce_animation,
                notif_email, notif_system_alerts, notif_report_approvals, notif_record_updates, notif_maintenance,
                security_pin_hash, otp_enabled, totp_secret_enc, totp_enabled_at, totp_last_used_at,
                last_login_at, last_login_ip, last_login_device
         FROM user_settings
         WHERE user_id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return $defaults;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$row) {
        return $defaults;
    }

    return array_merge($defaults, $row);
}

function saveUserPreferences(mysqli $conn, int $userId, array $prefs): bool
{
    if (!ensureUserAppearanceTable($conn)) {
        return false;
    }

    $landing = (string)($prefs['default_landing_page'] ?? 'dashboard');
    $rows = (int)($prefs['table_rows_per_page'] ?? 25);
    $timeFormat = (string)($prefs['time_format'] ?? '12hr');
    $dateFormat = (string)($prefs['date_format'] ?? 'MM/DD/YYYY');
    $compact = (int)($prefs['compact_mode'] ?? 0);
    $larger = (int)($prefs['larger_text'] ?? 0);
    $reduce = (int)($prefs['reduce_animation'] ?? 0);

    $stmt = $conn->prepare(
        "INSERT INTO user_settings
            (user_id, default_landing_page, table_rows_per_page, time_format, date_format, compact_mode, larger_text, reduce_animation)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            default_landing_page = VALUES(default_landing_page),
            table_rows_per_page = VALUES(table_rows_per_page),
            time_format = VALUES(time_format),
            date_format = VALUES(date_format),
            compact_mode = VALUES(compact_mode),
            larger_text = VALUES(larger_text),
            reduce_animation = VALUES(reduce_animation)"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('isissiii', $userId, $landing, $rows, $timeFormat, $dateFormat, $compact, $larger, $reduce);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function saveUserNotifications(mysqli $conn, int $userId, array $notifications): bool
{
    if (!ensureUserAppearanceTable($conn)) {
        return false;
    }

    $notifEmail = (int)($notifications['notif_email'] ?? 0);
    $notifSystem = (int)($notifications['notif_system_alerts'] ?? 0);
    $notifApprovals = (int)($notifications['notif_report_approvals'] ?? 0);
    $notifUpdates = (int)($notifications['notif_record_updates'] ?? 0);
    $notifMaintenance = (int)($notifications['notif_maintenance'] ?? 0);

    $stmt = $conn->prepare(
        "INSERT INTO user_settings
            (user_id, notif_email, notif_system_alerts, notif_report_approvals, notif_record_updates, notif_maintenance)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            notif_email = VALUES(notif_email),
            notif_system_alerts = VALUES(notif_system_alerts),
            notif_report_approvals = VALUES(notif_report_approvals),
            notif_record_updates = VALUES(notif_record_updates),
            notif_maintenance = VALUES(notif_maintenance)"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iiiiii', $userId, $notifEmail, $notifSystem, $notifApprovals, $notifUpdates, $notifMaintenance);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function formatRoleLabel(string $roleKey): string
{
    $clean = trim($roleKey);
    if ($clean === '') {
        return 'Unassigned';
    }
    $clean = str_replace(['_', '-'], ' ', strtolower($clean));
    $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;
    return ucwords($clean);
}

function accountBarangayOptions(): array
{
    return [
        'Bagong Nayon',
        'Barangca',
        'Calantipay',
        'Catulinan',
        'Concepcion',
        'Hinukay',
        'Makinabang',
        'Matangtubig',
        'Pagala',
        'Paitan',
        'Piel',
        'Pinagbarilan',
        'Poblacion',
        'Sabang',
        'San Jose',
        'San Roque',
        'Santa Barbara',
        'Santo Cristo',
        'Santo Niño',
        'Subic',
        'Sulivan',
        'Tangos',
        'Tarcan',
        'Tiaong',
        'Tibag',
        'Tilapayong',
        'Virgen Delas Flores',
    ];
}

function accountRoleRequiresBarangay(string $roleName): bool
{
    $roleKey = normalize_role_key($roleName);
    if ($roleKey === 'iec_secretary') {
        return true;
    }

    // Future-proof: any explicitly barangay-scoped custom role naming.
    return strpos($roleKey, 'barangay') !== false;
}

function canonicalBarangayValue(string $input, array $allowedBarangays): string
{
    $value = trim($input);
    if ($value === '') {
        return '';
    }

    foreach ($allowedBarangays as $allowed) {
        if (strcasecmp($allowed, $value) === 0) {
            return $allowed;
        }
    }

    return '';
}

function userFormHasCreatedAtColumn(mysqli $conn): bool
{
    static $checked = false;
    static $hasColumn = false;

    if ($checked) {
        return $hasColumn;
    }

    $checked = true;
    $col = $conn->query("SHOW COLUMNS FROM user_form LIKE 'created_at'");
    $hasColumn = (bool)($col && $col->num_rows > 0);
    return $hasColumn;
}

function loadAccountRoleOptions(mysqli $conn, bool $includeHeadAdmin = true): array
{
    $rows = [];
    $res = $conn->query("SELECT id, role_name FROM roles ORDER BY role_name ASC");
    if (!$res) {
        return $rows;
    }

    while ($row = $res->fetch_assoc()) {
        $roleName = (string)($row['role_name'] ?? '');
        if (!$includeHeadAdmin && normalize_role_key($roleName) === 'head_admin') {
            continue;
        }

        $rows[] = [
            'id' => (int)($row['id'] ?? 0),
            'role_name' => $roleName,
        ];
    }

    return $rows;
}

function loadManagedAccounts(mysqli $conn, bool $includeHeadAdmin = true): array
{
    $rows = [];
    $createdAtSelect = userFormHasCreatedAtColumn($conn) ? ', u.created_at' : ', NULL AS created_at';

    $sql = "SELECT u.id, u.name, u.email, u.barangay, u.email_hash, u.email_enc, u.name_enc, u.barangay_enc, u.status, u.role_id, r.role_name{$createdAtSelect}
            FROM user_form u
            LEFT JOIN roles r ON r.id = u.role_id";
    if (!$includeHeadAdmin) {
        $sql .= " WHERE (r.role_name IS NULL OR LOWER(TRIM(r.role_name)) <> 'head_admin')";
    }
    $sql .= " ORDER BY u.id DESC";

    $res = $conn->query($sql);
    if (!$res) {
        return $rows;
    }

    while ($row = $res->fetch_assoc()) {
        $row = data_user_row_apply_decryption($row);
        data_sync_user_row_if_needed($conn, $row);
        $rows[] = [
            'id' => (int)($row['id'] ?? 0),
            'name' => (string)($row['name'] ?? ''),
            'email' => (string)($row['email'] ?? ''),
            'barangay' => (string)($row['barangay'] ?? ''),
            'status' => strtolower(trim((string)($row['status'] ?? 'inactive'))),
            'role_id' => (int)($row['role_id'] ?? 0),
            'role_name' => (string)($row['role_name'] ?? ''),
            'created_at' => (string)($row['created_at'] ?? ''),
        ];
    }

    return $rows;
}

$userRow = loadCurrentUser($conn, $userId);
if (!$userRow) {
    secure_session_start();
    session_unset();
    session_destroy();
    header('Location: ' . url_with_base('auth/index.php'));
    exit();
}

$appearance = loadUserAppearance($conn, $userId, $allowedUiTones);
$currentTopbarTone = (string)($appearance['topbar_tone'] ?? 'green');
$currentSidebarTone = (string)($appearance['sidebar_tone'] ?? 'green');
$currentAvatar = loadUserAvatar($conn, $userId);
$_SESSION['topbar_tone'] = $currentTopbarTone;
$_SESSION['sidebar_tone'] = $currentSidebarTone;
$_SESSION['ui_tone'] = $currentTopbarTone;
$_SESSION['user_avatar'] = $currentAvatar;

$portalSettings = loadUserPortalSettings($conn, $userId);
$preferences = [
    'default_landing_page' => (string)($portalSettings['default_landing_page'] ?? 'dashboard'),
    'table_rows_per_page' => (int)($portalSettings['table_rows_per_page'] ?? 25),
    'time_format' => (string)($portalSettings['time_format'] ?? '12hr'),
    'date_format' => (string)($portalSettings['date_format'] ?? 'MM/DD/YYYY'),
    'compact_mode' => (int)($portalSettings['compact_mode'] ?? 0),
    'larger_text' => (int)($portalSettings['larger_text'] ?? 0),
    'reduce_animation' => (int)($portalSettings['reduce_animation'] ?? 0),
];
if (!in_array($preferences['default_landing_page'], $allowedLandingPages, true)) $preferences['default_landing_page'] = 'dashboard';
if (!in_array($preferences['table_rows_per_page'], $allowedRowsPerPage, true)) $preferences['table_rows_per_page'] = 25;
if (!in_array($preferences['time_format'], $allowedTimeFormats, true)) $preferences['time_format'] = '12hr';
if (!in_array($preferences['date_format'], $allowedDateFormats, true)) $preferences['date_format'] = 'MM/DD/YYYY';

$lastLoginAt = (string)($portalSettings['last_login_at'] ?? '');
$lastLoginIp = (string)($portalSettings['last_login_ip'] ?? '');
$lastLoginDevice = (string)($portalSettings['last_login_device'] ?? '');
if ($lastLoginAt === '') $lastLoginAt = 'Not Available';
if ($lastLoginIp === '') $lastLoginIp = '0.0.0.0';
if ($lastLoginDevice === '') $lastLoginDevice = 'Unknown Device';

$totpConfig = totp_load_user_config($conn, $userId);
$totpEnabled = !empty($totpConfig['enabled']);
$totpEnabledAt = trim((string)($totpConfig['enabled_at'] ?? ''));
$totpLastUsedAt = trim((string)($totpConfig['last_used_at'] ?? ''));
$storedTotpSecret = trim((string)($totpConfig['secret'] ?? ''));

$pendingTotpSetupSecret = trim((string)($_SESSION['totp_setup_secret'] ?? ''));
$pendingTotpSetupTs = (int)($_SESSION['totp_setup_ts'] ?? 0);
if ($pendingTotpSetupSecret !== '' && ($pendingTotpSetupTs <= 0 || (time() - $pendingTotpSetupTs) > 900)) {
    unset($_SESSION['totp_setup_secret'], $_SESSION['totp_setup_ts']);
    $pendingTotpSetupSecret = '';
}

$totpIssuer = 'CITY ENRO';
$totpAccount = trim((string)($userRow['email'] ?? ''));
if ($totpAccount === '') {
    $totpAccount = 'user' . $userId . '@city-enro.local';
}
$totpProvisioningUri = '';
if (!$totpEnabled && $pendingTotpSetupSecret !== '') {
    $totpProvisioningUri = totp_build_provisioning_uri($totpIssuer, $totpAccount, $pendingTotpSetupSecret);
}

$headAdminAllowlist = $isHeadAdmin ? head_admin_allowlist_rows($conn) : [];

$allowedTabs = ['general', 'security'];
if ($canManageAccounts) {
    $allowedTabs[] = 'accounts';
}
if ($canViewActivity) {
    $allowedTabs[] = 'activity';
}
$activeTab = (string)($_GET['tab'] ?? 'general');
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'general';
}

$recentActivity = $canViewActivity ? loadUserRecentActivity($conn, $userId, 20) : [];
$accountBarangayOptions = accountBarangayOptions();
$accountRoleOptions = $canManageAccounts ? loadAccountRoleOptions($conn, $isHeadAdmin) : [];
$managedAccounts = $isHeadAdmin ? loadManagedAccounts($conn, true) : [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');
    $activeTabFromPost = (string)($_POST['active_tab'] ?? 'general');
    if (!in_array($activeTabFromPost, $allowedTabs, true)) {
        $activeTabFromPost = 'general';
    }

    if ($action === 'account_create') {
        if (!$canManageAccounts) {
            $flash = ['type' => 'danger', 'text' => 'Only Admin or Head Admin can manage accounts.'];
        } else {
            $newName = trim((string)($_POST['account_name'] ?? ''));
            $newEmail = strtolower(trim((string)($_POST['account_email'] ?? '')));
            $newPassword = (string)($_POST['account_password'] ?? '');
            $newPasswordCheck = validate_password_policy($newPassword, true);
            $newRoleId = (int)($_POST['account_role_id'] ?? 0);
            $newBarangay = trim((string)($_POST['account_barangay'] ?? ''));
            $newStatus = strtolower(trim((string)($_POST['account_status'] ?? 'active')));

            if ($newStatus !== 'active' && $newStatus !== 'inactive') {
                $newStatus = 'active';
            }

            if ($newName === '') {
                $flash = ['type' => 'danger', 'text' => 'Account name is required.'];
            } elseif (strlen($newName) > 120) {
                $flash = ['type' => 'danger', 'text' => 'Account name is too long (max 120 characters).'];
            } elseif ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $flash = ['type' => 'danger', 'text' => 'Valid email is required.'];
            } elseif (!$newPasswordCheck['ok']) {
                $flash = ['type' => 'danger', 'text' => (string)$newPasswordCheck['error']];
            } elseif ($newRoleId <= 0) {
                $flash = ['type' => 'danger', 'text' => 'Please select a role.'];
            } else {
                $roleName = '';
                $stmtRole = $conn->prepare("SELECT role_name FROM roles WHERE id = ? LIMIT 1");
                if ($stmtRole) {
                    $stmtRole->bind_param('i', $newRoleId);
                    $stmtRole->execute();
                    $roleRow = $stmtRole->get_result()->fetch_assoc();
                    $stmtRole->close();
                    $roleName = (string)($roleRow['role_name'] ?? '');
                }

                if ($roleName === '') {
                    $flash = ['type' => 'danger', 'text' => 'Selected role was not found.'];
                } elseif (normalize_role_key($roleName) === 'head_admin' && !$isHeadAdmin) {
                    $flash = ['type' => 'danger', 'text' => 'Only Head Admin can create a Head Admin account.'];
                } else {
                    $requiresBarangay = accountRoleRequiresBarangay($roleName);
                    $canonicalBarangay = canonicalBarangayValue($newBarangay, $accountBarangayOptions);

                    if ($newBarangay !== '' && $canonicalBarangay === '') {
                        $flash = ['type' => 'danger', 'text' => 'Please select a valid barangay from the list.'];
                    } elseif ($requiresBarangay && $canonicalBarangay === '') {
                        $flash = ['type' => 'danger', 'text' => 'Barangay is required for this role.'];
                    } else {
                        // Main accounts must not be tied to a barangay.
                        $newBarangay = $requiresBarangay ? $canonicalBarangay : '';
                        $existing = data_find_user_by_email($conn, $newEmail);
                        if ($existing) {
                            $flash = ['type' => 'danger', 'text' => 'Email already exists.'];
                        }
                    }
                }
            }

            if ($flash['text'] === '') {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                if (userFormHasCreatedAtColumn($conn)) {
                    $stmtInsert = $conn->prepare(
                        "INSERT INTO user_form (name, email, password, role_id, barangay, status, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, NOW())"
                    );
                    if ($stmtInsert) {
                        $stmtInsert->bind_param('sssiss', $newName, $newEmail, $hash, $newRoleId, $newBarangay, $newStatus);
                    }
                } else {
                    $stmtInsert = $conn->prepare(
                        "INSERT INTO user_form (name, email, password, role_id, barangay, status)
                         VALUES (?, ?, ?, ?, ?, ?)"
                    );
                    if ($stmtInsert) {
                        $stmtInsert->bind_param('sssiss', $newName, $newEmail, $hash, $newRoleId, $newBarangay, $newStatus);
                    }
                }

                if (!isset($stmtInsert) || !$stmtInsert) {
                    $flash = ['type' => 'danger', 'text' => 'Unable to create account right now.'];
                } elseif ($stmtInsert->execute()) {
                    $newAccountId = (int)$stmtInsert->insert_id;
                    $stmtInsert->close();
                    data_sync_user_encryption($conn, $newAccountId, $newName, $newEmail, $newBarangay);
                    $flash = ['type' => 'success', 'text' => 'Account created successfully.'];
                    logUserActivity($conn, $userId, 'Created account: ' . $newEmail, 'Account Management');
                    log_audit(
                        $userId,
                        (string)($_SESSION['user_email'] ?? ''),
                        'CREATE',
                        'Account Management',
                        'user_form',
                        (string)$newAccountId,
                        'Head admin created a new user account.',
                        [
                            'created_user_email' => $newEmail,
                            'role_id' => $newRoleId,
                            'status' => $newStatus,
                        ]
                    );
                } else {
                    $insertError = (string)$stmtInsert->error;
                    $stmtInsert->close();
                    $flash = ['type' => 'danger', 'text' => 'Failed to create account. ' . ($insertError !== '' ? $insertError : '')];
                }
            }
        }
    }

    if ($action === 'account_reset_2fa') {
        if (!$isHeadAdmin) {
            $flash = ['type' => 'danger', 'text' => 'Only Head Admin can reset user 2FA.'];
        } else {
            $targetUserId = (int)($_POST['target_user_id'] ?? 0);
            $result = auth_flow_admin_reset_twofa($conn, $targetUserId);
            $flash = ['type' => !empty($result['ok']) ? 'success' : 'danger', 'text' => (string)($result['message'] ?? 'Unable to reset user 2FA.')];

            if (!empty($result['ok'])) {
                logUserActivity($conn, $userId, 'Reset 2FA for user #' . $targetUserId, 'Account Management');
                log_audit(
                    $userId,
                    (string)($_SESSION['user_email'] ?? ''),
                    'UPDATE',
                    'Account Management',
                    'user_2fa',
                    (string)$targetUserId,
                    'Head admin reset user 2FA.',
                    ['target_user_id' => $targetUserId]
                );
            }
        }
    }

    if ($action === 'profile') {
        $name = trim((string)($_POST['name'] ?? ''));
        $newAvatarPath = '';
        $oldAvatarPath = loadUserAvatar($conn, $userId);

        if ($name === '') {
            $flash = ['type' => 'danger', 'text' => 'Name is required.'];
        } elseif (strlen($name) > 120) {
            $flash = ['type' => 'danger', 'text' => 'Name is too long (max 120 characters).'];
        } else {
            $avatarFile = $_FILES['profile_avatar'] ?? null;
            $hasAvatarUpload = is_array($avatarFile) && (int)($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            if ($hasAvatarUpload) {
                $uploadResult = processAvatarUpload($userId, $avatarFile);
                if (!$uploadResult['ok']) {
                    $flash = ['type' => 'danger', 'text' => (string)$uploadResult['message']];
                } else {
                    $newAvatarPath = (string)$uploadResult['path'];
                }
            }
        }

        if ($flash['text'] === '') {
            $stmt = $conn->prepare("UPDATE user_form SET name = ? WHERE id = ?");
            $stmt->bind_param('si', $name, $userId);
            if ($stmt->execute()) {
                data_sync_user_encryption(
                    $conn,
                    $userId,
                    $name,
                    (string)($userRow['email'] ?? ''),
                    (string)($userRow['barangay'] ?? '')
                );
                $_SESSION['user_name'] = $name;
                $userRow['name'] = $name;

                if ($newAvatarPath !== '') {
                    if (saveUserAvatar($conn, $userId, $newAvatarPath)) {
                        $_SESSION['user_avatar'] = $newAvatarPath;
                        $currentAvatar = $newAvatarPath;
                        if (
                            $oldAvatarPath !== $newAvatarPath &&
                            str_starts_with(ltrim(security_strip_legacy_base_path($oldAvatarPath), '/'), 'uploads/profile/')
                        ) {
                            $oldAvatarAbs = APP_ROOT . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim(security_strip_legacy_base_path($oldAvatarPath), '/'));
                            if (is_file($oldAvatarAbs)) {
                                @unlink($oldAvatarAbs);
                            }
                        }
                        $flash = ['type' => 'success', 'text' => 'Profile updated successfully.'];
                        logUserActivity($conn, $userId, 'Updated profile details and photo', 'Account Settings');
                        log_audit(
                            $userId,
                            (string)($_SESSION['user_email'] ?? ''),
                            'UPDATE',
                            'Account Settings',
                            'user_form',
                            (string)$userId,
                            'Updated profile details and profile photo.',
                            ['avatar_updated' => true]
                        );
                    } else {
                        $newAvatarAbs = APP_ROOT . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim(security_strip_legacy_base_path($newAvatarPath), '/'));
                        if (is_file($newAvatarAbs)) {
                            @unlink($newAvatarAbs);
                        }
                        $flash = ['type' => 'danger', 'text' => 'Profile name updated but failed to save profile photo.'];
                    }
                } else {
                    $flash = ['type' => 'success', 'text' => 'Profile updated successfully.'];
                    logUserActivity($conn, $userId, 'Updated profile details', 'Account Settings');
                    log_audit(
                        $userId,
                        (string)($_SESSION['user_email'] ?? ''),
                        'UPDATE',
                        'Account Settings',
                        'user_form',
                        (string)$userId,
                        'Updated profile details.',
                        ['avatar_updated' => false]
                    );
                }
            } else {
                if ($newAvatarPath !== '') {
                    $newAvatarAbs = $_SERVER['DOCUMENT_ROOT'] . $newAvatarPath;
                    if (is_file($newAvatarAbs)) {
                        @unlink($newAvatarAbs);
                    }
                }
                $flash = ['type' => 'danger', 'text' => 'Failed to update profile.'];
            }
            $stmt->close();
        }
    }

    if ($action === 'password') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $passwordCheck = validate_password_policy($newPassword, true);

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $flash = ['type' => 'danger', 'text' => 'All password fields are required.'];
        } elseif (!password_verify($currentPassword, (string)($userRow['password'] ?? ''))) {
            $flash = ['type' => 'danger', 'text' => 'Current password is incorrect.'];
        } elseif (!$passwordCheck['ok']) {
            $flash = ['type' => 'danger', 'text' => (string)$passwordCheck['error']];
        } elseif ($newPassword !== $confirmPassword) {
            $flash = ['type' => 'danger', 'text' => 'New password and confirmation do not match.'];
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE user_form SET password = ? WHERE id = ?");
            $stmt->bind_param('si', $newHash, $userId);
            if ($stmt->execute()) {
                $flash = ['type' => 'success', 'text' => 'Password updated successfully.'];
                $userRow['password'] = $newHash;
                logUserActivity($conn, $userId, 'Changed account password', 'Security');
                log_audit(
                    $userId,
                    (string)($_SESSION['user_email'] ?? ''),
                    'UPDATE',
                    'Security',
                    'user_form',
                    (string)$userId,
                    'Changed account password.',
                    null
                );
            } else {
                $flash = ['type' => 'danger', 'text' => 'Failed to update password.'];
            }
            $stmt->close();
        }
    }

    if ($action === 'appearance') {
        $topbarTone = (string)($_POST['topbar_tone'] ?? 'green');
        $sidebarTone = (string)($_POST['sidebar_tone'] ?? 'green');
        if (!in_array($topbarTone, $allowedUiTones, true)) $topbarTone = 'green';
        if (!in_array($sidebarTone, $allowedUiTones, true)) $sidebarTone = 'green';

        if (saveUserAppearance($conn, $userId, $topbarTone, $sidebarTone)) {
            $_SESSION['topbar_tone'] = $topbarTone;
            $_SESSION['sidebar_tone'] = $sidebarTone;
            $_SESSION['ui_tone'] = $topbarTone;
            $currentTopbarTone = $topbarTone;
            $currentSidebarTone = $sidebarTone;
            $flash = ['type' => 'success', 'text' => 'Appearance updated successfully.'];
            logUserActivity($conn, $userId, 'Updated topbar/sidebar theme', 'Preferences');
            log_audit(
                $userId,
                (string)($_SESSION['user_email'] ?? ''),
                'UPDATE',
                'Preferences',
                'user_settings',
                (string)$userId,
                'Updated appearance theme.',
                ['topbar_tone' => $topbarTone, 'sidebar_tone' => $sidebarTone]
            );
        } else {
            $flash = ['type' => 'danger', 'text' => 'Failed to save appearance.'];
        }
    }

    if ($action === 'preferences') {
        $landing = (string)($_POST['default_landing_page'] ?? 'dashboard');
        $rows = (int)($_POST['table_rows_per_page'] ?? 25);
        $timeFormat = (string)($_POST['time_format'] ?? '12hr');
        $dateFormat = (string)($_POST['date_format'] ?? 'MM/DD/YYYY');
        $compactMode = isset($_POST['compact_mode']) ? 1 : 0;
        $largerText = isset($_POST['larger_text']) ? 1 : 0;
        $reduceAnimation = isset($_POST['reduce_animation']) ? 1 : 0;

        if (!in_array($landing, $allowedLandingPages, true)) $landing = 'dashboard';
        if (!in_array($rows, $allowedRowsPerPage, true)) $rows = 25;
        if (!in_array($timeFormat, $allowedTimeFormats, true)) $timeFormat = '12hr';
        if (!in_array($dateFormat, $allowedDateFormats, true)) $dateFormat = 'MM/DD/YYYY';

        $prefsPayload = [
            'default_landing_page' => $landing,
            'table_rows_per_page' => $rows,
            'time_format' => $timeFormat,
            'date_format' => $dateFormat,
            'compact_mode' => $compactMode,
            'larger_text' => $largerText,
            'reduce_animation' => $reduceAnimation,
        ];

        if (saveUserPreferences($conn, $userId, $prefsPayload)) {
            $preferences = $prefsPayload;
            $flash = ['type' => 'success', 'text' => 'Preferences updated successfully.'];
            logUserActivity($conn, $userId, 'Updated user preferences', 'Preferences');
            log_audit(
                $userId,
                (string)($_SESSION['user_email'] ?? ''),
                'UPDATE',
                'Preferences',
                'user_settings',
                (string)$userId,
                'Updated user preferences.',
                $prefsPayload
            );
        } else {
            $flash = ['type' => 'danger', 'text' => 'Failed to save preferences.'];
        }
    }

    if ($action === 'logout_other_sessions') {
        $currentSessionRowId = (int)($_SESSION['audit_session_row_id'] ?? 0);
        $reason = 'User requested logout of other sessions';
        $status = 'forced_logout';
        $stmt = $conn->prepare(
            "UPDATE user_sessions
             SET status = ?,
                 logout_at = NOW(),
                 invalidated_at = NOW(),
                 invalidated_by_user_id = ?,
                 invalidated_reason = ?
             WHERE user_id = ?
               AND status = 'active'
               AND id <> ?"
        );

        if ($stmt) {
            $stmt->bind_param('sisii', $status, $userId, $reason, $userId, $currentSessionRowId);
            $stmt->execute();
            $affected = (int)$stmt->affected_rows;
            $stmt->close();

            $flash = ['type' => 'success', 'text' => $affected > 0
                ? ('Logged out ' . $affected . ' other active session(s).')
                : 'No other active sessions found.'];
            logUserActivity($conn, $userId, 'Requested logout of other sessions', 'Security');
            log_audit(
                $userId,
                (string)($_SESSION['user_email'] ?? ''),
                'LOGOUT',
                'Security',
                'user_session',
                'multiple',
                'Logged out other active sessions.',
                ['affected_sessions' => $affected]
            );
        } else {
            $flash = ['type' => 'danger', 'text' => 'Unable to process logout for other sessions.'];
        }
    }

    if ($action === 'totp_begin_setup') {
        if ($totpEnabled) {
            $flash = ['type' => 'warning', 'text' => 'Google Authenticator is already enabled.'];
        } else {
            $newSecret = totp_generate_secret(20);
            if ($newSecret === '') {
                $flash = ['type' => 'danger', 'text' => 'Unable to start authenticator setup right now.'];
            } else {
                $_SESSION['totp_setup_secret'] = $newSecret;
                $_SESSION['totp_setup_ts'] = time();
                $flash = ['type' => 'info', 'text' => 'Scan the QR code and enter the 6-digit code to finish setup.'];
            }
        }
    }

    if ($action === 'totp_enable') {
        $setupSecret = trim((string)($_SESSION['totp_setup_secret'] ?? ''));
        $setupTs = (int)($_SESSION['totp_setup_ts'] ?? 0);
        $totpCode = trim((string)($_POST['totp_setup_code'] ?? ''));

        if ($setupSecret === '' || $setupTs <= 0 || (time() - $setupTs) > 900) {
            unset($_SESSION['totp_setup_secret'], $_SESSION['totp_setup_ts']);
            $flash = ['type' => 'warning', 'text' => 'Authenticator setup expired. Please start setup again.'];
        } elseif (!preg_match('/^[0-9]{6}$/', $totpCode)) {
            $flash = ['type' => 'danger', 'text' => 'Enter a valid 6-digit authenticator code.'];
        } elseif (!totp_verify_code($setupSecret, $totpCode, 1)) {
            $flash = ['type' => 'danger', 'text' => 'Invalid authenticator code.'];
        } elseif (totp_enable_for_user($conn, $userId, $setupSecret)) {
            $backupCodes = auth_flow_replace_backup_codes($conn, $userId, 8);
            if ($backupCodes !== []) {
                auth_flow_send_backup_codes_email((string)($userRow['email'] ?? ''), $backupCodes);
            }
            unset($_SESSION['totp_setup_secret'], $_SESSION['totp_setup_ts']);
            $flash = ['type' => 'success', 'text' => 'Google Authenticator has been enabled.'];
            logUserActivity($conn, $userId, 'Enabled Google Authenticator 2FA', 'Security');
            log_audit(
                $userId,
                (string)($_SESSION['user_email'] ?? ''),
                'UPDATE',
                'Security',
                'user_settings',
                (string)$userId,
                'Enabled Google Authenticator 2FA.',
                null
            );
        } else {
            $flash = ['type' => 'danger', 'text' => 'Failed to enable authenticator.'];
        }
    }

    if ($action === 'totp_disable') {
        $password = (string)($_POST['totp_disable_password'] ?? '');
        $totpCode = trim((string)($_POST['totp_disable_code'] ?? ''));

        if (!$totpEnabled || $storedTotpSecret === '') {
            $flash = ['type' => 'warning', 'text' => 'Google Authenticator is not enabled.'];
        } elseif ($password === '' || !password_verify($password, (string)($userRow['password'] ?? ''))) {
            $flash = ['type' => 'danger', 'text' => 'Current password is incorrect.'];
        } elseif (!preg_match('/^[0-9]{6}$/', $totpCode)) {
            $flash = ['type' => 'danger', 'text' => 'Enter a valid 6-digit authenticator code.'];
        } elseif (!totp_verify_code($storedTotpSecret, $totpCode, 1)) {
            $flash = ['type' => 'danger', 'text' => 'Invalid authenticator code.'];
        } else {
            $disableResult = auth_flow_admin_reset_twofa($conn, $userId);
            if (empty($disableResult['ok'])) {
                $flash = ['type' => 'danger', 'text' => 'Failed to disable authenticator.'];
            } else {
            unset($_SESSION['totp_setup_secret'], $_SESSION['totp_setup_ts']);
            $flash = ['type' => 'success', 'text' => 'Google Authenticator has been disabled.'];
            logUserActivity($conn, $userId, 'Disabled Google Authenticator 2FA', 'Security');
            log_audit(
                $userId,
                (string)($_SESSION['user_email'] ?? ''),
                'UPDATE',
                'Security',
                'user_settings',
                (string)$userId,
                'Disabled Google Authenticator 2FA.',
                null
            );
            }
        }
    }

    if ($action === 'allowlist_add') {
        if (!$isHeadAdmin) {
            $flash = ['type' => 'danger', 'text' => 'Only Head Admin can manage IP allowlist.'];
        } else {
            $rule = (string)($_POST['allowlist_ip_or_cidr'] ?? '');
            $note = (string)($_POST['allowlist_note'] ?? '');
            $result = head_admin_allowlist_add(
                $conn,
                $rule,
                $note,
                $userId,
                (string)($_SESSION['user_email'] ?? '')
            );
            $flash = ['type' => $result['ok'] ? 'success' : 'danger', 'text' => (string)$result['message']];
            if (!empty($result['ok'])) {
                log_audit(
                    $userId,
                    (string)($_SESSION['user_email'] ?? ''),
                    'CREATE',
                    'Security',
                    'head_admin_ip_allowlist',
                    (string)($result['rule'] ?? ''),
                    'Added/updated head admin IP allowlist rule.',
                    ['rule' => (string)($result['rule'] ?? ''), 'note' => trim($note)]
                );
            }
        }
    }

    if ($action === 'allowlist_remove') {
        if (!$isHeadAdmin) {
            $flash = ['type' => 'danger', 'text' => 'Only Head Admin can manage IP allowlist.'];
        } else {
            $rowId = (int)($_POST['allowlist_id'] ?? 0);
            $result = head_admin_allowlist_remove($conn, $rowId);
            $flash = ['type' => $result['ok'] ? 'success' : 'danger', 'text' => (string)$result['message']];
            if (!empty($result['ok'])) {
                log_audit(
                    $userId,
                    (string)($_SESSION['user_email'] ?? ''),
                    'DELETE',
                    'Security',
                    'head_admin_ip_allowlist',
                    (string)$rowId,
                    'Removed head admin IP allowlist rule.',
                    null
                );
            }
        }
    }

    $_SESSION['settings_flash'] = $flash;
    $tabQuery = $activeTabFromPost !== 'general' ? ('?tab=' . rawurlencode($activeTabFromPost)) : '';
    header('Location: ' . url_with_base('settings.php' . $tabQuery));
    exit();
}

$displayName = trim((string)($userRow['name'] ?? ''));
if ($displayName === '') {
    $displayName = (string)($_SESSION['user_name'] ?? '');
}
if ($displayName === '') {
    $displayName = 'User';
}

$email = (string)($userRow['email'] ?? ($_SESSION['user_email'] ?? ''));
$barangay = (string)($userRow['barangay'] ?? ($_SESSION['barangay'] ?? ''));

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/topbar_sidebar.php';
?>

<link rel="stylesheet" href="<?= url_with_base('dist/css/settings.css?v=20260212-1') ?>">


<div class="page-wrapper">
  <div class="page-breadcrumb">
    <div class="row">
      <div class="col-5 align-self-center">
        <h4 class="page-title fw-normal text-dark">Settings</h4>
      </div>
      <div class="col-7 align-self-center">
        <div class="d-flex align-items-center justify-content-end">
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent mb-0 p-0">
              <li class="breadcrumb-item"><a href="<?= url_with_base('dashboard.php') ?>" class="text-primary">Home</a></li>
              <li class="breadcrumb-item active text-muted" aria-current="page">Settings</li>
            </ol>
          </nav>
        </div>
      </div>
    </div>
  </div>

  <div class="container-fluid settings-page">
    <?php if ($flash['text'] !== ''): ?>
      <div id="settingsFlash" class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?> mb-3 fade show" role="alert">
        <?= htmlspecialchars($flash['text'], ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <ul class="nav nav-pills settings-tabs-nav" id="settingsTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'general' ? 'active' : '' ?>" id="settings-general-tab" data-bs-toggle="tab" data-bs-target="#settings-general" type="button" role="tab" aria-controls="settings-general" aria-selected="<?= $activeTab === 'general' ? 'true' : 'false' ?>">General</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'security' ? 'active' : '' ?>" id="settings-security-tab" data-bs-toggle="tab" data-bs-target="#settings-security" type="button" role="tab" aria-controls="settings-security" aria-selected="<?= $activeTab === 'security' ? 'true' : 'false' ?>">Security</button>
      </li>
      <?php if ($canManageAccounts): ?>
      <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'accounts' ? 'active' : '' ?>" id="settings-accounts-tab" data-bs-toggle="tab" data-bs-target="#settings-accounts" type="button" role="tab" aria-controls="settings-accounts" aria-selected="<?= $activeTab === 'accounts' ? 'true' : 'false' ?>">Accounts</button>
      </li>
      <?php endif; ?>
      <?php if ($canViewActivity): ?>
      <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'activity' ? 'active' : '' ?>" id="settings-activity-tab" data-bs-toggle="tab" data-bs-target="#settings-activity" type="button" role="tab" aria-controls="settings-activity" aria-selected="<?= $activeTab === 'activity' ? 'true' : 'false' ?>">Activity Logs</button>
      </li>
      <?php endif; ?>
    </ul>

    <div class="tab-content" id="settingsTabsContent">
      <div class="tab-pane fade settings-tab-pane<?= $activeTab === 'general' ? ' show active' : '' ?>" id="settings-general" role="tabpanel" aria-labelledby="settings-general-tab" tabindex="0">
        <div class="row g-3">
          <div class="col-lg-6">
            <div class="card">
              <div class="card-body">
                <h5 class="card-title mb-3">Change Profile</h5>
                <form method="POST" novalidate enctype="multipart/form-data">
                  <?= csrf_input(); ?>
                  <input type="hidden" name="action" value="profile">
                  <input type="hidden" name="active_tab" value="general">

                  <div class="mb-3">
                    <label class="form-label fw-semibold" for="settings_profile_avatar">Profile Photo</label>
                    <div class="settings-profile-photo">
                      <img
                        src="<?= htmlspecialchars((string)$currentAvatar, ENT_QUOTES, 'UTF-8') ?>"
                        alt="Profile"
                        class="settings-avatar-preview rounded-circle">
                      <input
                        type="file"
                        class="form-control"
                        id="settings_profile_avatar"
                        name="profile_avatar"
                        accept="image/jpeg,image/png,image/webp,image/gif">
                    </div>
                    <small class="text-muted d-block mt-1">JPG, PNG, WEBP or GIF, max 2MB.</small>
                  </div>

                  <div class="mb-3">
                    <label class="form-label fw-semibold" for="settings_name">Display Name</label>
                    <input type="text" class="form-control" id="settings_name" name="name" maxlength="120"
                           value="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>" required>
                  </div>

                  <div class="mb-3">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" class="form-control" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" readonly>
                  </div>

                  <div class="mb-3">
                    <label class="form-label fw-semibold">Barangay</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($barangay, ENT_QUOTES, 'UTF-8') ?>" readonly>
                  </div>

                  <button type="submit" class="btn btn-primary">Save Profile</button>
                </form>
              </div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="card">
              <div class="card-body">
                <h5 class="card-title mb-3">Change Password</h5>
                <form method="POST" novalidate>
                  <?= csrf_input(); ?>
                  <input type="hidden" name="action" value="password">
                  <input type="hidden" name="active_tab" value="general">

                  <div class="mb-3">
                    <label class="form-label fw-semibold" for="settings_current_password">Current Password</label>
                    <input type="password" class="form-control" id="settings_current_password" name="current_password" autocomplete="current-password" required>
                  </div>

                  <div class="mb-3">
                    <label class="form-label fw-semibold" for="settings_new_password">New Password</label>
                    <input type="password" class="form-control" id="settings_new_password" name="new_password" minlength="10" autocomplete="new-password" required>
                  </div>

                  <div class="mb-3">
                    <label class="form-label fw-semibold" for="settings_confirm_password">Confirm New Password</label>
                    <input type="password" class="form-control" id="settings_confirm_password" name="confirm_password" minlength="10" autocomplete="new-password" required>
                  </div>

                  <button type="submit" class="btn btn-primary">Update Password</button>
                </form>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="card">
              <div class="card-body">
                <h5 class="card-title mb-3">Appearance</h5>
                <form method="POST" novalidate id="appearanceForm" onsubmit="return false;">
                  <?= csrf_input(); ?>

                  <div class="appearance-theme-grid mb-3">
                    <div class="appearance-panel">
                      <label class="form-label fw-semibold">Topbar Theme</label>
                      <div class="tone-grid" id="settings_topbar_tone">
                        <?php foreach ($tonePalette as $toneKey => $toneMeta): ?>
                          <?php $inputId = 'topbar_tone_' . $toneKey; ?>
                          <div class="tone-item">
                            <input
                              class="tone-radio"
                              type="radio"
                              name="topbar_tone"
                              id="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>"
                              value="<?= htmlspecialchars($toneKey, ENT_QUOTES, 'UTF-8') ?>"
                              <?= $currentTopbarTone === $toneKey ? 'checked' : '' ?>>
                            <label class="tone-card" for="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>">
                              <span class="tone-swatch" style="--tone-color: <?= htmlspecialchars((string)$toneMeta['hex'], ENT_QUOTES, 'UTF-8') ?>;"></span>
                              <span class="tone-name"><?= htmlspecialchars((string)$toneMeta['label'], ENT_QUOTES, 'UTF-8') ?></span>
                            </label>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>

                    <div class="appearance-panel">
                      <label class="form-label fw-semibold">Sidebar Theme</label>
                      <div class="tone-grid" id="settings_sidebar_tone">
                        <?php foreach ($tonePalette as $toneKey => $toneMeta): ?>
                          <?php $inputId = 'sidebar_tone_' . $toneKey; ?>
                          <div class="tone-item">
                            <input
                              class="tone-radio"
                              type="radio"
                              name="sidebar_tone"
                              id="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>"
                              value="<?= htmlspecialchars($toneKey, ENT_QUOTES, 'UTF-8') ?>"
                              <?= $currentSidebarTone === $toneKey ? 'checked' : '' ?>>
                            <label class="tone-card" for="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>">
                              <span class="tone-swatch" style="--tone-color: <?= htmlspecialchars((string)$toneMeta['hex'], ENT_QUOTES, 'UTF-8') ?>;"></span>
                              <span class="tone-name"><?= htmlspecialchars((string)$toneMeta['label'], ENT_QUOTES, 'UTF-8') ?></span>
                            </label>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>
                  <small class="text-muted d-block mt-2">Saved per account and kept after logout/login.</small>
                  <div id="appearanceStatus" class="small text-muted mt-2" aria-live="polite">Choose a color to save automatically.</div>
                </form>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="card">
              <div class="card-body">
                <h5 class="card-title mb-3">Account Information</h5>
                <div class="row g-3 settings-readonly-grid">
                  <div class="col-md-6 col-xl-4">
                    <label class="form-label" for="settings_account_id">Account ID</label>
                    <input id="settings_account_id" type="text" class="form-control" value="<?= htmlspecialchars((string)$userId, ENT_QUOTES, 'UTF-8') ?>" readonly>
                  </div>
                  <div class="col-md-6 col-xl-4">
                    <label class="form-label" for="settings_account_role">Role</label>
                    <input id="settings_account_role" type="text" class="form-control" value="<?= htmlspecialchars((string)($_SESSION['user_role'] ?? ($_SESSION['role'] ?? 'User')), ENT_QUOTES, 'UTF-8') ?>" readonly>
                  </div>
                  <div class="col-md-6 col-xl-4">
                    <label class="form-label" for="settings_account_dept">Department</label>
                    <input id="settings_account_dept" type="text" class="form-control" value="<?= htmlspecialchars((string)($_SESSION['department'] ?? 'City ENRO'), ENT_QUOTES, 'UTF-8') ?>" readonly>
                  </div>
                  <div class="col-md-6 col-xl-4">
                    <label class="form-label" for="settings_account_status">Status</label>
                    <input id="settings_account_status" type="text" class="form-control" value="<?= htmlspecialchars((string)($_SESSION['account_status'] ?? 'Active'), ENT_QUOTES, 'UTF-8') ?>" readonly>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="tab-pane fade settings-tab-pane<?= $activeTab === 'security' ? ' show active' : '' ?>" id="settings-security" role="tabpanel" aria-labelledby="settings-security-tab" tabindex="0">
        <div class="row g-3">
          <div class="col-12 col-xl-6">
            <div class="card">
              <div class="card-body">
                <h5 class="card-title mb-3">Last Login Info</h5>
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label" for="security_last_login">Last Login Date/Time</label>
                    <input id="security_last_login" type="text" class="form-control" value="<?= htmlspecialchars($lastLoginAt, ENT_QUOTES, 'UTF-8') ?>" readonly>
                  </div>
                  <div class="col-12">
                    <label class="form-label" for="security_last_ip">IP Address</label>
                    <input id="security_last_ip" type="text" class="form-control" value="<?= htmlspecialchars($lastLoginIp, ENT_QUOTES, 'UTF-8') ?>" readonly>
                  </div>
                  <div class="col-12">
                    <label class="form-label" for="security_last_device">Device</label>
                    <input id="security_last_device" type="text" class="form-control" value="<?= htmlspecialchars($lastLoginDevice, ENT_QUOTES, 'UTF-8') ?>" readonly>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-xl-6">
            <div class="card">
              <div class="card-body">
                <h5 class="card-title mb-3">Active Sessions</h5>
                <div class="settings-section-subtitle">Current Session</div>
                <div class="border rounded-3 p-3 mb-3 bg-light">
                  <div class="small text-muted"><?= htmlspecialchars(getClientDeviceSummary(), ENT_QUOTES, 'UTF-8') ?> - This Device</div>
                  <div class="fw-semibold text-dark">Current Session</div>
                </div>
                <div class="settings-section-subtitle">Other Devices</div>
                <ul class="list-group mb-3">
                  <li class="list-group-item d-flex justify-content-between align-items-center">Chrome on Windows <span class="small text-muted">192.168.1.10</span></li>
                  <li class="list-group-item d-flex justify-content-between align-items-center">Android Mobile <span class="small text-muted">192.168.1.22</span></li>
                </ul>
                <form method="POST" novalidate class="d-inline">
                  <?= csrf_input(); ?>
                  <input type="hidden" name="action" value="logout_other_sessions">
                  <input type="hidden" name="active_tab" value="security">
                  <button type="submit" class="btn btn-outline-secondary">Log out other sessions</button>
                </form>
              </div>
            </div>
          </div>

          <div class="col-12 col-xl-6">
            <div class="card">
              <div class="card-body">
                <h5 class="card-title mb-3">Google Authenticator 2FA</h5>
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                  <span class="settings-note mb-0">Status:
                    <strong class="<?= $totpEnabled ? 'text-success' : 'text-muted' ?>">
                      <?= $totpEnabled ? 'Enabled' : 'Disabled' ?>
                    </strong>
                  </span>
                  <?php if ($totpEnabled && $totpEnabledAt !== ''): ?>
                    <span class="settings-note mb-0">Enabled at: <?= htmlspecialchars($totpEnabledAt, ENT_QUOTES, 'UTF-8') ?></span>
                  <?php endif; ?>
                </div>

                <?php if (!$totpEnabled && $pendingTotpSetupSecret === ''): ?>
                  <form method="POST" class="d-inline">
                    <?= csrf_input(); ?>
                    <input type="hidden" name="action" value="totp_begin_setup">
                    <input type="hidden" name="active_tab" value="security">
                    <button type="submit" class="btn btn-primary">Set up Authenticator</button>
                  </form>
                  <div class="settings-note mt-2">After clicking setup, scan the QR code using Google Authenticator and verify one code.</div>
                <?php elseif (!$totpEnabled && $pendingTotpSetupSecret !== ''): ?>
                  <div class="settings-qr-wrap mb-3">
                    <?php if ($totpProvisioningUri !== ''): ?>
                      <div
                        id="settings-totp-qr"
                        class="settings-qr-image"
                        data-otpauth="<?= htmlspecialchars($totpProvisioningUri, ENT_QUOTES, 'UTF-8') ?>"
                      ></div>
                      <script src="<?= url_with_base('assets/vendor/qrcodejs/qrcode.min.js') ?>"></script>
                      <script>
                        (function () {
                          const el = document.getElementById('settings-totp-qr');
                          if (!el) return;
                          const data = el.getAttribute('data-otpauth') || '';
                          if (!data || typeof QRCode === 'undefined') return;
                          el.innerHTML = '';
                          new QRCode(el, { text: data, width: 140, height: 140, correctLevel: QRCode.CorrectLevel.M });
                          el.removeAttribute('data-otpauth');
                        })();
                      </script>
                    <?php endif; ?>
                    <div>
                      <div class="settings-note mb-2">Secret key (manual setup):</div>
                      <code class="settings-secret-code"><?= htmlspecialchars($pendingTotpSetupSecret, ENT_QUOTES, 'UTF-8') ?></code>
                    </div>
                  </div>
                  <form method="POST" novalidate>
                    <?= csrf_input(); ?>
                    <input type="hidden" name="action" value="totp_enable">
                    <input type="hidden" name="active_tab" value="security">
                    <div class="mb-3">
                      <label class="form-label" for="totp_setup_code">Authenticator Code</label>
                      <input type="text" class="form-control" id="totp_setup_code" name="totp_setup_code" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" placeholder="6-digit code" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Enable Authenticator</button>
                  </form>
                <?php else: ?>
                  <?php if ($totpLastUsedAt !== ''): ?>
                    <div class="settings-note mb-2">Last used: <?= htmlspecialchars($totpLastUsedAt, ENT_QUOTES, 'UTF-8') ?></div>
                  <?php endif; ?>
                  <form method="POST" novalidate>
                    <?= csrf_input(); ?>
                    <input type="hidden" name="action" value="totp_disable">
                    <input type="hidden" name="active_tab" value="security">
                    <div class="mb-3">
                      <label class="form-label" for="totp_disable_password">Current Password</label>
                      <input type="password" class="form-control" id="totp_disable_password" name="totp_disable_password" autocomplete="current-password" required>
                    </div>
                    <div class="mb-3">
                      <label class="form-label" for="totp_disable_code">Authenticator Code</label>
                      <input type="text" class="form-control" id="totp_disable_code" name="totp_disable_code" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" placeholder="6-digit code" required>
                    </div>
                    <button type="submit" class="btn btn-outline-secondary">Disable Authenticator</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <?php if ($isHeadAdmin): ?>
          <div class="col-12 col-xl-6">
            <div class="card">
              <div class="card-body">
                <h5 class="card-title mb-3">Head Admin IP Allowlist</h5>
                <div class="settings-note mb-3">Only listed IP/CIDR can access Head Admin sessions. Current IP: <strong><?= htmlspecialchars(getClientIpAddress(), ENT_QUOTES, 'UTF-8') ?></strong></div>
                <form method="POST" novalidate class="row g-2 mb-3">
                  <?= csrf_input(); ?>
                  <input type="hidden" name="action" value="allowlist_add">
                  <input type="hidden" name="active_tab" value="security">
                  <div class="col-md-5">
                    <label class="form-label" for="allowlist_ip_or_cidr">IP or CIDR</label>
                    <input type="text" class="form-control" id="allowlist_ip_or_cidr" name="allowlist_ip_or_cidr" placeholder="203.0.113.10 or 203.0.113.0/24" required>
                  </div>
                  <div class="col-md-5">
                    <label class="form-label" for="allowlist_note">Note</label>
                    <input type="text" class="form-control" id="allowlist_note" name="allowlist_note" maxlength="190" placeholder="Office network">
                  </div>
                  <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Add</button>
                  </div>
                </form>

                <div class="table-responsive settings-table-wrap">
                  <table class="table table-hover align-middle mb-0">
                    <thead>
                      <tr>
                        <th>Rule</th>
                        <th>Note</th>
                        <th>Created</th>
                        <th class="text-end">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (!empty($headAdminAllowlist)): ?>
                        <?php foreach ($headAdminAllowlist as $row): ?>
                          <tr>
                            <td><code><?= htmlspecialchars((string)($row['ip_or_cidr'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                            <td><?= htmlspecialchars((string)($row['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-end">
                              <form method="POST" class="d-inline" onsubmit="return confirm('Remove this allowlist rule?');">
                                <?= csrf_input(); ?>
                                <input type="hidden" name="action" value="allowlist_remove">
                                <input type="hidden" name="active_tab" value="security">
                                <input type="hidden" name="allowlist_id" value="<?= (int)($row['id'] ?? 0) ?>">
                                <button type="submit" class="btn btn-outline-secondary btn-sm">Remove</button>
                              </form>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td colspan="4" class="text-center text-muted py-3">No allowlist rules yet. Head Admin login is currently open to any IP.</td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>

        </div>
      </div>

      <?php if ($canManageAccounts): ?>
      <div class="tab-pane fade settings-tab-pane<?= $activeTab === 'accounts' ? ' show active' : '' ?>" id="settings-accounts" role="tabpanel" aria-labelledby="settings-accounts-tab" tabindex="0">
        <div class="row g-3">
          <div class="<?= $isHeadAdmin ? 'col-12 col-xl-4' : 'col-12' ?>">
            <div class="card">
              <div class="card-body">
                <h5 class="card-title mb-3">Add Account</h5>
                <div class="settings-note settings-admin-note mb-3">
                  <?= $isHeadAdmin
                    ? 'Head Admin can create user accounts and assign all roles.'
                    : 'Admin can create user accounts, but the Head Admin role remains hidden.' ?>
                </div>
                <form method="POST" novalidate>
                  <?= csrf_input(); ?>
                  <input type="hidden" name="action" value="account_create">
                  <input type="hidden" name="active_tab" value="accounts">

                  <div class="mb-3">
                    <label class="form-label" for="account_name">Full Name</label>
                    <input type="text" class="form-control" id="account_name" name="account_name" maxlength="120" required>
                  </div>

                  <div class="mb-3">
                    <label class="form-label" for="account_email">Email</label>
                    <input type="email" class="form-control" id="account_email" name="account_email" maxlength="190" required>
                  </div>

                  <div class="mb-3">
                    <label class="form-label" for="account_password">Temporary Password</label>
                    <input type="password" class="form-control" id="account_password" name="account_password" minlength="10" required>
                  </div>

                  <div class="mb-3">
                    <label class="form-label" for="account_role_id">Role</label>
                    <select class="form-select" id="account_role_id" name="account_role_id" required>
                      <option value="">Select role</option>
                      <?php foreach ($accountRoleOptions as $roleOpt): ?>
                        <?php $roleNameOpt = (string)($roleOpt['role_name'] ?? ''); ?>
                        <option
                          value="<?= (int)($roleOpt['id'] ?? 0) ?>"
                          data-requires-barangay="<?= accountRoleRequiresBarangay($roleNameOpt) ? '1' : '0' ?>"
                        ><?= htmlspecialchars(formatRoleLabel($roleNameOpt), ENT_QUOTES, 'UTF-8') ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="mb-3">
                    <label class="form-label" for="account_barangay">Barangay</label>
                    <select class="form-select" id="account_barangay" name="account_barangay" disabled>
                      <option value="" selected>Not applicable (main account)</option>
                      <?php foreach ($accountBarangayOptions as $barangayOption): ?>
                        <option value="<?= htmlspecialchars($barangayOption, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($barangayOption, ENT_QUOTES, 'UTF-8') ?></option>
                      <?php endforeach; ?>
                    </select>
                    <div class="form-text" id="account_barangay_help">Only required for barangay accounts.</div>
                  </div>

                  <div class="mb-3">
                    <label class="form-label" for="account_status">Status</label>
                    <select class="form-select" id="account_status" name="account_status">
                      <option value="active" selected>Active</option>
                      <option value="inactive">Inactive</option>
                    </select>
                  </div>

                  <button type="submit" class="btn btn-primary w-100">Create Account</button>
                </form>
              </div>
            </div>
          </div>

          <?php if ($isHeadAdmin): ?>
          <div class="col-12 col-xl-8">
            <div class="card">
              <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                  <h5 class="card-title mb-0">All Accounts</h5>
                  <span class="settings-note mb-0">Total: <?= (int)count($managedAccounts) ?></span>
                </div>
                <div class="table-responsive settings-table-wrap">
                  <table class="table table-hover align-middle">
                    <thead>
                      <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Barangay</th>
                        <th>Status</th>
                        <th>Created</th>
                        <?php if ($isHeadAdmin): ?>
                        <th class="text-end">Actions</th>
                        <?php endif; ?>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (!empty($managedAccounts)): ?>
                        <?php foreach ($managedAccounts as $acct): ?>
                          <?php
                            $roleLabel = formatRoleLabel((string)($acct['role_name'] ?? ''));
                            $status = strtolower(trim((string)($acct['status'] ?? 'inactive')));
                            $statusClass = $status === 'active' ? 'is-active' : 'is-inactive';
                            $createdRaw = trim((string)($acct['created_at'] ?? ''));
                            $createdLabel = $createdRaw !== '' ? $createdRaw : 'N/A';
                          ?>
                          <tr>
                            <td><?= (int)($acct['id'] ?? 0) ?></td>
                            <td><?= htmlspecialchars((string)($acct['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-break"><?= htmlspecialchars((string)($acct['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($acct['barangay'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="account-status-badge <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars($createdLabel, ENT_QUOTES, 'UTF-8') ?></td>
                            <?php if ($isHeadAdmin): ?>
                            <td class="text-end">
                              <form method="POST" class="d-inline" onsubmit="return confirm('Reset 2FA for this account? User will be required to set up Authenticator again.');">
                                <?= csrf_input(); ?>
                                <input type="hidden" name="action" value="account_reset_2fa">
                                <input type="hidden" name="active_tab" value="accounts">
                                <input type="hidden" name="target_user_id" value="<?= (int)($acct['id'] ?? 0) ?>">
                                <button type="submit" class="btn btn-outline-warning btn-sm">
                                  Reset 2FA
                                </button>
                              </form>
                            </td>
                            <?php endif; ?>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td colspan="<?= $isHeadAdmin ? '8' : '7' ?>" class="text-center text-muted py-4">No accounts found.</td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($canViewActivity): ?>
      <div class="tab-pane fade settings-tab-pane<?= $activeTab === 'activity' ? ' show active' : '' ?>" id="settings-activity" role="tabpanel" aria-labelledby="settings-activity-tab" tabindex="0">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title mb-3">Activity Logs</h5>
            <div class="table-responsive settings-table-wrap">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th>Device</th>
                    <th>IP Address</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($recentActivity)): ?>
                    <?php foreach ($recentActivity as $log): ?>
                      <tr>
                        <td><?= htmlspecialchars((string)($log['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($log['action_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($log['module_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($log['device'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($log['ip_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td><?= date('Y-m-d H:i:s') ?></td>
                      <td>No recorded activity yet</td>
                      <td>Account Settings</td>
                      <td><?= htmlspecialchars(getClientDeviceSummary(), ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars(getClientIpAddress(), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  (function () {
    const topbarTone = <?= json_encode($currentTopbarTone, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?> || 'green';
    const sidebarTone = <?= json_encode($currentSidebarTone, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?> || 'green';
    document.documentElement.setAttribute('data-topbar-tone', topbarTone);
    document.documentElement.setAttribute('data-sidebar-tone', sidebarTone);
    document.documentElement.setAttribute('data-ui-tone', topbarTone);
    try {
      localStorage.setItem('enro_topbar_tone', topbarTone);
      localStorage.setItem('enro_sidebar_tone', sidebarTone);
      localStorage.setItem('enro_ui_tone', topbarTone);
    } catch (e) {
      // no-op
    }
  })();
</script>

<script>
  (function () {
    const form = document.getElementById('appearanceForm');
    if (!form) return;

    const status = document.getElementById('appearanceStatus');
    const csrfInput = form.querySelector('input[name="csrf_token"]');
    const toneInputs = form.querySelectorAll('input[name="topbar_tone"], input[name="sidebar_tone"]');
    let saving = false;
    let queued = false;

    function setStatus(message, cls) {
      if (!status) return;
      status.className = 'small mt-2 ' + (cls || 'text-muted');
      status.textContent = message;
    }

    function getSelectedTone(name) {
      const selected = form.querySelector('input[name="' + name + '"]:checked');
      return selected ? selected.value : 'green';
    }

    function applyTone(topbarTone, sidebarTone) {
      document.documentElement.setAttribute('data-topbar-tone', topbarTone);
      document.documentElement.setAttribute('data-sidebar-tone', sidebarTone);
      document.documentElement.setAttribute('data-ui-tone', topbarTone);
      try {
        localStorage.setItem('enro_topbar_tone', topbarTone);
        localStorage.setItem('enro_sidebar_tone', sidebarTone);
        localStorage.setItem('enro_ui_tone', topbarTone);
      } catch (e) {
        // no-op
      }
    }

    async function saveAppearance() {
      if (saving) {
        queued = true;
        return;
      }

      saving = true;
      setStatus('Saving appearance...', 'text-muted');

      const topbarTone = getSelectedTone('topbar_tone');
      const sidebarTone = getSelectedTone('sidebar_tone');

      const payload = new FormData();
      payload.append('csrf_token', csrfInput ? csrfInput.value : '');
      payload.append('topbar_tone', topbarTone);
      payload.append('sidebar_tone', sidebarTone);

      try {
        const response = await fetch(<?= json_encode(url_with_base('auth/save_theme.php'), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: payload
        });

        const text = (await response.text()).trim().toLowerCase();
        if (!response.ok || text !== 'success') {
          throw new Error(text || 'save_failed');
        }

        applyTone(topbarTone, sidebarTone);
        setStatus('Appearance saved.', 'text-success');
      } catch (error) {
        setStatus('Failed to save appearance. Please try again.', 'text-danger');
      } finally {
        saving = false;
        if (queued) {
          queued = false;
          saveAppearance();
        }
      }
    }

    toneInputs.forEach(function (input) {
      input.addEventListener('change', saveAppearance);
    });
  })();
</script>

<script>
  (function () {
    const roleSelect = document.getElementById('account_role_id');
    const barangaySelect = document.getElementById('account_barangay');
    const barangayHelp = document.getElementById('account_barangay_help');
    if (!roleSelect || !barangaySelect) return;

    function syncBarangayState() {
      const selected = roleSelect.options[roleSelect.selectedIndex] || null;
      const requiresBarangay = selected && selected.getAttribute('data-requires-barangay') === '1';

      barangaySelect.disabled = !requiresBarangay;
      barangaySelect.required = !!requiresBarangay;

      if (!requiresBarangay) {
        barangaySelect.value = '';
      }
      if (barangayHelp) {
        barangayHelp.textContent = requiresBarangay
          ? 'Required for barangay accounts.'
          : 'Only required for barangay accounts.';
      }
    }

    roleSelect.addEventListener('change', syncBarangayState);
    syncBarangayState();
  })();
</script>

<?php if ($flash['text'] !== ''): ?>
<script>
  (function () {
    const flash = document.getElementById('settingsFlash');
    if (!flash) return;

    setTimeout(function () {
      flash.classList.remove('show');
      setTimeout(function () {
        if (flash.parentNode) {
          flash.parentNode.removeChild(flash);
        }
      }, 250);
    }, 3000);
  })();
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer_scripts.php'; ?>




