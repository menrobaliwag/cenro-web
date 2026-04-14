<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';
secure_session_start();

if (!function_exists('totp_base32_alphabet')) {
    function totp_base32_alphabet(): string
    {
        return 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    }
}

if (!function_exists('totp_base32_encode')) {
    function totp_base32_encode(string $binary): string
    {
        if ($binary === '') {
            return '';
        }

        $alphabet = totp_base32_alphabet();
        $bits = '';
        $length = strlen($binary);
        for ($i = 0; $i < $length; $i++) {
            $bits .= str_pad(decbin(ord($binary[$i])), 8, '0', STR_PAD_LEFT);
        }

        $output = '';
        $bitLength = strlen($bits);
        for ($i = 0; $i < $bitLength; $i += 5) {
            $chunk = substr($bits, $i, 5);
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $output .= $alphabet[bindec($chunk)];
        }

        return $output;
    }
}

if (!function_exists('totp_base32_decode')) {
    function totp_base32_decode(string $base32): string
    {
        $clean = strtoupper(trim($base32));
        $clean = preg_replace('/[^A-Z2-7]/', '', $clean) ?? '';
        if ($clean === '') {
            return '';
        }

        $alphabet = totp_base32_alphabet();
        $map = [];
        $length = strlen($alphabet);
        for ($i = 0; $i < $length; $i++) {
            $map[$alphabet[$i]] = $i;
        }

        $bits = '';
        $cleanLength = strlen($clean);
        for ($i = 0; $i < $cleanLength; $i++) {
            $char = $clean[$i];
            if (!isset($map[$char])) {
                return '';
            }
            $bits .= str_pad(decbin((int)$map[$char]), 5, '0', STR_PAD_LEFT);
        }

        $output = '';
        $bitLength = strlen($bits);
        for ($i = 0; $i + 8 <= $bitLength; $i += 8) {
            $output .= chr(bindec(substr($bits, $i, 8)));
        }

        return $output;
    }
}

if (!function_exists('totp_generate_secret')) {
    function totp_generate_secret(int $byteLength = 20): string
    {
        $byteLength = max(10, min(64, $byteLength));
        try {
            $random = random_bytes($byteLength);
        } catch (Throwable $e) {
            $random = openssl_random_pseudo_bytes($byteLength) ?: '';
        }
        return totp_base32_encode((string)$random);
    }
}

if (!function_exists('totp_generate_code')) {
    function totp_generate_code(string $secret, ?int $timeSlice = null, int $digits = 6, int $period = 30): string
    {
        $key = totp_base32_decode($secret);
        if ($key === '') {
            return '';
        }

        $period = max(15, $period);
        $digits = max(6, min(8, $digits));
        if ($timeSlice === null) {
            $timeSlice = (int)floor(time() / $period);
        }

        $counter = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $counter, $key, true);
        if (!is_string($hash) || strlen($hash) < 20) {
            return '';
        }

        $offset = ord(substr($hash, -1)) & 0x0F;
        $segment = substr($hash, $offset, 4);
        $unpacked = unpack('N', $segment);
        if (!is_array($unpacked) || !isset($unpacked[1])) {
            return '';
        }

        $value = ((int)$unpacked[1]) & 0x7FFFFFFF;
        $mod = 10 ** $digits;
        return str_pad((string)($value % $mod), $digits, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('totp_verify_code')) {
    function totp_verify_code(string $secret, string $code, int $window = 1, int $digits = 6, int $period = 30): bool
    {
        $code = trim($code);
        if (!preg_match('/^[0-9]{6,8}$/', $code)) {
            return false;
        }

        $window = max(0, min(5, $window));
        $currentSlice = (int)floor(time() / max(15, $period));

        for ($i = -$window; $i <= $window; $i++) {
            $candidate = totp_generate_code($secret, $currentSlice + $i, $digits, $period);
            if ($candidate !== '' && hash_equals($candidate, $code)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('totp_build_provisioning_uri')) {
    function totp_build_provisioning_uri(string $issuer, string $account, string $secret): string
    {
        $issuer = trim($issuer);
        $account = trim($account);
        if ($issuer === '' || $account === '' || $secret === '') {
            return '';
        }

        $label = rawurlencode($issuer . ':' . $account);
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ]);

        return 'otpauth://totp/' . $label . '?' . $query;
    }
}

if (!function_exists('totp_has_column')) {
    function totp_has_column(mysqli $conn, string $table, string $column): bool
    {
        $safeTable = $conn->real_escape_string($table);
        $safeColumn = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        return $res instanceof mysqli_result && $res->num_rows > 0;
    }
}

if (!function_exists('totp_user_settings_ready')) {
    function totp_user_settings_ready(mysqli $conn): bool
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
            'otp_enabled' => "TINYINT(1) NOT NULL DEFAULT 0",
            'totp_secret_enc' => "LONGTEXT NULL DEFAULT NULL",
            'totp_enabled_at' => "DATETIME NULL DEFAULT NULL",
            'totp_last_used_at' => "DATETIME NULL DEFAULT NULL",
        ];

        foreach ($requiredCols as $colName => $colSql) {
            if (!totp_has_column($conn, 'user_settings', $colName)) {
                if (!$conn->query("ALTER TABLE user_settings ADD COLUMN {$colName} {$colSql}")) {
                    return false;
                }
            }
        }

        return true;
    }
}

