<?php
declare(strict_types=1);

function data_base64url_encode(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function data_base64url_decode(string $encoded): string
{
    $payload = strtr($encoded, '-_', '+/');
    $padding = strlen($payload) % 4;
    if ($padding > 0) {
        $payload .= str_repeat('=', 4 - $padding);
    }

    $decoded = base64_decode($payload, true);
    return is_string($decoded) ? $decoded : '';
}

function data_environment_is_production(): bool
{
    return strtolower(trim((string)(getenv('APP_ENV') ?: 'local'))) === 'production';
}

function data_keys_file_path(): string
{
    $fromEnv = trim((string)(getenv('CITY_ENRO_KEYS_FILE') ?: ''));
    if ($fromEnv !== '') {
        return $fromEnv;
    }

    // Default outside web root (e.g. c:\xampp\city_enro_keys.php)
    return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'city_enro_keys.php';
}

function data_file_is_outside_webroot(string $path): bool
{
    $realPath = realpath($path);
    if ($realPath === false) {
        return false;
    }

    $docRoot = trim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($docRoot === '') {
        return true;
    }

    $realRoot = realpath($docRoot);
    if ($realRoot === false) {
        return true;
    }

    $pathNorm = str_replace('\\', '/', strtolower($realPath));
    $rootNorm = rtrim(str_replace('\\', '/', strtolower($realRoot)), '/');
    return strpos($pathNorm, $rootNorm . '/') !== 0 && $pathNorm !== $rootNorm;
}

function data_file_permissions_safe(string $path): bool
{
    // Windows ACLs are not reliably represented by fileperms bitmask.
    if (DIRECTORY_SEPARATOR === '\\') {
        return true;
    }

    $perms = @fileperms($path);
    if (!is_int($perms) || $perms <= 0) {
        return false;
    }

    // Require no group/other permissions.
    return (($perms & 0x1FF) & 0x077) === 0;
}

function data_parse_32byte_key(string $raw): string
{
    $value = trim($raw);
    if ($value === '') {
        return '';
    }

    if (stripos($value, 'base64:') === 0) {
        $value = trim(substr($value, 7));
    }

    $b64 = base64_decode($value, true);
    if (is_string($b64) && strlen($b64) === 32) {
        return $b64;
    }

    if (preg_match('/^[a-f0-9]{64}$/i', $value)) {
        $hex = hex2bin($value);
        if (is_string($hex) && strlen($hex) === 32) {
            return $hex;
        }
    }

    return '';
}

function data_load_keys_from_file(string $filePath): array
{
    if (!is_file($filePath)) {
        return [];
    }
    if (!data_file_is_outside_webroot($filePath)) {
        return [];
    }
    if (!data_file_permissions_safe($filePath)) {
        return [];
    }

    $loaded = @include $filePath;

    if (is_array($loaded)) {
        return [
            'DATA_KEY_BASE64_CURRENT' => (string)($loaded['DATA_KEY_BASE64_CURRENT'] ?? $loaded['DATA_KEY_BASE64'] ?? ''),
            'DATA_KEY_BASE64_PREVIOUS' => (string)($loaded['DATA_KEY_BASE64_PREVIOUS'] ?? ''),
            'EMAIL_HASH_KEY_CURRENT' => (string)($loaded['EMAIL_HASH_KEY_CURRENT'] ?? $loaded['EMAIL_HASH_KEY'] ?? ''),
            'EMAIL_HASH_KEY_PREVIOUS' => (string)($loaded['EMAIL_HASH_KEY_PREVIOUS'] ?? ''),
        ];
    }

    // Legacy single-string key file support.
    if (is_string($loaded) && trim($loaded) !== '') {
        return [
            'DATA_KEY_BASE64_CURRENT' => (string)$loaded,
            'DATA_KEY_BASE64_PREVIOUS' => '',
            'EMAIL_HASH_KEY_CURRENT' => '',
            'EMAIL_HASH_KEY_PREVIOUS' => '',
        ];
    }

}

function data_load_keys_from_runtime_config(): array
{
    $runtimePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'runtime.php';
    if (!is_file($runtimePath)) {
        return [];
    }

    $loaded = @include $runtimePath;
    if (!is_array($loaded)) {
        return [];
    }

    $security = $loaded['security'] ?? null;
    if (!is_array($security)) {
        return [];
    }

    return [
        'DATA_KEY_BASE64_CURRENT' => (string)($security['data_key_current'] ?? $security['data_key'] ?? ''),
        'DATA_KEY_BASE64_PREVIOUS' => (string)($security['data_key_previous'] ?? ''),
        'EMAIL_HASH_KEY_CURRENT' => (string)($security['email_hash_key_current'] ?? $security['email_hash_key'] ?? ''),
        'EMAIL_HASH_KEY_PREVIOUS' => (string)($security['email_hash_key_previous'] ?? ''),
    ];
}

function data_keys(): array
{
    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }

    $env = [
        'DATA_KEY_BASE64_CURRENT' => trim((string)(getenv('DATA_KEY_BASE64_CURRENT') ?: getenv('DATA_KEY_BASE64') ?: '')),
        'DATA_KEY_BASE64_PREVIOUS' => trim((string)(getenv('DATA_KEY_BASE64_PREVIOUS') ?: '')),
        'EMAIL_HASH_KEY_CURRENT' => trim((string)(getenv('EMAIL_HASH_KEY_CURRENT') ?: getenv('EMAIL_HASH_KEY') ?: '')),
        'EMAIL_HASH_KEY_PREVIOUS' => trim((string)(getenv('EMAIL_HASH_KEY_PREVIOUS') ?: '')),
    ];

    if (
        $env['DATA_KEY_BASE64_CURRENT'] === '' ||
        $env['EMAIL_HASH_KEY_CURRENT'] === ''
    ) {
        $fromRuntime = data_load_keys_from_runtime_config();
        foreach ($fromRuntime as $k => $v) {
            if (($env[$k] ?? '') === '' && trim((string)$v) !== '') {
                $env[$k] = trim((string)$v);
            }
        }
    }

    if (
        $env['DATA_KEY_BASE64_CURRENT'] === '' ||
        $env['EMAIL_HASH_KEY_CURRENT'] === ''
    ) {
        $fromFile = data_load_keys_from_file(data_keys_file_path());
        foreach ($fromFile as $k => $v) {
            if (($env[$k] ?? '') === '' && trim((string)$v) !== '') {
                $env[$k] = trim((string)$v);
            }
        }
    }

    $dataCurrent = data_parse_32byte_key((string)$env['DATA_KEY_BASE64_CURRENT']);
    $dataPrevious = data_parse_32byte_key((string)$env['DATA_KEY_BASE64_PREVIOUS']);
    $hashCurrent = (string)$env['EMAIL_HASH_KEY_CURRENT'];
    $hashPrevious = (string)$env['EMAIL_HASH_KEY_PREVIOUS'];

    $cached = [
        'data_current' => $dataCurrent,
        'data_previous' => $dataPrevious,
        'hash_current' => $hashCurrent,
        'hash_previous' => $hashPrevious,
    ];

    return $cached;
}

