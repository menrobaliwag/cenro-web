<?php
declare(strict_types=1);

require_once __DIR__ . '/security_bootstrap.php';
secure_session_start();

if (!function_exists('auth_flow_cookie_name')) {
    function auth_flow_cookie_name(): string
    {
        return 'city_enro_trusted_device';
    }
}

if (!function_exists('auth_flow_pending_timeout_seconds')) {
    function auth_flow_pending_timeout_seconds(): int
    {
        $raw = getenv('AUTH_PENDING_TIMEOUT_SECONDS');
        $val = is_string($raw) ? (int)$raw : 0;
        if ($val < 300 || $val > 3600) {
            return 1200;
        }
        return $val;
    }
}

if (!function_exists('auth_flow_session_idle_timeout_seconds')) {
    function auth_flow_session_idle_timeout_seconds(): int
    {
        $raw = getenv('AUTH_IDLE_TIMEOUT_SECONDS');
        $val = is_string($raw) ? (int)$raw : 0;
        if ($val < 300 || $val > 43200) {
            return 5400;
        }
        return $val;
    }
}

if (!function_exists('auth_flow_set_state')) {
    function auth_flow_set_state(bool $passwordVerified, bool $emailVerified, bool $twofaVerified): void
    {
        $_SESSION['password_verified'] = $passwordVerified;
        $_SESSION['email_verified'] = $emailVerified;
        $_SESSION['twofa_verified'] = $twofaVerified;
        $_SESSION['fully_authenticated'] = ($passwordVerified && $emailVerified && $twofaVerified);
    }
}

if (!function_exists('auth_flow_reset_state')) {
    function auth_flow_reset_state(): void
    {
        auth_flow_set_state(false, false, false);
    }
}

if (!function_exists('auth_flow_mark_password_verified')) {
    function auth_flow_mark_password_verified(bool $value = true): void
    {
        auth_flow_set_state(
            $value,
            (bool)($_SESSION['email_verified'] ?? false),
            (bool)($_SESSION['twofa_verified'] ?? false)
        );
    }
}

if (!function_exists('auth_flow_mark_email_verified')) {
    function auth_flow_mark_email_verified(bool $value = true): void
    {
        auth_flow_set_state(
            (bool)($_SESSION['password_verified'] ?? false),
            $value,
            (bool)($_SESSION['twofa_verified'] ?? false)
        );
    }
}

if (!function_exists('auth_flow_mark_twofa_verified')) {
    function auth_flow_mark_twofa_verified(bool $value = true): void
    {
        auth_flow_set_state(
            (bool)($_SESSION['password_verified'] ?? false),
            (bool)($_SESSION['email_verified'] ?? false),
            $value
        );
    }
}

if (!function_exists('auth_flow_is_fully_authenticated')) {
    function auth_flow_is_fully_authenticated(): bool
    {
        return (bool)($_SESSION['fully_authenticated'] ?? false) === true
            && (int)($_SESSION['user_id'] ?? 0) > 0;
    }
}

if (!function_exists('auth_flow_touch_activity')) {
    function auth_flow_touch_activity(): void
    {
        $_SESSION['auth_last_activity_at'] = time();
    }
}

if (!function_exists('auth_flow_session_timed_out')) {
    function auth_flow_session_timed_out(): bool
    {
        $last = (int)($_SESSION['auth_last_activity_at'] ?? 0);
        if ($last <= 0) {
            return false;
        }
        return (time() - $last) > auth_flow_session_idle_timeout_seconds();
    }
}

if (!function_exists('auth_flow_clear_authenticated_identity')) {
    function auth_flow_clear_authenticated_identity(): void
    {
        unset(
            $_SESSION['user_id'],
            $_SESSION['user_email'],
            $_SESSION['user_name'],
            $_SESSION['barangay'],
            $_SESSION['role_id'],
            $_SESSION['role'],
            $_SESSION['role_name'],
            $_SESSION['user_role'],
            $_SESSION['permissions'],
            $_SESSION['mfa_method'],
            $_SESSION['auth_last_activity_at']
        );
        auth_flow_reset_state();
    }
}

if (!function_exists('auth_flow_is_pending_auth_valid')) {
    function auth_flow_is_pending_auth_valid(): bool
    {
        $uid = (int)($_SESSION['pending_user_id'] ?? 0);
        $email = trim((string)($_SESSION['pending_email'] ?? ''));
        $started = (int)($_SESSION['pending_auth_ts'] ?? 0);
        if ($uid <= 0 || $email === '' || $started <= 0) {
            return false;
        }
        return (time() - $started) <= auth_flow_pending_timeout_seconds();
    }
}