if (!function_exists('totp_load_user_config')) {
    function totp_load_user_config(mysqli $conn, int $userId): array
    {
        $defaults = [
            'enabled' => false,
            'secret_enc' => '',
            'secret' => '',
            'enabled_at' => '',
            'last_used_at' => '',
        ];

        if ($userId <= 0 || !totp_user_settings_ready($conn)) {
            return $defaults;
        }

        $stmt = $conn->prepare(
            "SELECT otp_enabled, totp_secret_enc, totp_enabled_at, totp_last_used_at
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

        $secretEnc = trim((string)($row['totp_secret_enc'] ?? ''));
        $secret = '';
        if ($secretEnc !== '') {
            $secret = data_decrypt_text($secretEnc, $userId, 'totp_secret');
        }

        return [
            'enabled' => ((int)($row['otp_enabled'] ?? 0) === 1) && $secret !== '',
            'secret_enc' => $secretEnc,
            'secret' => $secret,
            'enabled_at' => (string)($row['totp_enabled_at'] ?? ''),
            'last_used_at' => (string)($row['totp_last_used_at'] ?? ''),
        ];
    }
}

if (!function_exists('totp_enable_for_user')) {
    function totp_enable_for_user(mysqli $conn, int $userId, string $secret): bool
    {
        $secret = trim($secret);
        if ($userId <= 0 || $secret === '' || !totp_user_settings_ready($conn)) {
            return false;
        }

        $secretEnc = data_encrypt_text($secret, $userId, 'totp_secret');
        if ($secretEnc === '') {
            return false;
        }

        $enabled = 1;
        $stmt = $conn->prepare(
            "INSERT INTO user_settings (user_id, otp_enabled, totp_secret_enc, totp_enabled_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                otp_enabled = VALUES(otp_enabled),
                totp_secret_enc = VALUES(totp_secret_enc),
                totp_enabled_at = NOW()"
        );
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('iis', $userId, $enabled, $secretEnc);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}

if (!function_exists('totp_disable_for_user')) {
    function totp_disable_for_user(mysqli $conn, int $userId): bool
    {
        if ($userId <= 0 || !totp_user_settings_ready($conn)) {
            return false;
        }

        $disabled = 0;
        $stmt = $conn->prepare(
            "INSERT INTO user_settings (user_id, otp_enabled, totp_secret_enc, totp_enabled_at, totp_last_used_at)
             VALUES (?, ?, NULL, NULL, NULL)
             ON DUPLICATE KEY UPDATE
                otp_enabled = VALUES(otp_enabled),
                totp_secret_enc = VALUES(totp_secret_enc),
                totp_enabled_at = NULL,
                totp_last_used_at = NULL"
        );
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ii', $userId, $disabled);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}

if (!function_exists('totp_mark_used_for_user')) {
    function totp_mark_used_for_user(mysqli $conn, int $userId): void
    {
        if ($userId <= 0 || !totp_user_settings_ready($conn)) {
            return;
        }

        $stmt = $conn->prepare(
            "UPDATE user_settings
             SET totp_last_used_at = NOW()
             WHERE user_id = ?"
        );
        if (!$stmt) {
            return;
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }
}