function data_keys_ready(): bool
{
    $keys = data_keys();
    return $keys['data_current'] !== '' && $keys['hash_current'] !== '';
}

function data_encryption_keys_for_decrypt(): array
{
    $keys = data_keys();
    $list = [];

    if ($keys['data_current'] !== '') {
        $list[] = $keys['data_current'];
    }
    if ($keys['data_previous'] !== '' && $keys['data_previous'] !== $keys['data_current']) {
        $list[] = $keys['data_previous'];
    }

    return $list;
}

function data_build_aad(int $recordId, string $field): string
{
    if ($recordId <= 0 || $field === '') {
        return '';
    }
    return 'user_form:' . $recordId . ':' . $field;
}

function data_encrypt_text(string $plaintext, int $recordId = 0, string $field = ''): string
{
    if ($plaintext === '' || !function_exists('openssl_encrypt')) {
        return '';
    }

    $keys = data_keys();
    $key = (string)$keys['data_current'];
    if ($key === '') {
        return '';
    }

    try {
        $nonce = random_bytes(12);
    } catch (Throwable $e) {
        return '';
    }

    $aad = data_build_aad($recordId, $field);
    $tag = '';
    $cipher = openssl_encrypt(
        $plaintext,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $nonce,
        $tag,
        $aad,
        16
    );

    if (!is_string($cipher) || $cipher === '' || $tag === '') {
        return '';
    }

    return 'enc2:'
        . data_base64url_encode($nonce)
        . '.' . data_base64url_encode($tag)
        . '.' . data_base64url_encode($cipher);
}

function data_decrypt_enc2(string $payload, int $recordId = 0, string $field = ''): string
{
    $body = substr($payload, 5);
    $parts = explode('.', $body, 3);
    if (count($parts) !== 3) {
        return '';
    }

    [$nonceB64, $tagB64, $cipherB64] = $parts;
    $nonce = data_base64url_decode($nonceB64);
    $tag = data_base64url_decode($tagB64);
    $cipher = data_base64url_decode($cipherB64);

    if ($nonce === '' || $tag === '' || $cipher === '') {
        return '';
    }

    $aad = data_build_aad($recordId, $field);
    foreach (data_encryption_keys_for_decrypt() as $key) {
        $plain = openssl_decrypt(
            $cipher,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad
        );
        if (is_string($plain)) {
            return $plain;
        }
    }

    return '';
}

