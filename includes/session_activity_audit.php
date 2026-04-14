<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/security.php';
secure_session_start();

if (!function_exists('audit_center_db')) {
    function audit_center_db(): ?mysqli
    {
        global $conn;
        if (!isset($conn) || !($conn instanceof mysqli)) {
            require_once dirname(__DIR__) . '/config/db.php';
        }
        if (isset($conn) && $conn instanceof mysqli) {
            return $conn;
        }
        return null;
    }
}

if (!function_exists('audit_has_column')) {
    function audit_has_column(mysqli $conn, string $tableName, string $columnName): bool
    {
        $safeTable = $conn->real_escape_string($tableName);
        $safeColumn = $conn->real_escape_string($columnName);
        $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        return $res instanceof mysqli_result && $res->num_rows > 0;
    }
}

if (!function_exists('audit_add_column_if_missing')) {
    function audit_add_column_if_missing(mysqli $conn, string $tableName, string $columnName, string $columnSql): bool
    {
        if (audit_has_column($conn, $tableName, $columnName)) {
            return true;
        }
        $safeTable = $conn->real_escape_string($tableName);
        $sql = "ALTER TABLE `{$safeTable}` ADD COLUMN `{$columnName}` {$columnSql}";
        return (bool)$conn->query($sql);
    }
}

if (!function_exists('audit_conn_cache_key')) {
    function audit_conn_cache_key(mysqli $conn): string
    {
        if (function_exists('spl_object_id')) {
            return 'conn:' . (string)spl_object_id($conn);
        }
        return 'thread:' . (string)((int)($conn->thread_id ?? 0));
    }
}