if (!function_exists('auth_flow_clear_trusted_device_cookie')) {
    function auth_flow_clear_trusted_device_cookie(): void
    {
        $opts = [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        setcookie(auth_flow_cookie_name(), '', $opts);
    }
}

if (!function_exists('auth_flow_trusted_device_fingerprint')) {
    function auth_flow_trusted_device_fingerprint(): string
    {
        $ua = strtolower(trim((string)($_SERVER['HTTP_USER_AGENT'] ?? '')));
        $lang = strtolower(trim((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')));
        $platform = strtolower(trim((string)($_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? '')));
        $chua = strtolower(trim((string)($_SERVER['HTTP_SEC_CH_UA'] ?? '')));
        $ip = security_client_ip();
        $ipPrefix = '';

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                $ipPrefix = $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24';
            }
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            $parts = array_slice($parts, 0, 4);
            $ipPrefix = implode(':', $parts) . '::/64';
        }

        $payload = implode('|', [$ua, $lang, $platform, $chua, $ipPrefix]);
        return hash('sha256', $payload);
    }
}

if (!function_exists('auth_flow_conn_cache_key')) {
    function auth_flow_conn_cache_key(mysqli $conn): string
    {
        if (function_exists('spl_object_id')) {
            return 'conn:' . (string)spl_object_id($conn);
        }
        return 'thread:' . (string)((int)($conn->thread_id ?? 0));
    }
}

if (!function_exists('auth_flow_has_column')) {
    function auth_flow_has_column(mysqli $conn, string $table, string $column, bool $refresh = false): bool
    {
        static $cache = [];
        $tableKey = strtolower(trim($table));
        $columnKey = strtolower(trim($column));
        if ($tableKey === '' || $columnKey === '') {
            return false;
        }

        $cacheKey = auth_flow_conn_cache_key($conn) . ':' . $tableKey . ':' . $columnKey;
        if ($refresh || !array_key_exists($cacheKey, $cache)) {
            $safeTable = $conn->real_escape_string($tableKey);
            $safeColumn = $conn->real_escape_string($columnKey);
            $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
            $cache[$cacheKey] = ($res instanceof mysqli_result) && $res->num_rows > 0;
        }

        return (bool)$cache[$cacheKey];
    }
}

if (!function_exists('auth_flow_ensure_security_tables')) {
    function auth_flow_ensure_security_tables(mysqli $conn): bool
    {
        static $ready = [];
        $connKey = auth_flow_conn_cache_key($conn);
        if (($ready[$connKey] ?? false) === true) {
            return true;
        }

        $userSettingsSql = "
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
        $otpSql = "
            CREATE TABLE IF NOT EXISTS auth_email_otp (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT NOT NULL,
                purpose VARCHAR(40) NOT NULL,
                otp_hash VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                attempts_used TINYINT UNSIGNED NOT NULL DEFAULT 0,
                max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 5,
                consumed_at DATETIME NULL DEFAULT NULL,
                sent_ip VARCHAR(64) NOT NULL DEFAULT '',
                sent_user_agent VARCHAR(255) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_auth_email_otp_user (user_id, purpose, expires_at),
                KEY idx_auth_email_otp_active (user_id, consumed_at, expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        $trustedSql = "
            CREATE TABLE IF NOT EXISTS auth_trusted_devices (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT NOT NULL,
                selector CHAR(24) NOT NULL,
                validator_hash CHAR(64) NOT NULL,
                fingerprint_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_ip VARCHAR(64) NOT NULL DEFAULT '',
                created_user_agent VARCHAR(255) NOT NULL DEFAULT '',
                last_ip VARCHAR(64) NOT NULL DEFAULT '',
                last_user_agent VARCHAR(255) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_used_at DATETIME NULL DEFAULT NULL,
                revoked_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY ux_auth_trusted_selector (selector),
                KEY idx_auth_trusted_user (user_id, expires_at),
                KEY idx_auth_trusted_active (user_id, revoked_at, expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        $backupSql = "
            CREATE TABLE IF NOT EXISTS auth_backup_codes (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT NOT NULL,
                code_hash VARCHAR(255) NOT NULL,
                used_at DATETIME NULL DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_auth_backup_user (user_id, used_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        if (!$conn->query($userSettingsSql)) {
            $ready[$connKey] = false;
            return false;
        }
        if (!$conn->query($otpSql)) {
            $ready[$connKey] = false;
            return false;
        }
        if (!$conn->query($trustedSql)) {
            $ready[$connKey] = false;
            return false;
        }
        if (!$conn->query($backupSql)) {
            $ready[$connKey] = false;
            return false;
        }

        if (!auth_flow_has_column($conn, 'user_settings', 'otp_enabled')) {
            if (!$conn->query("ALTER TABLE user_settings ADD COLUMN otp_enabled TINYINT(1) NOT NULL DEFAULT 0")) {
                if (!auth_flow_has_column($conn, 'user_settings', 'otp_enabled', true)) {
                    $ready[$connKey] = false;
                    return false;
                }
            }
            auth_flow_has_column($conn, 'user_settings', 'otp_enabled', true);
        }

        $ready[$connKey] = true;
        return true;
    }
}

if (!function_exists('auth_flow_otp_purpose_allowed')) {
    function auth_flow_otp_purpose_allowed(string $purpose): bool
    {
        return in_array(
            $purpose,
            ['setup_2fa_first_time', 'suspicious_login', 'account_recovery'],
            true
        );
    }
}

if (!function_exists('auth_flow_random_token')) {
    function auth_flow_random_token(int $bytes): string
    {
        try {
            return bin2hex(random_bytes($bytes));
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('auth_flow_generate_otp_code')) {
    function auth_flow_generate_otp_code(): string
    {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('auth_flow_issue_email_otp')) {
    function auth_flow_issue_email_otp(mysqli $conn, int $userId, string $purpose): array
    {
        if ($userId <= 0 || !auth_flow_otp_purpose_allowed($purpose)) {
            return ['ok' => false, 'error' => 'Invalid OTP request.'];
        }
        if (!auth_flow_ensure_security_tables($conn)) {
            return ['ok' => false, 'error' => 'Security table setup failed.'];
        }

        $code = auth_flow_generate_otp_code();
        $hash = password_hash($code, PASSWORD_DEFAULT);
        if (!is_string($hash) || $hash === '') {
            return ['ok' => false, 'error' => 'Unable to secure OTP.'];
        }

        $expiresTs = time() + 300;
        $expiresAt = date('Y-m-d H:i:s', $expiresTs);
        $ip = security_client_ip();
        $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if (strlen($ua) > 255) {
            $ua = substr($ua, 0, 255);
        }
        $maxAttempts = 5;

        $stmtConsume = $conn->prepare(
            "UPDATE auth_email_otp
             SET consumed_at = NOW()
             WHERE user_id = ?
               AND purpose = ?
               AND consumed_at IS NULL"
        );
        if ($stmtConsume) {
            $stmtConsume->bind_param('is', $userId, $purpose);
            $stmtConsume->execute();
            $stmtConsume->close();
        }

        $stmt = $conn->prepare(
            "INSERT INTO auth_email_otp
                (user_id, purpose, otp_hash, expires_at, attempts_used, max_attempts, sent_ip, sent_user_agent)
             VALUES (?, ?, ?, ?, 0, ?, ?, ?)"
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Unable to create OTP challenge.'];
        }

        $stmt->bind_param('isssiss', $userId, $purpose, $hash, $expiresAt, $maxAttempts, $ip, $ua);
        $ok = $stmt->execute();
        $challengeId = $ok ? (int)$stmt->insert_id : 0;
        $stmt->close();

        if (!$ok || $challengeId <= 0) {
            return ['ok' => false, 'error' => 'Unable to create OTP challenge.'];
        }

        return [
            'ok' => true,
            'challenge_id' => $challengeId,
            'otp' => $code,
            'expires_at' => $expiresAt,
            'expires_ts' => $expiresTs,
        ];
    }
}

if (!function_exists('auth_flow_consume_otp_challenge')) {
    function auth_flow_consume_otp_challenge(mysqli $conn, int $challengeId): void
    {
        if ($challengeId <= 0) {
            return;
        }
        $stmt = $conn->prepare(
            "UPDATE auth_email_otp
             SET consumed_at = NOW()
             WHERE id = ? AND consumed_at IS NULL"
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('i', $challengeId);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('auth_flow_verify_email_otp')) {
    function auth_flow_verify_email_otp(mysqli $conn, int $userId, string $purpose, string $otp): array
    {
        if ($userId <= 0 || !auth_flow_otp_purpose_allowed($purpose)) {
            return ['ok' => false, 'error' => 'Invalid OTP verification request.', 'remaining' => 0];
        }
        if (!preg_match('/^\d{6}$/', $otp)) {
            return ['ok' => false, 'error' => 'Please enter a valid 6-digit OTP.', 'remaining' => 5];
        }
        if (!auth_flow_ensure_security_tables($conn)) {
            return ['ok' => false, 'error' => 'Security table setup failed.', 'remaining' => 0];
        }

        $stmt = $conn->prepare(
            "SELECT id, otp_hash, expires_at, attempts_used, max_attempts
             FROM auth_email_otp
             WHERE user_id = ?
               AND purpose = ?
               AND consumed_at IS NULL
             ORDER BY id DESC
             LIMIT 1"
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Unable to verify OTP right now.', 'remaining' => 0];
        }

        $stmt->bind_param('is', $userId, $purpose);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            return ['ok' => false, 'error' => 'OTP not found. Please request a new code.', 'remaining' => 0];
        }

        $challengeId = (int)($row['id'] ?? 0);
        $expiresAt = strtotime((string)($row['expires_at'] ?? ''));
        $attemptsUsed = (int)($row['attempts_used'] ?? 0);
        $maxAttempts = (int)($row['max_attempts'] ?? 5);
        if ($maxAttempts <= 0) {
            $maxAttempts = 5;
        }

        if ($expiresAt !== false && $expiresAt < time()) {
            auth_flow_consume_otp_challenge($conn, $challengeId);
            return ['ok' => false, 'error' => 'OTP expired. Please request a new code.', 'remaining' => 0];
        }

        if ($attemptsUsed >= $maxAttempts) {
            auth_flow_consume_otp_challenge($conn, $challengeId);
            return ['ok' => false, 'error' => 'Maximum OTP attempts reached. Please log in again.', 'remaining' => 0];
        }

        $hash = (string)($row['otp_hash'] ?? '');
        $valid = $hash !== '' && password_verify($otp, $hash);
        if ($valid) {
            auth_flow_consume_otp_challenge($conn, $challengeId);
            return ['ok' => true, 'error' => '', 'remaining' => $maxAttempts - $attemptsUsed];
        }

        $stmtFail = $conn->prepare(
            "UPDATE auth_email_otp
             SET attempts_used = attempts_used + 1,
                 consumed_at = CASE WHEN (attempts_used + 1) >= max_attempts THEN NOW() ELSE consumed_at END
             WHERE id = ?"
        );
        if ($stmtFail) {
            $stmtFail->bind_param('i', $challengeId);
            $stmtFail->execute();
            $stmtFail->close();
        }

        $remaining = max(0, $maxAttempts - ($attemptsUsed + 1));
        $msg = $remaining > 0
            ? 'Invalid OTP. Please try again.'
            : 'Maximum OTP attempts reached. Please log in again.';

        return ['ok' => false, 'error' => $msg, 'remaining' => $remaining];
    }
}

if (!function_exists('auth_flow_otp_purpose_label')) {
    function auth_flow_otp_purpose_label(string $purpose): string
    {
        if ($purpose === 'setup_2fa_first_time') {
            return 'Complete first-time Authenticator setup';
        }
        if ($purpose === 'suspicious_login') {
            return 'Verify suspicious/new device login';
        }
        return 'Account recovery verification';
    }
}

if (!function_exists('auth_flow_send_email_otp_code')) {
    function auth_flow_send_email_otp_code(string $email, string $otpCode, string $purpose): array
    {
        $email = data_normalize_email($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid recipient email.'];
        }
        if (!preg_match('/^\d{6}$/', $otpCode)) {
            return ['ok' => false, 'error' => 'Invalid OTP code.'];
        }

        require_once dirname(__DIR__) . '/config/secrets.php';
        require_once dirname(__DIR__) . '/sendphpmailer/PHPMailer.php';
        require_once dirname(__DIR__) . '/sendphpmailer/SMTP.php';
        require_once dirname(__DIR__) . '/sendphpmailer/Exception.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = SMTP_AUTH;
            if (SMTP_HOST === '') {
                throw new \PHPMailer\PHPMailer\Exception('SMTP host is not configured.');
            }
            if (SMTP_AUTH && (SMTP_USER === '' || SMTP_PASS === '')) {
                throw new \PHPMailer\PHPMailer\Exception('SMTP credentials are not configured.');
            }
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            if (in_array(SMTP_SECURE, ['tls', 'starttls'], true)) {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } elseif (in_array(SMTP_SECURE, ['ssl', 'smtps'], true)) {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }
            $mail->Port = SMTP_PORT;
            $mail->Timeout = 10;

            $mail->setFrom(SMTP_SENDER, SMTP_SENDER_NAME);
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Your Security Verification Code';

            $label = htmlspecialchars(auth_flow_otp_purpose_label($purpose), ENT_QUOTES, 'UTF-8');
            $safeCode = htmlspecialchars($otpCode, ENT_QUOTES, 'UTF-8');
            $mail->Body = "
                <div style='font-family:Arial,sans-serif;max-width:560px;margin:auto'>
                    <h3 style='margin-bottom:4px'>Security Verification Code</h3>
                    <p style='margin-top:0;color:#444'>{$label}</p>
                    <p>Your 6-digit code is:</p>
                    <p style='font-size:28px;font-weight:700;letter-spacing:2px'>{$safeCode}</p>
                    <p style='color:#444'>This code expires in <strong>5 minutes</strong> and can only be used once.</p>
                    <p style='color:#777'>If you did not request this, please secure your account immediately.</p>
                </div>
            ";
            $mail->send();
            return ['ok' => true, 'error' => ''];
        } catch (Throwable $e) {
            error_log('OTP email send failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Unable to send OTP right now.'];
        }
    }
}

if (!function_exists('auth_flow_issue_and_send_email_otp')) {
    function auth_flow_issue_and_send_email_otp(mysqli $conn, int $userId, string $email, string $purpose): array
    {
        $issued = auth_flow_issue_email_otp($conn, $userId, $purpose);
        if (empty($issued['ok'])) {
            return $issued;
        }

        $send = auth_flow_send_email_otp_code($email, (string)$issued['otp'], $purpose);
        if (empty($send['ok'])) {
            auth_flow_consume_otp_challenge($conn, (int)($issued['challenge_id'] ?? 0));
            return ['ok' => false, 'error' => (string)($send['error'] ?? 'Unable to send OTP right now.')];
        }

        return [
            'ok' => true,
            'error' => '',
            'expires_ts' => (int)($issued['expires_ts'] ?? (time() + 300)),
        ];
    }
}

if (!function_exists('auth_flow_parse_trusted_cookie')) {
    function auth_flow_parse_trusted_cookie(): array
    {
        $raw = trim((string)($_COOKIE[auth_flow_cookie_name()] ?? ''));
        if (!preg_match('/^[a-f0-9]{24}\.[a-f0-9]{64}$/', $raw)) {
            return [];
        }
        [$selector, $validator] = explode('.', $raw, 2);
        return ['selector' => $selector, 'validator' => $validator];
    }
}

if (!function_exists('auth_flow_set_trusted_cookie')) {
    function auth_flow_set_trusted_cookie(string $selector, string $validator, int $expiresTs): void
    {
        if ($selector === '' || $validator === '' || $expiresTs <= time()) {
            auth_flow_clear_trusted_device_cookie();
            return;
        }
        $opts = [
            'expires' => $expiresTs,
            'path' => '/',
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        setcookie(auth_flow_cookie_name(), $selector . '.' . $validator, $opts);
    }
}

if (!function_exists('auth_flow_prune_trusted_devices')) {
    function auth_flow_prune_trusted_devices(mysqli $conn, int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $stmt = $conn->prepare(
            "DELETE FROM auth_trusted_devices
             WHERE user_id = ?
               AND (revoked_at IS NOT NULL OR expires_at < NOW())"
        );
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }

        $stmtIds = $conn->prepare(
            "SELECT id
             FROM auth_trusted_devices
             WHERE user_id = ?
               AND revoked_at IS NULL
               AND expires_at >= NOW()
             ORDER BY id DESC"
        );
        if (!$stmtIds) {
            return;
        }
        $stmtIds->bind_param('i', $userId);
        $stmtIds->execute();
        $res = $stmtIds->get_result();
        $ids = [];
        while ($res && ($row = $res->fetch_assoc())) {
            $ids[] = (int)($row['id'] ?? 0);
        }
        $stmtIds->close();

        if (count($ids) <= 50) {
            return;
        }
        $drop = array_slice($ids, 50);
        foreach ($drop as $id) {
            if ($id <= 0) {
                continue;
            }
            $stmtDel = $conn->prepare("DELETE FROM auth_trusted_devices WHERE id = ? AND user_id = ?");
            if ($stmtDel) {
                $stmtDel->bind_param('ii', $id, $userId);
                $stmtDel->execute();
                $stmtDel->close();
            }
        }
    }
}

if (!function_exists('auth_flow_register_trusted_device')) {
    function auth_flow_register_trusted_device(mysqli $conn, int $userId, int $days = 30): bool
    {
        if ($userId <= 0 || $days <= 0) {
            return false;
        }
        if (!auth_flow_ensure_security_tables($conn)) {
            return false;
        }

        $selector = auth_flow_random_token(12);
        $validator = auth_flow_random_token(32);
        if ($selector === '' || $validator === '') {
            return false;
        }

        $validatorHash = hash('sha256', $validator);
        $fingerprintHash = auth_flow_trusted_device_fingerprint();
        $expiresTs = time() + ($days * 86400);
        $expiresAt = date('Y-m-d H:i:s', $expiresTs);
        $ip = security_client_ip();
        $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if (strlen($ua) > 255) {
            $ua = substr($ua, 0, 255);
        }

        $stmt = $conn->prepare(
            "INSERT INTO auth_trusted_devices
                (user_id, selector, validator_hash, fingerprint_hash, expires_at, created_ip, created_user_agent, last_ip, last_user_agent, last_used_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param(
            'issssssss',
            $userId,
            $selector,
            $validatorHash,
            $fingerprintHash,
            $expiresAt,
            $ip,
            $ua,
            $ip,
            $ua
        );
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            return false;
        }

        auth_flow_set_trusted_cookie($selector, $validator, $expiresTs);
        auth_flow_prune_trusted_devices($conn, $userId);
        return true;
    }
}

if (!function_exists('auth_flow_revoke_device_token')) {
    function auth_flow_revoke_device_token(mysqli $conn, int $userId, string $selector): void
    {
        if ($userId <= 0 || $selector === '') {
            return;
        }
        $stmt = $conn->prepare(
            "UPDATE auth_trusted_devices
             SET revoked_at = NOW()
             WHERE user_id = ? AND selector = ?"
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('is', $userId, $selector);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('auth_flow_is_trusted_device')) {
    function auth_flow_is_trusted_device(mysqli $conn, int $userId): bool
    {
        if ($userId <= 0) {
            auth_flow_clear_trusted_device_cookie();
            return false;
        }
        if (!auth_flow_ensure_security_tables($conn)) {
            return false;
        }

        $cookie = auth_flow_parse_trusted_cookie();
        if (empty($cookie['selector']) || empty($cookie['validator'])) {
            return false;
        }

        $selector = (string)$cookie['selector'];
        $validator = (string)$cookie['validator'];

        $stmt = $conn->prepare(
            "SELECT validator_hash, fingerprint_hash, expires_at
             FROM auth_trusted_devices
             WHERE user_id = ?
               AND selector = ?
               AND revoked_at IS NULL
             LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('is', $userId, $selector);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            auth_flow_clear_trusted_device_cookie();
            return false;
        }

        $expiresAtTs = strtotime((string)($row['expires_at'] ?? ''));
        if ($expiresAtTs === false || $expiresAtTs < time()) {
            auth_flow_revoke_device_token($conn, $userId, $selector);
            auth_flow_clear_trusted_device_cookie();
            return false;
        }

        $expectedHash = (string)($row['validator_hash'] ?? '');
        $actualHash = hash('sha256', $validator);
        if ($expectedHash === '' || !hash_equals($expectedHash, $actualHash)) {
            auth_flow_revoke_device_token($conn, $userId, $selector);
            auth_flow_clear_trusted_device_cookie();
            return false;
        }

        $fingerprintExpected = (string)($row['fingerprint_hash'] ?? '');
        $fingerprintActual = auth_flow_trusted_device_fingerprint();
        if ($fingerprintExpected === '' || !hash_equals($fingerprintExpected, $fingerprintActual)) {
            auth_flow_revoke_device_token($conn, $userId, $selector);
            auth_flow_clear_trusted_device_cookie();
            return false;
        }

        $newValidator = auth_flow_random_token(32);
        if ($newValidator !== '') {
            $newHash = hash('sha256', $newValidator);
            $ip = security_client_ip();
            $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
            if (strlen($ua) > 255) {
                $ua = substr($ua, 0, 255);
            }

            $stmtUp = $conn->prepare(
                "UPDATE auth_trusted_devices
                 SET validator_hash = ?,
                     last_used_at = NOW(),
                     last_ip = ?,
                     last_user_agent = ?
                 WHERE user_id = ?
                   AND selector = ?
                   AND revoked_at IS NULL"
            );
            if ($stmtUp) {
                $stmtUp->bind_param('sssis', $newHash, $ip, $ua, $userId, $selector);
                $stmtUp->execute();
                $stmtUp->close();
                auth_flow_set_trusted_cookie($selector, $newValidator, $expiresAtTs);
            }
        }

        return true;
    }
}

if (!function_exists('auth_flow_random_backup_code')) {
    function auth_flow_random_backup_code(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $maxIdx = strlen($alphabet) - 1;
        $raw = '';
        for ($i = 0; $i < 10; $i++) {
            $raw .= $alphabet[random_int(0, $maxIdx)];
        }
        return substr($raw, 0, 5) . '-' . substr($raw, 5);
    }
}

if (!function_exists('auth_flow_normalize_backup_code')) {
    function auth_flow_normalize_backup_code(string $code): string
    {
        $clean = strtoupper(trim($code));
        $clean = preg_replace('/[^A-Z0-9]/', '', $clean) ?? '';
        return $clean;
    }
}

if (!function_exists('auth_flow_replace_backup_codes')) {
    function auth_flow_replace_backup_codes(mysqli $conn, int $userId, int $count = 8): array
    {
        if ($userId <= 0) {
            return [];
        }
        if (!auth_flow_ensure_security_tables($conn)) {
            return [];
        }

        $count = max(1, min(20, $count));
        $codes = [];
        while (count($codes) < $count) {
            $candidate = auth_flow_random_backup_code();
            if (!in_array($candidate, $codes, true)) {
                $codes[] = $candidate;
            }
        }

        $conn->begin_transaction();
        try {
            $stmtDel = $conn->prepare("DELETE FROM auth_backup_codes WHERE user_id = ?");
            if (!$stmtDel) {
                throw new RuntimeException('Delete backup codes failed.');
            }
            $stmtDel->bind_param('i', $userId);
            $stmtDel->execute();
            $stmtDel->close();

            $stmtIns = $conn->prepare(
                "INSERT INTO auth_backup_codes (user_id, code_hash)
                 VALUES (?, ?)"
            );
            if (!$stmtIns) {
                throw new RuntimeException('Insert backup codes failed.');
            }

            foreach ($codes as $code) {
                $normalized = auth_flow_normalize_backup_code($code);
                $hash = password_hash($normalized, PASSWORD_DEFAULT);
                if (!is_string($hash) || $hash === '') {
                    throw new RuntimeException('Hash backup code failed.');
                }
                $stmtIns->bind_param('is', $userId, $hash);
                if (!$stmtIns->execute()) {
                    throw new RuntimeException('Save backup code failed.');
                }
            }
            $stmtIns->close();
            $conn->commit();
            return $codes;
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Backup code generation failed for user #' . $userId . ': ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('auth_flow_verify_backup_code')) {
    function auth_flow_verify_backup_code(mysqli $conn, int $userId, string $input): bool
    {
        if ($userId <= 0) {
            return false;
        }
        if (!auth_flow_ensure_security_tables($conn)) {
            return false;
        }

        $normalized = auth_flow_normalize_backup_code($input);
        if ($normalized === '' || strlen($normalized) < 8 || strlen($normalized) > 20) {
            return false;
        }

        $stmt = $conn->prepare(
            "SELECT id, code_hash
             FROM auth_backup_codes
             WHERE user_id = ?
               AND used_at IS NULL"
        );
        if (!$stmt) {
            return false;
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

        foreach ($rows as $row) {
            $codeHash = (string)($row['code_hash'] ?? '');
            if ($codeHash === '') {
                continue;
            }
            if (password_verify($normalized, $codeHash)) {
                $rowId = (int)($row['id'] ?? 0);
                if ($rowId <= 0) {
                    return false;
                }
                $stmtUse = $conn->prepare(
                    "UPDATE auth_backup_codes
                     SET used_at = NOW()
                     WHERE id = ?
                       AND user_id = ?
                       AND used_at IS NULL"
                );
                if (!$stmtUse) {
                    return false;
                }
                $stmtUse->bind_param('ii', $rowId, $userId);
                $stmtUse->execute();
                $affected = (int)$stmtUse->affected_rows;
                $stmtUse->close();
                return $affected > 0;
            }
        }

        return false;
    }
}

if (!function_exists('auth_flow_remaining_backup_codes')) {
    function auth_flow_remaining_backup_codes(mysqli $conn, int $userId): int
    {
        if ($userId <= 0 || !auth_flow_ensure_security_tables($conn)) {
            return 0;
        }

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM auth_backup_codes
             WHERE user_id = ?
               AND used_at IS NULL"
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return (int)($row['total'] ?? 0);
    }
}

if (!function_exists('auth_flow_send_backup_codes_email')) {
    function auth_flow_send_backup_codes_email(string $email, array $codes): bool
    {
        $email = data_normalize_email($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $codes === []) {
            return false;
        }

        require_once dirname(__DIR__) . '/config/secrets.php';
        require_once dirname(__DIR__) . '/sendphpmailer/PHPMailer.php';
        require_once dirname(__DIR__) . '/sendphpmailer/SMTP.php';
        require_once dirname(__DIR__) . '/sendphpmailer/Exception.php';

        $escapedCodes = [];
        foreach ($codes as $code) {
            $escapedCodes[] = htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8');
        }
        $listItems = '<li>' . implode('</li><li>', $escapedCodes) . '</li>';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = SMTP_AUTH;
            if (SMTP_HOST === '') {
                return false;
            }
            if (SMTP_AUTH && (SMTP_USER === '' || SMTP_PASS === '')) {
                return false;
            }
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            if (in_array(SMTP_SECURE, ['tls', 'starttls'], true)) {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } elseif (in_array(SMTP_SECURE, ['ssl', 'smtps'], true)) {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }
            $mail->Port = SMTP_PORT;
            $mail->Timeout = 10;
            $mail->setFrom(SMTP_SENDER, SMTP_SENDER_NAME);
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Your 2FA Backup Codes';
            $mail->Body = "
                <div style='font-family:Arial,sans-serif;max-width:560px;margin:auto'>
                    <h3>Your Backup Codes</h3>
                    <p>Store these codes securely. Each code can be used once.</p>
                    <ol>{$listItems}</ol>
                    <p>If you did not enable 2FA, contact support immediately.</p>
                </div>
            ";
            $mail->send();
            return true;
        } catch (Throwable $e) {
            error_log('Backup codes email send failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('auth_flow_admin_reset_twofa')) {
    function auth_flow_admin_reset_twofa(mysqli $conn, int $targetUserId): array
    {
        if ($targetUserId <= 0) {
            return ['ok' => false, 'message' => 'Invalid target user id.'];
        }
        if (!auth_flow_ensure_security_tables($conn)) {
            return ['ok' => false, 'message' => 'Unable to initialize security tables.'];
        }

        require_once __DIR__ . '/totp.php';
        $conn->begin_transaction();
        try {
            if (!totp_disable_for_user($conn, $targetUserId)) {
                throw new RuntimeException('Unable to disable authenticator.');
            }

            $stmtBackup = $conn->prepare("DELETE FROM auth_backup_codes WHERE user_id = ?");
            if (!$stmtBackup) {
                throw new RuntimeException('Unable to clear backup codes.');
            }
            $stmtBackup->bind_param('i', $targetUserId);
            $stmtBackup->execute();
            $stmtBackup->close();

            $stmtTrusted = $conn->prepare(
                "UPDATE auth_trusted_devices
                 SET revoked_at = NOW()
                 WHERE user_id = ?
                   AND revoked_at IS NULL"
            );
            if (!$stmtTrusted) {
                throw new RuntimeException('Unable to revoke trusted devices.');
            }
            $stmtTrusted->bind_param('i', $targetUserId);
            $stmtTrusted->execute();
            $stmtTrusted->close();

            $stmtOtp = $conn->prepare(
                "UPDATE auth_email_otp
                 SET consumed_at = NOW()
                 WHERE user_id = ?
                   AND consumed_at IS NULL"
            );
            if ($stmtOtp) {
                $stmtOtp->bind_param('i', $targetUserId);
                $stmtOtp->execute();
                $stmtOtp->close();
            }

            $conn->commit();
            return ['ok' => true, 'message' => 'User 2FA has been reset.'];
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Admin 2FA reset failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Failed to reset user 2FA.'];
        }
    }
}