function data_decrypt_legacy_enc1(string $payload): string
{
    $raw = data_base64url_decode(substr($payload, 5));
    if ($raw === '' || strlen($raw) < 28) {
        return '';
    }

    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);

    foreach (data_encryption_keys_for_decrypt() as $key) {
        $plain = openssl_decrypt(
            $cipher,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        if (is_string($plain)) {
            return $plain;
        }
    }

    return '';
}

function data_decrypt_text(string $payload, int $recordId = 0, string $field = ''): string
{
    if ($payload === '' || !function_exists('openssl_decrypt')) {
        return $payload;
    }

    if (strpos($payload, 'enc2:') === 0) {
        return data_decrypt_enc2($payload, $recordId, $field);
    }

    if (strpos($payload, 'enc1:') === 0) {
        return data_decrypt_legacy_enc1($payload);
    }

    return $payload;
}

function data_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function data_email_hash(string $email): string
{
    $normalized = data_normalize_email($email);
    if ($normalized === '') {
        return '';
    }

    $keys = data_keys();
    $hashKey = (string)$keys['hash_current'];
    if ($hashKey === '') {
        return '';
    }

    return hash_hmac('sha256', $normalized, $hashKey);
}

function data_email_hash_candidates(string $email): array
{
    $normalized = data_normalize_email($email);
    if ($normalized === '') {
        return [];
    }

    $keys = data_keys();
    $out = [];

    $current = (string)$keys['hash_current'];
    if ($current !== '') {
        $out[] = hash_hmac('sha256', $normalized, $current);
    }

    $previous = (string)$keys['hash_previous'];
    if ($previous !== '' && $previous !== $current) {
        $out[] = hash_hmac('sha256', $normalized, $previous);
    }

    return array_values(array_unique(array_filter($out, static function ($v) {
        return is_string($v) && $v !== '';
    })));
}

function data_conn_cache_key(mysqli $conn): string
{
    if (function_exists('spl_object_id')) {
        return 'conn:' . (string)spl_object_id($conn);
    }
    return 'thread:' . (string)((int)($conn->thread_id ?? 0));
}

function data_user_form_has_column(mysqli $conn, string $column, bool $refresh = false): bool
{
    static $cache = [];
    $columnKey = strtolower(trim($column));
    if ($columnKey === '') {
        return false;
    }
    $cacheKey = data_conn_cache_key($conn) . ':col:' . $columnKey;

    if ($refresh || !array_key_exists($cacheKey, $cache)) {
        $safe = $conn->real_escape_string($columnKey);
        $res = $conn->query("SHOW COLUMNS FROM user_form LIKE '{$safe}'");
        $cache[$cacheKey] = (bool)($res && $res->num_rows > 0);
    }

    return (bool)$cache[$cacheKey];
}

function data_user_form_has_index(mysqli $conn, string $indexName, bool $refresh = false): bool
{
    static $cache = [];
    $indexKey = strtolower(trim($indexName));
    if ($indexKey === '') {
        return false;
    }
    $cacheKey = data_conn_cache_key($conn) . ':idx:' . $indexKey;
    if ($refresh || !array_key_exists($cacheKey, $cache)) {
        $safe = $conn->real_escape_string($indexKey);
        $res = $conn->query("SHOW INDEX FROM user_form WHERE Key_name = '{$safe}'");
        $cache[$cacheKey] = (bool)($res && $res->num_rows > 0);
    }
    return (bool)$cache[$cacheKey];
}

function data_user_form_ensure_encryption_columns(mysqli $conn): bool
{
    static $ready = [];
    $connKey = data_conn_cache_key($conn);
    if (($ready[$connKey] ?? false) === true) {
        return true;
    }

    $columns = [
        'email_hash' => "CHAR(64) NULL DEFAULT NULL",
        'email_enc' => "TEXT NULL DEFAULT NULL",
        'name_enc' => "TEXT NULL DEFAULT NULL",
        'barangay_enc' => "TEXT NULL DEFAULT NULL",
    ];

    foreach ($columns as $column => $ddl) {
        if (!data_user_form_has_column($conn, $column)) {
            if (!$conn->query("ALTER TABLE user_form ADD COLUMN {$column} {$ddl}")) {
                $ready[$connKey] = false;
                return false;
            }
            data_user_form_has_column($conn, $column, true);
        }
    }

    if (!data_user_form_has_index($conn, 'idx_user_form_email_hash')) {
        if (!$conn->query("ALTER TABLE user_form ADD INDEX idx_user_form_email_hash (email_hash)")) {
            if (!data_user_form_has_index($conn, 'idx_user_form_email_hash', true)) {
                $ready[$connKey] = false;
                return false;
            }
        }
        data_user_form_has_index($conn, 'idx_user_form_email_hash', true);
    }

    $ready[$connKey] = true;
    return true;
}