if (!function_exists('ensure_audit_center_tables')) {
    function ensure_audit_center_tables(mysqli $conn): bool
    {
        static $ready = [];
        $connKey = audit_conn_cache_key($conn);
        if (($ready[$connKey] ?? false) === true) {
            return true;
        }

        $sql = "
            CREATE TABLE IF NOT EXISTS user_sessions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT NOT NULL,
                email VARCHAR(190) NOT NULL,
                role_name VARCHAR(120) NOT NULL DEFAULT '',
                session_id_hash CHAR(64) NOT NULL,
                login_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                logout_at DATETIME NULL DEFAULT NULL,
                status ENUM('active','expired','forced_logout') NOT NULL DEFAULT 'active',
                ip_address VARCHAR(64) NOT NULL DEFAULT '',
                user_agent VARCHAR(255) NOT NULL DEFAULT '',
                device VARCHAR(190) NOT NULL DEFAULT '',
                location VARCHAR(160) NOT NULL DEFAULT 'Unknown',
                last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                invalidated_at DATETIME NULL DEFAULT NULL,
                invalidated_by_user_id INT NULL DEFAULT NULL,
                invalidated_reason VARCHAR(255) NULL DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_user_sessions_user (user_id, login_at),
                KEY idx_user_sessions_email (email, login_at),
                KEY idx_user_sessions_status (status, last_activity_at),
                KEY idx_user_sessions_role (role_name, login_at),
                KEY idx_user_sessions_hash (session_id_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        if (!$conn->query($sql)) {
            $ready[$connKey] = false;
            return false;
        }

        $sql = "
            CREATE TABLE IF NOT EXISTS audit_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT NULL DEFAULT NULL,
                email VARCHAR(190) NULL DEFAULT NULL,
                user_name VARCHAR(190) NULL DEFAULT NULL,
                action VARCHAR(50) NOT NULL,
                module VARCHAR(120) NOT NULL,
                record_type VARCHAR(120) NULL DEFAULT NULL,
                record_id VARCHAR(120) NULL DEFAULT NULL,
                entity_type VARCHAR(80) NULL DEFAULT NULL,
                entity_id BIGINT NULL DEFAULT NULL,
                description TEXT NULL DEFAULT NULL,
                snapshot_json LONGTEXT NULL DEFAULT NULL,
                before_data LONGTEXT NULL DEFAULT NULL,
                after_data LONGTEXT NULL DEFAULT NULL,
                ip_address VARCHAR(64) NULL DEFAULT NULL,
                location VARCHAR(160) NULL DEFAULT NULL,
                device VARCHAR(190) NULL DEFAULT NULL,
                user_agent VARCHAR(255) NULL DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_audit_user_created (user_id, created_at),
                KEY idx_audit_email_created (email, created_at),
                KEY idx_audit_action_created (action, created_at),
                KEY idx_audit_module_created (module, created_at),
                KEY idx_audit_record_created (record_type, record_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        if (!$conn->query($sql)) {
            $ready[$connKey] = false;
            return false;
        }

        $auditCols = [
            'email' => "VARCHAR(190) NULL DEFAULT NULL",
            'record_type' => "VARCHAR(120) NULL DEFAULT NULL",
            'record_id' => "VARCHAR(120) NULL DEFAULT NULL",
            'snapshot_json' => "LONGTEXT NULL DEFAULT NULL",
            'location' => "VARCHAR(160) NULL DEFAULT NULL",
            'device' => "VARCHAR(190) NULL DEFAULT NULL",
        ];
        foreach ($auditCols as $colName => $colSql) {
            if (!audit_add_column_if_missing($conn, 'audit_logs', $colName, $colSql)) {
                $ready[$connKey] = false;
                return false;
            }
        }

        // Performance indexes for Audit Center filters.
        // Safe to run repeatedly; MySQL will error if exists, so we check first.
        $auditIndexChecks = [
            'idx_audit_created' => 'created_at',
            'idx_audit_module_action_created' => 'module, action, created_at',
            'idx_audit_record_id' => 'record_id',
        ];
        foreach ($auditIndexChecks as $indexName => $cols) {
            $safeIndex = $conn->real_escape_string($indexName);
            $resIdx = $conn->query("SHOW INDEX FROM audit_logs WHERE Key_name = '{$safeIndex}'");
            $exists = ($resIdx instanceof mysqli_result) && $resIdx->num_rows > 0;
            if ($resIdx instanceof mysqli_result) {
                $resIdx->free();
            }
            if ($exists) {
                continue;
            }
            if (!$conn->query("ALTER TABLE audit_logs ADD INDEX {$indexName} ({$cols})")) {
                // If index creation fails due to concurrency, re-check once.
                $resIdx2 = $conn->query("SHOW INDEX FROM audit_logs WHERE Key_name = '{$safeIndex}'");
                $exists2 = ($resIdx2 instanceof mysqli_result) && $resIdx2->num_rows > 0;
                if ($resIdx2 instanceof mysqli_result) {
                    $resIdx2->free();
                }
                if (!$exists2) {
                    $ready[$connKey] = false;
                    return false;
                }
            }
        }

        $sql = "
            CREATE TABLE IF NOT EXISTS deleted_items (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                module_name VARCHAR(120) NOT NULL,
                source_table VARCHAR(120) NOT NULL,
                record_type VARCHAR(120) NOT NULL,
                record_id VARCHAR(120) NOT NULL,
                item_identifier VARCHAR(255) NOT NULL,
                deleted_by_user_id INT NOT NULL,
                deleted_by_email VARCHAR(190) NOT NULL,
                deleted_reason VARCHAR(255) NULL DEFAULT NULL,
                record_snapshot LONGTEXT NOT NULL,
                deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                status ENUM('deleted','restored','purged') NOT NULL DEFAULT 'deleted',
                restored_at DATETIME NULL DEFAULT NULL,
                restored_by_user_id INT NULL DEFAULT NULL,
                permanently_deleted_at DATETIME NULL DEFAULT NULL,
                permanently_deleted_by_user_id INT NULL DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_deleted_items_deleted_at (deleted_at),
                KEY idx_deleted_items_module (module_name, deleted_at),
                KEY idx_deleted_items_status (status, deleted_at),
                KEY idx_deleted_items_email (deleted_by_email, deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        if (!$conn->query($sql)) {
            $ready[$connKey] = false;
            return false;
        }

        $sql = "
            CREATE TABLE IF NOT EXISTS ip_location_cache (
                ip_address VARCHAR(64) NOT NULL,
                location_label VARCHAR(160) NOT NULL DEFAULT 'Unknown',
                cached_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                PRIMARY KEY (ip_address),
                KEY idx_ip_location_expire (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        $ok = (bool)$conn->query($sql);
        $ready[$connKey] = $ok;
        return $ok;
    }
}

if (!function_exists('audit_client_ip')) {
    function audit_client_ip(): string
    {
        return substr(security_client_ip(), 0, 64);
    }
}

if (!function_exists('audit_user_agent')) {
    function audit_user_agent(): string
    {
        $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if ($ua === '') {
            return 'Unknown User Agent';
        }
        return substr($ua, 0, 255);
    }
}

if (!function_exists('audit_device_from_ua')) {
    function audit_device_from_ua(string $ua): string
    {
        $ua = trim($ua);
        if ($ua === '') {
            return 'Unknown Device';
        }

        $deviceType = 'Desktop';
        if (preg_match('/Mobile|Android|iPhone|iPad|Tablet/i', $ua)) {
            $deviceType = 'Mobile';
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

        return substr($browser . ' on ' . $deviceType, 0, 190);
    }
}

if (!function_exists('audit_is_private_ip')) {
    function audit_is_private_ip(string $ip): bool
    {
        if ($ip === '' || $ip === '0.0.0.0') {
            return true;
        }
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        return filter_var($ip, FILTER_VALIDATE_IP, $flags) === false;
    }
}
if (!function_exists('audit_fetch_location_remote')) {
    function audit_fetch_location_remote(string $ip): string
    {
        if ($ip === '' || audit_is_private_ip($ip)) {
            return 'Local Network';
        }

        $url = 'https://ipapi.co/' . rawurlencode($ip) . '/json/';
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 2.5,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n",
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if (!is_string($raw) || $raw === '') {
            return 'Unknown';
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return 'Unknown';
        }

        $city = trim((string)($json['city'] ?? ''));
        $country = trim((string)($json['country_name'] ?? ''));
        if ($city !== '' && $country !== '') {
            return substr($city . ', ' . $country, 0, 160);
        }
        if ($country !== '') {
            return substr($country, 0, 160);
        }
        return 'Unknown';
    }
}

if (!function_exists('audit_location_from_ip')) {
    function audit_location_from_ip(mysqli $conn, string $ip): string
    {
        if ($ip === '' || $ip === '0.0.0.0') {
            return 'Unknown';
        }
        if (audit_is_private_ip($ip)) {
            return 'Local Network';
        }

        if (!ensure_audit_center_tables($conn)) {
            return 'Unknown';
        }

        $stmt = $conn->prepare(
            "SELECT location_label
             FROM ip_location_cache
             WHERE ip_address = ?
               AND expires_at > NOW()
             LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param('s', $ip);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if ($row && trim((string)$row['location_label']) !== '') {
                return (string)$row['location_label'];
            }
        }

        $location = audit_fetch_location_remote($ip);
        $expiresAt = date('Y-m-d H:i:s', time() + (7 * 24 * 60 * 60));
        $stmt = $conn->prepare(
            "INSERT INTO ip_location_cache (ip_address, location_label, expires_at)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                location_label = VALUES(location_label),
                expires_at = VALUES(expires_at),
                cached_at = CURRENT_TIMESTAMP"
        );
        if ($stmt) {
            $stmt->bind_param('sss', $ip, $location, $expiresAt);
            $stmt->execute();
            $stmt->close();
        }
        return $location;
    }
}

if (!function_exists('audit_json_encode')) {
    function audit_json_encode($payload): ?string
    {
        if ($payload === null) {
            return null;
        }
        if (is_string($payload)) {
            return $payload;
        }
        if (!is_array($payload)) {
            return null;
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : null;
    }
}

if (!function_exists('log_audit')) {
    function log_audit($user_id, $email, $action, $module, $record_type, $record_id, $description, $snapshot_array = null): bool
    {
        $conn = audit_center_db();
        if (!$conn instanceof mysqli) {
            return false;
        }
        if (!ensure_audit_center_tables($conn)) {
            return false;
        }

        $uid = (int)$user_id;
        $email = substr(trim((string)$email), 0, 190);
        $action = strtoupper(substr(trim((string)$action), 0, 50));
        $module = substr(trim((string)$module), 0, 120);
        $recordType = substr(trim((string)$record_type), 0, 120);
        $recordId = substr(trim((string)$record_id), 0, 120);
        $description = trim((string)$description);
        $description = $description === '' ? null : $description;
        $userName = substr(trim((string)($_SESSION['user_name'] ?? '')), 0, 190);
        $userName = $userName === '' ? null : $userName;

        if ($action === '' || $module === '') {
            return false;
        }

        $ipAddress = audit_client_ip();
        $userAgent = audit_user_agent();
        $device = audit_device_from_ua($userAgent);
        $location = audit_location_from_ip($conn, $ipAddress);

        $snapshotJson = audit_json_encode($snapshot_array);
        $beforeData = ($action === 'DELETE' || $action === 'RESTORE') ? $snapshotJson : null;
        $afterData = null;

        $entityType = $recordType === '' ? null : $recordType;
        $entityIdStr = null;
        if ($recordId !== '' && preg_match('/^\d+$/', $recordId)) {
            $entityIdStr = $recordId;
        }

        $stmt = $conn->prepare(
            "INSERT INTO audit_logs (
                user_id, email, user_name, action, module, record_type, record_id,
                entity_type, entity_id, description, snapshot_json, before_data, after_data,
                ip_address, location, device, user_agent, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param(
            'issssssssssssssss',
            $uid,
            $email,
            $userName,
            $action,
            $module,
            $recordType,
            $recordId,
            $entityType,
            $entityIdStr,
            $description,
            $snapshotJson,
            $beforeData,
            $afterData,
            $ipAddress,
            $location,
            $device,
            $userAgent
        );
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            $GLOBALS['__audit_log_call_count'] = (int)($GLOBALS['__audit_log_call_count'] ?? 0) + 1;
        }
        return (bool)$ok;
    }
}

if (!function_exists('audit_log_call_count')) {
    function audit_log_call_count(): int
    {
        return (int)($GLOBALS['__audit_log_call_count'] ?? 0);
    }
}

if (!function_exists('audit_is_sensitive_key')) {
    function audit_is_sensitive_key(string $key): bool
    {
        $k = strtolower(trim($key));
        if ($k === '') {
            return false;
        }
        $needles = ['password', 'pass', 'otp', 'token', 'secret', 'csrf', 'pin', 'code', 'validator'];
        foreach ($needles as $needle) {
            if (strpos($k, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('audit_module_from_path')) {
    function audit_module_from_path(string $path): string
    {
        $basePath = trim((string)BASE_URL, '/');
        $modulePattern = $basePath !== ''
            ? '#/' . preg_quote($basePath, '#') . '/modules/([^/]+)/#'
            : '#/modules/([^/]+)/#';
        if (preg_match($modulePattern, $path, $m)) {
            $module = str_replace(['_', '-'], ' ', (string)($m[1] ?? ''));
            $module = trim($module);
            if ($module !== '') {
                return ucwords($module);
            }
        }
        if (strpos($path, url_with_base('settings.php')) !== false) {
            return 'Account Settings';
        }
        if (strpos($path, url_with_base('auth/')) !== false) {
            return 'Authentication';
        }
        return 'System';
    }
}

if (!function_exists('audit_compact_request_snapshot')) {
    function audit_compact_request_snapshot(): array
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path = (string)(parse_url($uri, PHP_URL_PATH) ?? '');

        $postKeys = [];
        foreach (array_keys($_POST ?? []) as $key) {
            $k = (string)$key;
            if (audit_is_sensitive_key($k)) {
                continue;
            }
            $postKeys[] = $k;
        }
        $getKeys = [];
        foreach (array_keys($_GET ?? []) as $key) {
            $k = (string)$key;
            if (audit_is_sensitive_key($k)) {
                continue;
            }
            $getKeys[] = $k;
        }

        $pick = static function (string $name): string {
            $val = '';
            if (isset($_POST[$name])) {
                $val = (string)$_POST[$name];
            } elseif (isset($_GET[$name])) {
                $val = (string)$_GET[$name];
            }
            $val = trim($val);
            return substr($val, 0, 120);
        };

        return [
            'method' => $method,
            'path' => substr($path, 0, 255),
            'script' => substr((string)($_SERVER['SCRIPT_NAME'] ?? ''), 0, 255),
            'action' => $pick('action'),
            'record_id' => $pick('record_id') !== '' ? $pick('record_id') : $pick('id'),
            'target_user_id' => $pick('target_user_id'),
            'module' => $pick('module'),
            'section' => $pick('section'),
            'tab' => $pick('tab'),
            'post_keys' => $postKeys,
            'get_keys' => $getKeys,
            'http_code' => http_response_code(),
        ];
    }
}

if (!function_exists('audit_auto_log_request_if_needed')) {
    function audit_auto_log_request_if_needed(int $logCountBefore = 0): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }
        if (audit_log_call_count() > $logCountBefore) {
            return;
        }

        $uid = (int)($_SESSION['user_id'] ?? 0);
        $email = (string)($_SESSION['user_email'] ?? '');
        if ($uid <= 0 || trim($email) === '') {
            return;
        }

        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path = (string)(parse_url($uri, PHP_URL_PATH) ?? '');
        $module = audit_module_from_path($path);
        $actionHint = trim((string)(($_POST['action'] ?? $_GET['action'] ?? '')));
        $recordId = trim((string)(($_POST['id'] ?? $_POST['record_id'] ?? $_GET['id'] ?? $_GET['record_id'] ?? '')));
        $description = 'Automatic request audit: ' . basename($path !== '' ? $path : (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($actionHint !== '') {
            $description .= ' (action=' . substr($actionHint, 0, 60) . ')';
        }

        $snapshot = audit_compact_request_snapshot();
        log_audit(
            $uid,
            $email,
            'REQUEST_' . $method,
            $module,
            'http_request',
            $recordId,
            $description,
            $snapshot
        );
    }
}

if (!function_exists('audit_register_auto_request_logger')) {
    function audit_register_auto_request_logger(): void
    {
        if (!empty($GLOBALS['__audit_auto_logger_registered'])) {
            return;
        }
        $GLOBALS['__audit_auto_logger_registered'] = true;
        $before = audit_log_call_count();
        register_shutdown_function(static function () use ($before): void {
            audit_auto_log_request_if_needed($before);
        });
    }
}

if (!function_exists('audit_update_last_login_fields')) {
    function audit_update_last_login_fields(mysqli $conn, int $userId, string $ipAddress, string $device): void
    {
        if ($userId <= 0) {
            return;
        }

        $createUserSettings = "
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
        if (!$conn->query($createUserSettings)) {
            return;
        }

        audit_add_column_if_missing($conn, 'user_settings', 'last_login_at', "DATETIME NULL DEFAULT NULL");
        audit_add_column_if_missing($conn, 'user_settings', 'last_login_ip', "VARCHAR(64) NULL DEFAULT NULL");
        audit_add_column_if_missing($conn, 'user_settings', 'last_login_device', "VARCHAR(190) NULL DEFAULT NULL");

        $stmt = $conn->prepare(
            "INSERT INTO user_settings (user_id, last_login_at, last_login_ip, last_login_device)
             VALUES (?, NOW(), ?, ?)
             ON DUPLICATE KEY UPDATE
                last_login_at = VALUES(last_login_at),
                last_login_ip = VALUES(last_login_ip),
                last_login_device = VALUES(last_login_device)"
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('iss', $userId, $ipAddress, $device);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('register_user_session_audit')) {
    function register_user_session_audit(int $userId, string $email, string $roleName = ''): int
    {
        if ($userId <= 0) {
            return 0;
        }
        $conn = audit_center_db();
        if (!$conn instanceof mysqli) {
            return 0;
        }
        if (!ensure_audit_center_tables($conn)) {
            return 0;
        }

        $email = substr(trim($email), 0, 190);
        $roleName = substr(trim($roleName), 0, 120);
        $sessionHash = hash('sha256', session_id());
        $ipAddress = audit_client_ip();
        $userAgent = audit_user_agent();
        $device = audit_device_from_ua($userAgent);
        $location = audit_location_from_ip($conn, $ipAddress);

        $stmt = $conn->prepare(
            "INSERT INTO user_sessions (
                user_id, email, role_name, session_id_hash, login_at, status,
                ip_address, user_agent, device, location, last_activity_at
            ) VALUES (?, ?, ?, ?, NOW(), 'active', ?, ?, ?, ?, NOW())"
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param(
            'isssssss',
            $userId,
            $email,
            $roleName,
            $sessionHash,
            $ipAddress,
            $userAgent,
            $device,
            $location
        );
        $ok = $stmt->execute();
        $sessionRowId = $ok ? (int)$stmt->insert_id : 0;
        $stmt->close();

        if ($sessionRowId > 0) {
            $_SESSION['audit_session_row_id'] = $sessionRowId;
            $_SESSION['audit_session_hash'] = $sessionHash;
            $_SESSION['audit_last_touch_ts'] = time();
            audit_update_last_login_fields($conn, $userId, $ipAddress, $device);
        }
        return $sessionRowId;
    }
}
if (!function_exists('touch_user_session_audit')) {
    function touch_user_session_audit(int $throttleSeconds = 45): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $sessionRowId = (int)($_SESSION['audit_session_row_id'] ?? 0);
        if ($userId <= 0 || $sessionRowId <= 0) {
            return;
        }

        $lastTouch = (int)($_SESSION['audit_last_touch_ts'] ?? 0);
        $now = time();
        if ($lastTouch > 0 && ($now - $lastTouch) < $throttleSeconds) {
            return;
        }

        $conn = audit_center_db();
        if (!$conn instanceof mysqli) {
            return;
        }
        if (!ensure_audit_center_tables($conn)) {
            return;
        }

        $stmt = $conn->prepare(
            "UPDATE user_sessions
             SET last_activity_at = NOW()
             WHERE id = ?
               AND user_id = ?
               AND status = 'active'"
        );
        if ($stmt) {
            $stmt->bind_param('ii', $sessionRowId, $userId);
            $stmt->execute();
            $stmt->close();
        }
        $_SESSION['audit_last_touch_ts'] = $now;
    }
}

if (!function_exists('close_user_session_audit')) {
    function close_user_session_audit(string $status = 'expired', string $reason = 'User logout'): void
    {
        $allowed = ['expired', 'forced_logout'];
        if (!in_array($status, $allowed, true)) {
            $status = 'expired';
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $email = (string)($_SESSION['user_email'] ?? '');
        $sessionRowId = (int)($_SESSION['audit_session_row_id'] ?? 0);
        $sessionHash = hash('sha256', session_id());

        $conn = audit_center_db();
        if ($conn instanceof mysqli && ensure_audit_center_tables($conn)) {
            if ($sessionRowId > 0) {
                $stmt = $conn->prepare(
                    "UPDATE user_sessions
                     SET status = ?,
                         logout_at = NOW(),
                         last_activity_at = NOW(),
                         invalidated_reason = CASE WHEN ? = '' THEN invalidated_reason ELSE ? END
                     WHERE id = ?
                       AND user_id = ?"
                );
                if ($stmt) {
                    $stmt->bind_param('sssii', $status, $reason, $reason, $sessionRowId, $userId);
                    $stmt->execute();
                    $stmt->close();
                }
            } else if ($userId > 0) {
                $stmt = $conn->prepare(
                    "UPDATE user_sessions
                     SET status = ?,
                         logout_at = NOW(),
                         last_activity_at = NOW(),
                         invalidated_reason = CASE WHEN ? = '' THEN invalidated_reason ELSE ? END
                     WHERE user_id = ?
                       AND session_id_hash = ?
                       AND status = 'active'"
                );
                if ($stmt) {
                    $stmt->bind_param('sssis', $status, $reason, $reason, $userId, $sessionHash);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        if ($userId > 0) {
            log_audit($userId, $email, 'LOGOUT', 'Authentication', 'user', (string)$userId, $reason, null);
        }

        unset($_SESSION['audit_session_row_id'], $_SESSION['audit_session_hash'], $_SESSION['audit_last_touch_ts']);
    }
}

if (!function_exists('enforce_user_session_audit')) {
    function enforce_user_session_audit(): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $conn = audit_center_db();
        if (!$conn instanceof mysqli || !ensure_audit_center_tables($conn)) {
            return;
        }

        $sessionRowId = (int)($_SESSION['audit_session_row_id'] ?? 0);
        if ($sessionRowId <= 0) {
            register_user_session_audit(
                $userId,
                (string)($_SESSION['user_email'] ?? ''),
                (string)($_SESSION['role'] ?? '')
            );
            return;
        }

        $stmt = $conn->prepare(
            "SELECT status, session_id_hash
             FROM user_sessions
             WHERE id = ?
               AND user_id = ?
             LIMIT 1"
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('ii', $sessionRowId, $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        $currentHash = hash('sha256', session_id());
        $status = (string)($row['status'] ?? '');
        $storedHash = (string)($row['session_id_hash'] ?? '');
        if (!$row || $status !== 'active' || $storedHash !== $currentHash) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            session_destroy();
            header('Location: ' . url_with_base('auth/index.php?session=expired'));
            exit;
        }
    }
}

if (!function_exists('force_logout_user_session')) {
    function force_logout_user_session(int $sessionRowId, int $adminUserId, string $adminEmail): array
    {
        if ($sessionRowId <= 0) {
            return ['ok' => false, 'message' => 'Invalid session id.'];
        }

        $conn = audit_center_db();
        if (!$conn instanceof mysqli || !ensure_audit_center_tables($conn)) {
            return ['ok' => false, 'message' => 'Audit tables are unavailable.'];
        }

        $stmt = $conn->prepare(
            "SELECT id, user_id, email, status
             FROM user_sessions
             WHERE id = ?
             LIMIT 1"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Unable to query session.'];
        }
        $stmt->bind_param('i', $sessionRowId);
        $stmt->execute();
        $res = $stmt->get_result();
        $target = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$target) {
            return ['ok' => false, 'message' => 'Session not found.'];
        }
        if ((string)$target['status'] !== 'active') {
            return ['ok' => false, 'message' => 'Session is already inactive.'];
        }

        $reason = 'Force logout by admin';
        $status = 'forced_logout';
        $stmt = $conn->prepare(
            "UPDATE user_sessions
             SET status = ?,
                 logout_at = NOW(),
                 invalidated_at = NOW(),
                 invalidated_by_user_id = ?,
                 invalidated_reason = ?,
                 last_activity_at = NOW()
             WHERE id = ?"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Unable to invalidate session.'];
        }
        $stmt->bind_param('sisi', $status, $adminUserId, $reason, $sessionRowId);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            return ['ok' => false, 'message' => 'Failed to invalidate session.'];
        }

        $targetEmail = (string)($target['email'] ?? '');
        $snapshot = [
            'target_user_id' => (int)($target['user_id'] ?? 0),
            'target_email' => $targetEmail,
            'session_id' => $sessionRowId,
            'result_status' => 'forced_logout',
        ];
        log_audit(
            $adminUserId,
            $adminEmail,
            'LOGOUT',
            'Session Audit Center',
            'user_session',
            (string)$sessionRowId,
            'Force logout session for ' . ($targetEmail !== '' ? $targetEmail : ('user #' . (int)$target['user_id'])),
            $snapshot
        );

        return ['ok' => true, 'message' => 'Session force-logged out successfully.'];
    }
}
if (!function_exists('audit_allowed_soft_delete_tables')) {
    function audit_allowed_soft_delete_tables(): array
    {
        return [
            'iec_cleanup_drive' => [
                'id_column' => 'id',
                'label_column' => 'activity_title',
                'module_name' => 'IEC Cleanup Drive',
                'record_type' => 'cleanup_drive',
            ],
        ];
    }
}

if (!function_exists('audit_allowed_archive_tables')) {
    function audit_allowed_archive_tables(): array
    {
        return [
            'truck_record' => [
                'id_column' => 'id',
                'archive_column' => 'archived',
                'archive_mode' => 'flag', // 0 = active, 1 = archived
                'module_name' => 'MRF Truck Record',
                'record_type' => 'truck_record',
            ],
            'scavenger' => [
                'id_column' => 'id',
                'archive_column' => 'archived',
                'archive_mode' => 'flag',
                'module_name' => 'MRF Sorters',
                'record_type' => 'sorter_record',
            ],
            'waste' => [
                'id_column' => 'id',
                'archive_column' => 'archived',
                'archive_mode' => 'datetime', // NULL = active, NOT NULL = archived timestamp
                'module_name' => 'MRF Other Waste',
                'record_type' => 'other_waste',
            ],
            'waste_reduction' => [
                'id_column' => 'id',
                'archive_column' => 'archived',
                'archive_mode' => 'flag',
                'module_name' => 'MRF Waste Reduction',
                'record_type' => 'waste_reduction',
            ],
            'files' => [
                'id_column' => 'id',
                'archive_column' => 'archived',
                'archive_mode' => 'flag',
                'module_name' => 'MRF Waste Reduction Files',
                'record_type' => 'waste_reduction_file',
            ],
            'violations' => [
                'id_column' => 'id',
                'archive_column' => 'archived',
                'archive_mode' => 'flag',
                'module_name' => 'Eco Police Violations',
                'record_type' => 'violation',
            ],
            'palitbasura_records' => [
                'id_column' => 'id',
                'archive_column' => 'archived',
                'archive_mode' => 'flag',
                'module_name' => 'Palit Basura',
                'record_type' => 'palitbasura_record',
            ],
        ];
    }
}

if (!function_exists('audit_record_identifier')) {
    function audit_record_identifier(array $snapshot, string $tableName, int $recordId, string $preferredKey = ''): string
    {
        if ($preferredKey !== '' && isset($snapshot[$preferredKey])) {
            $value = trim((string)$snapshot[$preferredKey]);
            if ($value !== '') {
                return $value;
            }
        }

        $candidates = [
            'activity_title', 'title', 'name', 'full_name', 'barangay',
            'plate_number', 'plate_no', 'truck_no', 'email', 'record_date', 'date',
        ];
        foreach ($candidates as $key) {
            if (!isset($snapshot[$key])) {
                continue;
            }
            $value = trim((string)$snapshot[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return $tableName . ' #' . $recordId;
    }
}

if (!function_exists('audit_is_archived_state')) {
    function audit_is_archived_state($value, string $mode): bool
    {
        if ($mode === 'datetime') {
            return $value !== null && trim((string)$value) !== '' && (string)$value !== '0000-00-00 00:00:00';
        }
        return (int)$value === 1;
    }
}

if (!function_exists('audit_ensure_soft_delete_columns')) {
    function audit_ensure_soft_delete_columns(mysqli $conn, string $tableName): bool
    {
        $allowed = audit_allowed_soft_delete_tables();
        if (!isset($allowed[$tableName])) {
            return false;
        }
        if (!audit_add_column_if_missing($conn, $tableName, 'is_deleted', "TINYINT(1) NOT NULL DEFAULT 0")) {
            return false;
        }
        if (!audit_add_column_if_missing($conn, $tableName, 'deleted_at', "DATETIME NULL DEFAULT NULL")) {
            return false;
        }
        if (!audit_add_column_if_missing($conn, $tableName, 'deleted_by', "INT NULL DEFAULT NULL")) {
            return false;
        }
        return true;
    }
}

if (!function_exists('soft_delete_record_with_audit')) {
    function soft_delete_record_with_audit(
        mysqli $conn,
        string $moduleName,
        string $tableName,
        int $recordId,
        int $deletedByUserId,
        string $deletedByEmail,
        string $reason = '',
        string $idColumn = 'id',
        string $labelColumn = 'name'
    ): array {
        if ($recordId <= 0 || $deletedByUserId <= 0) {
            return ['ok' => false, 'message' => 'Invalid delete parameters.'];
        }

        $allowed = audit_allowed_soft_delete_tables();
        if (!isset($allowed[$tableName])) {
            return ['ok' => false, 'message' => 'Delete audit is not configured for this table.'];
        }
        $meta = $allowed[$tableName];
        $idColumn = (string)$meta['id_column'];
        $labelColumn = (string)$meta['label_column'];
        $recordType = (string)$meta['record_type'];
        $moduleName = trim($moduleName) !== '' ? $moduleName : (string)$meta['module_name'];

        if (!ensure_audit_center_tables($conn) || !audit_ensure_soft_delete_columns($conn, $tableName)) {
            return ['ok' => false, 'message' => 'Soft-delete infrastructure is unavailable.'];
        }

        $stmt = $conn->prepare(
            "SELECT *
             FROM `{$tableName}`
             WHERE `{$idColumn}` = ?
             LIMIT 1"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Unable to fetch record snapshot.'];
        }
        $stmt->bind_param('i', $recordId);
        $stmt->execute();
        $res = $stmt->get_result();
        $snapshot = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$snapshot) {
            return ['ok' => false, 'message' => 'Record not found.'];
        }
        if ((int)($snapshot['is_deleted'] ?? 0) === 1) {
            return ['ok' => false, 'message' => 'Record is already deleted.'];
        }

        $stmt = $conn->prepare(
            "UPDATE `{$tableName}`
             SET is_deleted = 1,
                 deleted_at = NOW(),
                 deleted_by = ?
             WHERE `{$idColumn}` = ?"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Unable to apply soft delete.'];
        }
        $stmt->bind_param('ii', $deletedByUserId, $recordId);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            return ['ok' => false, 'message' => 'Failed to soft delete record.'];
        }

        $itemIdentifier = audit_record_identifier($snapshot, $tableName, $recordId, $labelColumn);

        $snapshotJson = audit_json_encode($snapshot) ?? '{}';
        $deletedByEmail = substr(trim($deletedByEmail), 0, 190);
        $reason = trim($reason);
        $reason = $reason === '' ? null : substr($reason, 0, 255);
        $status = 'deleted';

        $stmt = $conn->prepare(
            "INSERT INTO deleted_items (
                module_name, source_table, record_type, record_id, item_identifier,
                deleted_by_user_id, deleted_by_email, deleted_reason, record_snapshot, status, deleted_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Soft delete applied but deleted review entry failed.'];
        }
        $recordIdStr = (string)$recordId;
        $stmt->bind_param(
            'sssssissss',
            $moduleName,
            $tableName,
            $recordType,
            $recordIdStr,
            $itemIdentifier,
            $deletedByUserId,
            $deletedByEmail,
            $reason,
            $snapshotJson,
            $status
        );
        $okInsert = $stmt->execute();
        $deletedItemId = $okInsert ? (int)$stmt->insert_id : 0;
        $stmt->close();

        log_audit(
            $deletedByUserId,
            $deletedByEmail,
            'DELETE',
            $moduleName,
            $recordType,
            (string)$recordId,
            'Soft deleted record: ' . $itemIdentifier,
            $snapshot
        );

        if (!$okInsert) {
            return ['ok' => false, 'message' => 'Soft delete logged, but deleted review entry failed.'];
        }

        return ['ok' => true, 'message' => 'Record deleted and captured in review bin.', 'deleted_item_id' => $deletedItemId];
    }
}

if (!function_exists('archive_record_with_audit')) {
    function archive_record_with_audit(
        mysqli $conn,
        string $moduleName,
        string $tableName,
        int $recordId,
        int $actorUserId,
        string $actorEmail,
        string $reason = ''
    ): array {
        if ($recordId <= 0 || $actorUserId <= 0) {
            return ['ok' => false, 'message' => 'Invalid archive parameters.'];
        }
        if (!ensure_audit_center_tables($conn)) {
            return ['ok' => false, 'message' => 'Audit infrastructure unavailable.'];
        }

        $allowed = audit_allowed_archive_tables();
        if (!isset($allowed[$tableName])) {
            return ['ok' => false, 'message' => 'Archive audit is not configured for this table.'];
        }

        $meta = $allowed[$tableName];
        $idColumn = (string)$meta['id_column'];
        $archiveColumn = (string)$meta['archive_column'];
        $archiveMode = (string)$meta['archive_mode'];
        $recordType = (string)$meta['record_type'];
        $moduleName = trim($moduleName) !== '' ? $moduleName : (string)$meta['module_name'];

        $stmt = $conn->prepare(
            "SELECT *
             FROM `{$tableName}`
             WHERE `{$idColumn}` = ?
             LIMIT 1"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Unable to fetch archive snapshot.'];
        }
        $stmt->bind_param('i', $recordId);
        $stmt->execute();
        $res = $stmt->get_result();
        $snapshot = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$snapshot) {
            return ['ok' => false, 'message' => 'Record not found.'];
        }
        if (audit_is_archived_state($snapshot[$archiveColumn] ?? null, $archiveMode)) {
            return ['ok' => false, 'message' => 'Record is already archived.'];
        }

        if ($archiveMode === 'datetime') {
            $stmt = $conn->prepare(
                "UPDATE `{$tableName}`
                 SET `{$archiveColumn}` = NOW()
                 WHERE `{$idColumn}` = ?"
            );
        } else {
            $stmt = $conn->prepare(
                "UPDATE `{$tableName}`
                 SET `{$archiveColumn}` = 1
                 WHERE `{$idColumn}` = ?"
            );
        }
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Unable to archive source record.'];
        }
        $stmt->bind_param('i', $recordId);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['ok' => false, 'message' => 'Failed to archive record.'];
        }

        $itemIdentifier = audit_record_identifier($snapshot, $tableName, $recordId);
        $snapshotJson = audit_json_encode($snapshot) ?? '{}';
        $actorEmail = substr(trim($actorEmail), 0, 190);
        $reason = trim($reason);
        $reason = $reason === '' ? null : substr($reason, 0, 255);

        $status = 'deleted';
        $stmt = $conn->prepare(
            "INSERT INTO deleted_items (
                module_name, source_table, record_type, record_id, item_identifier,
                deleted_by_user_id, deleted_by_email, deleted_reason, record_snapshot, status, deleted_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        if ($stmt) {
            $recordIdStr = (string)$recordId;
            $stmt->bind_param(
                'sssssissss',
                $moduleName,
                $tableName,
                $recordType,
                $recordIdStr,
                $itemIdentifier,
                $actorUserId,
                $actorEmail,
                $reason,
                $snapshotJson,
                $status
            );
            $stmt->execute();
            $stmt->close();
        }

        log_audit(
            $actorUserId,
            $actorEmail,
            'ARCHIVE',
            $moduleName,
            $recordType,
            (string)$recordId,
            'Archived record: ' . $itemIdentifier,
            $snapshot
        );

        return ['ok' => true, 'message' => 'Record archived successfully.'];
    }
}

if (!function_exists('restore_archived_record_with_audit')) {
    function restore_archived_record_with_audit(
        mysqli $conn,
        string $moduleName,
        string $tableName,
        int $recordId,
        int $actorUserId,
        string $actorEmail
    ): array {
        if ($recordId <= 0 || $actorUserId <= 0) {
            return ['ok' => false, 'message' => 'Invalid restore parameters.'];
        }
        if (!ensure_audit_center_tables($conn)) {
            return ['ok' => false, 'message' => 'Audit infrastructure unavailable.'];
        }

        $allowed = audit_allowed_archive_tables();
        if (!isset($allowed[$tableName])) {
            return ['ok' => false, 'message' => 'Archive restore is not configured for this table.'];
        }

        $meta = $allowed[$tableName];
        $idColumn = (string)$meta['id_column'];
        $archiveColumn = (string)$meta['archive_column'];
        $archiveMode = (string)$meta['archive_mode'];
        $recordType = (string)$meta['record_type'];
        $moduleName = trim($moduleName) !== '' ? $moduleName : (string)$meta['module_name'];

        $stmt = $conn->prepare(
            "SELECT *
             FROM `{$tableName}`
             WHERE `{$idColumn}` = ?
             LIMIT 1"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Unable to fetch archived record.'];
        }
        $stmt->bind_param('i', $recordId);
        $stmt->execute();
        $res = $stmt->get_result();
        $snapshot = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$snapshot) {
            return ['ok' => false, 'message' => 'Record not found.'];
        }
        if (!audit_is_archived_state($snapshot[$archiveColumn] ?? null, $archiveMode)) {
            return ['ok' => false, 'message' => 'Record is already active.'];
        }

        if ($archiveMode === 'datetime') {
            $stmt = $conn->prepare(
                "UPDATE `{$tableName}`
                 SET `{$archiveColumn}` = NULL
                 WHERE `{$idColumn}` = ?"
            );
        } else {
            $stmt = $conn->prepare(
                "UPDATE `{$tableName}`
                 SET `{$archiveColumn}` = 0
                 WHERE `{$idColumn}` = ?"
            );
        }
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Unable to restore archived record.'];
        }
        $stmt->bind_param('i', $recordId);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['ok' => false, 'message' => 'Failed to restore archived record.'];
        }

        $itemIdentifier = audit_record_identifier($snapshot, $tableName, $recordId);
        $actorEmail = substr(trim($actorEmail), 0, 190);

        $recordIdStr = (string)$recordId;
        $pickStmt = $conn->prepare(
            "SELECT id
             FROM deleted_items
             WHERE source_table = ?
               AND record_id = ?
               AND status = 'deleted'
             ORDER BY id DESC
             LIMIT 1"
        );
        if ($pickStmt) {
            $pickStmt->bind_param('ss', $tableName, $recordIdStr);
            $pickStmt->execute();
            $pickRes = $pickStmt->get_result();
            $pickRow = $pickRes ? $pickRes->fetch_assoc() : null;
            $pickStmt->close();
            if ($pickRow) {
                $restoreStatus = 'restored';
                $updateStmt = $conn->prepare(
                    "UPDATE deleted_items
                     SET status = ?,
                         restored_at = NOW(),
                         restored_by_user_id = ?
                     WHERE id = ?"
                );
                if ($updateStmt) {
                    $deletedItemId = (int)$pickRow['id'];
                    $updateStmt->bind_param('sii', $restoreStatus, $actorUserId, $deletedItemId);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
            }
        }

        log_audit(
            $actorUserId,
            $actorEmail,
            'RESTORE',
            $moduleName,
            $recordType,
            (string)$recordId,
            'Restored archived record: ' . $itemIdentifier,
            $snapshot
        );

        return ['ok' => true, 'message' => 'Archived record restored successfully.'];
    }
}

if (!function_exists('restore_deleted_item_with_audit')) {
    function restore_deleted_item_with_audit(mysqli $conn, int $deletedItemId, int $adminUserId, string $adminEmail): array
    {
        if ($deletedItemId <= 0 || $adminUserId <= 0) {
            return ['ok' => false, 'message' => 'Invalid restore parameters.'];
        }
        if (!ensure_audit_center_tables($conn)) {
            return ['ok' => false, 'message' => 'Audit infrastructure unavailable.'];
        }

        $stmt = $conn->prepare(
            "SELECT *
             FROM deleted_items
             WHERE id = ?
             LIMIT 1"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Unable to fetch deleted item.'];
        }
        $stmt->bind_param('i', $deletedItemId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            return ['ok' => false, 'message' => 'Deleted item not found.'];
        }
        if ((string)$row['status'] !== 'deleted') {
            return ['ok' => false, 'message' => 'Item is not in deleted state.'];
        }

        $tableName = (string)$row['source_table'];
        $recordId = (int)$row['record_id'];
        $softAllowed = audit_allowed_soft_delete_tables();
        $archiveAllowed = audit_allowed_archive_tables();

        if (isset($softAllowed[$tableName])) {
            $meta = $softAllowed[$tableName];
            $idColumn = (string)$meta['id_column'];
            if (!audit_ensure_soft_delete_columns($conn, $tableName)) {
                return ['ok' => false, 'message' => 'Soft delete columns are unavailable.'];
            }

            $stmt = $conn->prepare(
                "UPDATE `{$tableName}`
                 SET is_deleted = 0,
                     deleted_at = NULL,
                     deleted_by = NULL
                 WHERE `{$idColumn}` = ?"
            );
            if (!$stmt) {
                return ['ok' => false, 'message' => 'Unable to restore source record.'];
            }
            $stmt->bind_param('i', $recordId);
            $ok = $stmt->execute();
            $stmt->close();
            if (!$ok) {
                return ['ok' => false, 'message' => 'Failed to restore source record.'];
            }
        } elseif (isset($archiveAllowed[$tableName])) {
            $meta = $archiveAllowed[$tableName];
            $idColumn = (string)$meta['id_column'];
            $archiveColumn = (string)$meta['archive_column'];
            $archiveMode = (string)$meta['archive_mode'];

            if ($archiveMode === 'datetime') {
                $stmt = $conn->prepare(
                    "UPDATE `{$tableName}`
                     SET `{$archiveColumn}` = NULL
                     WHERE `{$idColumn}` = ?"
                );
            } else {
                $stmt = $conn->prepare(
                    "UPDATE `{$tableName}`
                     SET `{$archiveColumn}` = 0
                     WHERE `{$idColumn}` = ?"
                );
            }
            if (!$stmt) {
                return ['ok' => false, 'message' => 'Unable to restore archived source record.'];
            }
            $stmt->bind_param('i', $recordId);
            $ok = $stmt->execute();
            $stmt->close();
            if (!$ok) {
                return ['ok' => false, 'message' => 'Failed to restore archived source record.'];
            }
        } else {
            return ['ok' => false, 'message' => 'Restore is not configured for source table.'];
        }

        $status = 'restored';
        $stmt = $conn->prepare(
            "UPDATE deleted_items
             SET status = ?,
                 restored_at = NOW(),
                 restored_by_user_id = ?
             WHERE id = ?"
        );
        if ($stmt) {
            $stmt->bind_param('sii', $status, $adminUserId, $deletedItemId);
            $stmt->execute();
            $stmt->close();
        }

        $snapshot = json_decode((string)($row['record_snapshot'] ?? ''), true);
        log_audit(
            $adminUserId,
            $adminEmail,
            'RESTORE',
            (string)$row['module_name'],
            (string)$row['record_type'],
            (string)$row['record_id'],
            'Restored deleted item: ' . (string)$row['item_identifier'],
            is_array($snapshot) ? $snapshot : null
        );

        return ['ok' => true, 'message' => 'Record restored successfully.'];
    }
}

if (!function_exists('purge_deleted_item_with_audit')) {
    function purge_deleted_item_with_audit(mysqli $conn, int $deletedItemId, int $adminUserId, string $adminEmail): array
    {
        if ($deletedItemId <= 0 || $adminUserId <= 0) {
            return ['ok' => false, 'message' => 'Invalid purge parameters.'];
        }
        if (!ensure_audit_center_tables($conn)) {
            return ['ok' => false, 'message' => 'Audit infrastructure unavailable.'];
        }

        $stmt = $conn->prepare(
            "SELECT *
             FROM deleted_items
             WHERE id = ?
             LIMIT 1"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Unable to fetch deleted item.'];
        }
        $stmt->bind_param('i', $deletedItemId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            return ['ok' => false, 'message' => 'Deleted item not found.'];
        }
        if ((string)$row['status'] !== 'deleted') {
            return ['ok' => false, 'message' => 'Only deleted records can be permanently deleted.'];
        }

        $tableName = (string)$row['source_table'];
        $recordId = (int)$row['record_id'];
        $softAllowed = audit_allowed_soft_delete_tables();
        $archiveAllowed = audit_allowed_archive_tables();
        if (isset($softAllowed[$tableName])) {
            $meta = $softAllowed[$tableName];
        } elseif (isset($archiveAllowed[$tableName])) {
            $meta = $archiveAllowed[$tableName];
        } else {
            return ['ok' => false, 'message' => 'Purge is not configured for source table.'];
        }
        $idColumn = (string)$meta['id_column'];

        $stmt = $conn->prepare(
            "DELETE FROM `{$tableName}`
             WHERE `{$idColumn}` = ?"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Unable to permanently delete source record.'];
        }
        $stmt->bind_param('i', $recordId);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['ok' => false, 'message' => 'Permanent delete failed.'];
        }

        $status = 'purged';
        $stmt = $conn->prepare(
            "UPDATE deleted_items
             SET status = ?,
                 permanently_deleted_at = NOW(),
                 permanently_deleted_by_user_id = ?
             WHERE id = ?"
        );
        if ($stmt) {
            $stmt->bind_param('sii', $status, $adminUserId, $deletedItemId);
            $stmt->execute();
            $stmt->close();
        }

        $snapshot = json_decode((string)($row['record_snapshot'] ?? ''), true);
        log_audit(
            $adminUserId,
            $adminEmail,
            'DELETE',
            (string)$row['module_name'],
            (string)$row['record_type'],
            (string)$row['record_id'],
            'Permanently deleted item: ' . (string)$row['item_identifier'],
            is_array($snapshot) ? $snapshot : null
        );

        return ['ok' => true, 'message' => 'Record permanently deleted.'];
    }
}