function data_user_row_apply_decryption(array $row): array
{
    $recordId = (int)($row['id'] ?? 0);
    $map = [
        'email' => 'email_enc',
        'name' => 'name_enc',
        'barangay' => 'barangay_enc',
    ];

    foreach ($map as $plainCol => $encCol) {
        $encValue = trim((string)($row[$encCol] ?? ''));
        if ($encValue === '') {
            continue;
        }

        $decrypted = data_decrypt_text($encValue, $recordId, $plainCol);
        if ($decrypted !== '') {
            $row[$plainCol] = $decrypted;
        }
    }

    return $row;
}

function data_sync_user_encryption(mysqli $conn, int $userId, string $name, string $email, string $barangay): bool
{
    if ($userId <= 0) {
        return false;
    }
    if (!data_user_form_ensure_encryption_columns($conn)) {
        return false;
    }

    $emailNormalized = data_normalize_email($email);
    $emailHash = $emailNormalized !== '' ? data_email_hash($emailNormalized) : '';
    $emailEnc = $emailNormalized !== '' ? data_encrypt_text($emailNormalized, $userId, 'email') : '';
    $nameEnc = $name !== '' ? data_encrypt_text($name, $userId, 'name') : '';
    $barangayEnc = $barangay !== '' ? data_encrypt_text($barangay, $userId, 'barangay') : '';

    // Do not overwrite with empty values when key material is missing.
    if ($emailHash === '' && $emailEnc === '' && $nameEnc === '' && $barangayEnc === '') {
        return false;
    }

    $stmt = $conn->prepare(
        "UPDATE user_form
         SET email_hash = ?, email_enc = ?, name_enc = ?, barangay_enc = ?
         WHERE id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ssssi', $emailHash, $emailEnc, $nameEnc, $barangayEnc, $userId);
    $ok = $stmt->execute();
    $stmt->close();

    return (bool)$ok;
}

function data_sync_user_row_if_needed(mysqli $conn, array $row): void
{
    $userId = (int)($row['id'] ?? 0);
    if ($userId <= 0) {
        return;
    }

    $name = (string)($row['name'] ?? '');
    $email = (string)($row['email'] ?? '');
    $barangay = (string)($row['barangay'] ?? '');

    $expectedHash = $email !== '' ? data_email_hash($email) : '';

    $needsEmailSync = $email !== '' && (
        trim((string)($row['email_hash'] ?? '')) === '' ||
        trim((string)($row['email_enc'] ?? '')) === '' ||
        ($expectedHash !== '' && !hash_equals((string)($row['email_hash'] ?? ''), $expectedHash))
    );
    $needsNameSync = $name !== '' && trim((string)($row['name_enc'] ?? '')) === '';
    $needsBarangaySync = $barangay !== '' && trim((string)($row['barangay_enc'] ?? '')) === '';

    if ($needsEmailSync || $needsNameSync || $needsBarangaySync) {
        data_sync_user_encryption($conn, $userId, $name, $email, $barangay);
    }
}

function data_find_user_by_email(mysqli $conn, string $email): ?array
{
    $emailNormalized = data_normalize_email($email);
    if ($emailNormalized === '') {
        return null;
    }

    data_user_form_ensure_encryption_columns($conn);

    $row = null;
    $hashes = data_email_hash_candidates($emailNormalized);
    if (count($hashes) === 1) {
        $stmt = $conn->prepare('SELECT * FROM user_form WHERE email_hash = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $hashes[0]);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
        }
    } elseif (count($hashes) >= 2) {
        $stmt = $conn->prepare('SELECT * FROM user_form WHERE email_hash IN (?, ?) LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('ss', $hashes[0], $hashes[1]);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
        }
    }

    // Compatibility fallback for rows not backfilled yet.
    if (!$row) {
        $stmt = $conn->prepare('SELECT * FROM user_form WHERE email = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $emailNormalized);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
        }
    }

    if (!$row) {
        return null;
    }

    $row = data_user_row_apply_decryption($row);
    if (trim((string)($row['email'] ?? '')) === '') {
        $row['email'] = $emailNormalized;
    }

    data_sync_user_row_if_needed($conn, $row);
    return $row;
}

function data_find_user_by_id(mysqli $conn, int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    data_user_form_ensure_encryption_columns($conn);

    $stmt = $conn->prepare('SELECT * FROM user_form WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

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
