<?php
declare(strict_types=1);

require_once __DIR__ . '/data_encryption.php';

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

if (!function_exists('security_detect_base_url')) {
    function security_detect_base_url(): string
    {
        $envBase = getenv('BASE_URL');
        if ($envBase !== false && trim((string)$envBase) !== '') {
            return rtrim((string)$envBase, '/');
        }

        $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $appRoot = realpath((string)APP_ROOT);
        if ($documentRoot !== false && $appRoot !== false) {
            $docNormalized = str_replace('\\', '/', $documentRoot);
            $appNormalized = str_replace('\\', '/', $appRoot);
            if (stripos($appNormalized, $docNormalized) === 0) {
                $relative = substr($appNormalized, strlen($docNormalized));
                $relative = '/' . ltrim((string)$relative, '/');
                return $relative === '/' ? '' : rtrim($relative, '/');
            }
        }

        return '';
    }
}

// Application base URL (public path prefix, no trailing slash).
// Auto-detects root vs subfolder deployment; BASE_URL env still overrides.
if (!defined('BASE_URL')) {
    define('BASE_URL', security_detect_base_url());
}

if (!function_exists('url_with_base')) {
    /**
     * Prefix a relative path with the public base URL.
     *
     * @param string $path Relative path like 'assets/css/style.css'
     */
    function url_with_base(string $path): string
    {
        $normalized = '/' . ltrim($path, '/');
        return (BASE_URL === '') ? $normalized : (BASE_URL . $normalized);
    }
}

if (!function_exists('security_strip_legacy_base_path')) {
    function security_strip_legacy_base_path(string $path): string
    {
        $normalized = preg_replace('~^https?://[^/]+~i', '', trim($path)) ?? trim($path);
        $candidates = [];
        if (BASE_URL !== '') {
            $candidates[] = BASE_URL;
        }
        $candidates[] = '/city_enro';

        foreach (array_unique($candidates) as $prefix) {
            $prefix = '/' . trim((string)$prefix, '/');
            if ($prefix === '/') {
                continue;
            }
            if ($normalized === $prefix) {
                return '';
            }
            if (str_starts_with($normalized, $prefix . '/')) {
                return substr($normalized, strlen($prefix));
            }
        }

        return $normalized;
    }
}

if (!function_exists('normalize_public_app_path')) {
    function normalize_public_app_path(string $path): string
    {
        $stripped = security_strip_legacy_base_path($path);
        return url_with_base(ltrim($stripped, '/'));
    }
}

function is_https(): bool 
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}

function security_host_without_port(string $host): string
{
    $host = trim($host);
    if ($host === '') {
        return '';
    }

    // IPv6 in host header: [::1]:8080
    if (preg_match('/^\[(.*)\](?::\d+)?$/', $host, $m)) {
        return strtolower(trim((string)$m[1]));
    }

    $parts = explode(':', $host, 2);
    return strtolower(trim((string)$parts[0]));
}

function security_app_env(): string
{
    return strtolower(trim((string)(getenv('APP_ENV') ?: 'local')));
}

function security_is_production_environment(): bool
{
    return security_app_env() === 'production';
}

function security_is_local_environment(): bool
{
    $host = security_host_without_port((string)($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '')));
    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        return true;
    }
    if ($host !== '' && str_ends_with($host, '.local')) {
        return true;
    }
    return false;
}

function security_force_https_enabled(): bool
{
    $flag = strtolower(trim((string)(getenv('APP_FORCE_HTTPS') ?: '')));
    if ($flag !== '') {
        return !in_array($flag, ['0', 'false', 'off', 'no'], true);
    }

    // Default: enforce HTTPS outside localhost/local environments.
    return !security_is_local_environment();
}

function security_apply_runtime_ini(): void
{
    if (!security_is_production_environment()) {
        return;
    }

    // Do not reveal runtime errors to end users in production.
    @ini_set('display_errors', '0');
    @ini_set('display_startup_errors', '0');
    @ini_set('html_errors', '0');
    @ini_set('log_errors', '1');
    @ini_set('expose_php', '0');
    error_reporting(E_ALL);
}

function security_register_exception_handlers(): void
{
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;

    set_exception_handler(function (Throwable $e): void {
        error_log(
            'Uncaught exception [' . get_class($e) . '] in '
            . $e->getFile() . ':' . $e->getLine()
            . ' -> ' . $e->getMessage()
        );

        if (!headers_sent()) {
            http_response_code(500);
        }
        exit(security_is_production_environment() ? 'Internal Server Error' : 'Application error.');
    });

    register_shutdown_function(function (): void {
        $err = error_get_last();
        if (!is_array($err)) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array((int)($err['type'] ?? 0), $fatalTypes, true)) {
            return;
        }

        error_log(
            'Fatal shutdown error in '
            . (string)($err['file'] ?? 'unknown')
            . ':' . (int)($err['line'] ?? 0)
            . ' -> ' . (string)($err['message'] ?? 'unknown')
        );

        if (security_is_production_environment() && !headers_sent()) {
            http_response_code(500);
        }
    });
}

function security_require_encryption_keys_in_production(): void
{
    if (!security_is_production_environment()) {
        return;
    }

    if (!data_keys_ready()) {
        error_log('Security configuration error: DATA_KEY_BASE64_CURRENT/DATA_KEY_BASE64 and EMAIL_HASH_KEY_CURRENT/EMAIL_HASH_KEY must be configured in production.');
        http_response_code(500);
        exit('Security configuration error.');
    }
}

function security_enforce_https(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    if (is_https() || security_is_local_environment() || !security_force_https_enabled()) {
        return;
    }

    $hostRaw = (string)($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? ''));
    $hostOnly = security_host_without_port($hostRaw);
    if ($hostOnly === '') {
        return;
    }

    // Basic host sanitization for redirect safety.
    $safeHost = preg_replace('/[^a-z0-9\.\-\[\]:]/i', '', $hostOnly) ?? '';
    if ($safeHost === '') {
        return;
    }

    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    if ($uri === '') {
        $uri = '/';
    }
    $statusCode = in_array(strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')), ['GET', 'HEAD'], true) ? 301 : 307;
    header('Location: https://' . $safeHost . $uri, true, $statusCode);
    exit();
}

function secure_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    $secure = is_https();
    ini_set('session.cookie_secure', $secure ? '1' : '0');

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function csrf_input(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function require_csrf(): void
{
    if (!security_is_state_changing_method()) {
        return;
    }
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($token) || $token === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

function security_is_state_changing_method(): bool
{
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
}

function security_enforce_csrf_for_state_changes(): void
{
    if (!security_is_state_changing_method()) {
        return;
    }
    require_csrf();
}

function security_trusted_proxies(): array
{
    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }

    $cached = [];
    $path = __DIR__ . '/../config/trusted_proxies.php';
    if (!is_file($path)) {
        return $cached;
    }

    $loaded = require $path;
    if (!is_array($loaded)) {
        return $cached;
    }

    foreach ($loaded as $rule) {
        if (!is_string($rule)) {
            continue;
        }
        $rule = trim($rule);
        if ($rule !== '') {
            $cached[] = $rule;
        }
    }

    return $cached;
}

function security_ip_in_cidr(string $ip, string $cidr): bool
{
    $cidr = trim($cidr);
    if ($cidr === '' || !str_contains($cidr, '/')) {
        return false;
    }

    [$network, $prefixRaw] = array_map('trim', explode('/', $cidr, 2));
    if ($network === '' || $prefixRaw === '' || !ctype_digit($prefixRaw)) {
        return false;
    }

    $ipBin = @inet_pton($ip);
    $netBin = @inet_pton($network);
    if ($ipBin === false || $netBin === false) {
        return false;
    }
    if (strlen($ipBin) !== strlen($netBin)) {
        return false;
    }

    $prefix = (int)$prefixRaw;
    $maxBits = strlen($ipBin) * 8;
    if ($prefix < 0 || $prefix > $maxBits) {
        return false;
    }
    if ($prefix === 0) {
        return true;
    }

    $bytes = intdiv($prefix, 8);
    $bits = $prefix % 8;

    if ($bytes > 0 && strncmp($ipBin, $netBin, $bytes) !== 0) {
        return false;
    }
    if ($bits === 0) {
        return true;
    }

    $mask = (0xFF << (8 - $bits)) & 0xFF;
    return ((ord($ipBin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask));
}

function security_is_trusted_proxy(string $remoteAddr): bool
{
    $remoteAddr = trim($remoteAddr);
    if ($remoteAddr === '' || !filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
        return false;
    }

    $remoteBin = @inet_pton($remoteAddr);
    if ($remoteBin === false) {
        return false;
    }

    foreach (security_trusted_proxies() as $rule) {
        if ($rule === '') {
            continue;
        }
        if (str_contains($rule, '/')) {
            if (security_ip_in_cidr($remoteAddr, $rule)) {
                return true;
            }
            continue;
        }

        $ruleBin = @inet_pton($rule);
        if ($ruleBin !== false && $ruleBin === $remoteBin) {
            return true;
        }
    }

    return false;
}

function security_client_ip(): string
{
    $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($remote === '' || !filter_var($remote, FILTER_VALIDATE_IP)) {
        return '0.0.0.0';
    }

    // ✅ Local/dev: map localhost loopback to LAN IP so allowlist matches your 192.168.x.x
    if (security_is_local_environment() && ($remote === '127.0.0.1' || $remote === '::1')) {
        $lan = gethostbyname(gethostname());
        if (is_string($lan) && filter_var($lan, FILTER_VALIDATE_IP) && $lan !== '127.0.0.1') {
            return $lan;
        }
        return $remote;
    }

    // Only honor forwarded headers when the direct peer is a trusted proxy.
    if (!security_is_trusted_proxy($remote)) {
        return $remote;
    }

    $candidates = [];
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $candidates[] = (string)$_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']);
        foreach ($forwarded as $rawIp) {
            $candidates[] = trim($rawIp);
        }
    }
    $candidates[] = $remote;

    foreach ($candidates as $ip) {
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return $remote;
}

function security_rate_limit_db(): ?mysqli
{
    static $db = null;
    static $resolved = false;

    if ($resolved) {
        return $db;
    }
    $resolved = true;

    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        $db = $GLOBALS['conn'];
    }
    return $db;
}

function security_ensure_rate_limit_table(mysqli $conn): bool
{
    static $ready = false;
    if ($ready) {
        return true;
    }

    $sql = "
        CREATE TABLE IF NOT EXISTS security_rate_limits (
            rate_key CHAR(64) NOT NULL,
            bucket_start INT UNSIGNED NOT NULL,
            attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
            last_ip VARCHAR(64) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (rate_key, bucket_start),
            KEY idx_security_rl_updated (updated_at),
            KEY idx_security_rl_bucket (bucket_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    if (!$conn->query($sql)) {
        return false;
    }

    $ready = true;
    return true;
}

function security_rate_limit_db_hit(string $key, int $limit, int $windowSeconds): ?bool
{
    $conn = security_rate_limit_db();
    if (!$conn instanceof mysqli) {
        return null;
    }
    if (!security_ensure_rate_limit_table($conn)) {
        return null;
    }

    $limit = max(1, $limit);
    $windowSeconds = max(1, $windowSeconds);
    $bucketStart = (int)(floor(time() / $windowSeconds) * $windowSeconds);
    $rateKey = hash('sha256', strtolower(trim($key)));
    $ip = security_client_ip();

    $stmtUp = $conn->prepare(
        "INSERT INTO security_rate_limits (rate_key, bucket_start, attempt_count, last_ip)
         VALUES (?, ?, 1, ?)
         ON DUPLICATE KEY UPDATE
            attempt_count = attempt_count + 1,
            last_ip = VALUES(last_ip),
            updated_at = CURRENT_TIMESTAMP"
    );
    if (!$stmtUp) {
        return null;
    }
    $stmtUp->bind_param('sis', $rateKey, $bucketStart, $ip);
    $ok = $stmtUp->execute();
    $stmtUp->close();
    if (!$ok) {
        return null;
    }

    $attemptCount = 0;
    $stmtGet = $conn->prepare(
        "SELECT attempt_count
         FROM security_rate_limits
         WHERE rate_key = ? AND bucket_start = ?
         LIMIT 1"
    );
    if (!$stmtGet) {
        return null;
    }
    $stmtGet->bind_param('si', $rateKey, $bucketStart);
    $stmtGet->execute();
    $res = $stmtGet->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmtGet->close();
    $attemptCount = (int)($row['attempt_count'] ?? 0);

    // Opportunistic cleanup of old windows.
    try {
        if (random_int(1, 100) === 1) {
            $threshold = time() - ($windowSeconds * 12);
            $stmtPrune = $conn->prepare("DELETE FROM security_rate_limits WHERE bucket_start < ?");
            if ($stmtPrune) {
                $stmtPrune->bind_param('i', $threshold);
                $stmtPrune->execute();
                $stmtPrune->close();
            }
        }
    } catch (Throwable $e) {
        // no-op
    }

    return $attemptCount <= $limit;
}

function security_rate_limit_session_hit(string $key, int $limit, int $windowSeconds): bool
{
    $now = time();
    $limit = max(1, $limit);
    $windowSeconds = max(1, $windowSeconds);

    if (!isset($_SESSION['rate_limits']) || !is_array($_SESSION['rate_limits'])) {
        $_SESSION['rate_limits'] = [];
    }

    $bucket = $_SESSION['rate_limits'][$key] ?? ['count' => 0, 'start' => $now];
    if (!is_array($bucket)) {
        $bucket = ['count' => 0, 'start' => $now];
    }

    if (($now - (int)($bucket['start'] ?? 0)) >= $windowSeconds) {
        $bucket = ['count' => 0, 'start' => $now];
    }

    $bucket['count'] = (int)($bucket['count'] ?? 0) + 1;
    $_SESSION['rate_limits'][$key] = $bucket;

    return (int)$bucket['count'] <= $limit;
}

function security_rate_limit_backend_hit(string $key, int $limit, int $windowSeconds): bool
{
    $dbResult = security_rate_limit_db_hit($key, $limit, $windowSeconds);
    if ($dbResult !== null) {
        return $dbResult;
    }
    return security_rate_limit_session_hit($key, $limit, $windowSeconds);
}

function rate_limit(string $key, int $limit, int $windowSeconds): bool
{
    $key = strtolower(trim($key));
    if ($key === '') {
        return true;
    }

    $limit = max(1, $limit);
    $windowSeconds = max(1, $windowSeconds);
    $ip = security_client_ip();

    // 1) Strict bucket: key + IP
    $okIp = security_rate_limit_backend_hit($key . '|ip:' . $ip, $limit, $windowSeconds);
    if (!$okIp) {
        return false;
    }

    // 2) Broader bucket: key only (helps against distributed brute-force).
    $globalLimit = max($limit + 5, (int)ceil($limit * 2));
    return security_rate_limit_backend_hit($key . '|global', $globalLimit, $windowSeconds);
}

function require_rate_limit(string $key, int $limit, int $windowSeconds, string $message = 'Too many attempts. Please try again later.'): void
{
    if (!rate_limit($key, $limit, $windowSeconds)) {
        http_response_code(429);
        exit($message);
    }
}

function security_build_csp(): string
{
    $directives = [
        "default-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
        "object-src 'none'",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdn.datatables.net https://cdnjs.cloudflare.com",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.datatables.net https://cdnjs.cloudflare.com https://fonts.googleapis.com",
        "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
        "img-src 'self' data: blob: https:",
        "connect-src 'self' https://api.brevo.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.datatables.net",
        "frame-src 'self'",
    ];

    // Apply browser-side mixed-content upgrade only on HTTPS pages.
    if (is_https()) {
        $directives[] = 'upgrade-insecure-requests';
        $directives[] = 'block-all-mixed-content';
    }

    return implode('; ', $directives);
}

function security_apply_headers(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }

    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Cross-Origin-Resource-Policy: same-site');
    header('Content-Security-Policy: ' . security_build_csp());
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function security_password_breach_count(string $password): ?int
{
    $password = (string)$password;
    if ($password === '') {
        return null;
    }

    $flag = strtolower(trim((string)(getenv('HIBP_CHECK') ?: '1')));
    if (in_array($flag, ['0', 'false', 'off', 'no'], true)) {
        return null;
    }

    static $cache = [];
    $sha1Hash = strtoupper(sha1($password));
    if (isset($cache[$sha1Hash])) {
        return $cache[$sha1Hash];
    }

    $prefix = substr($sha1Hash, 0, 5);
    $suffix = substr($sha1Hash, 5);
    $url = 'https://api.pwnedpasswords.com/range/' . $prefix;
    $response = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch !== false) {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 4);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Add-Padding: true',
                'User-Agent: city_enro-security/1.0',
            ]);
            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (!is_string($response) || $httpCode !== 200) {
                $cache[$sha1Hash] = null;
                return null;
            }
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 4,
                'header' => "Add-Padding: true\r\nUser-Agent: city_enro-security/1.0\r\n",
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if (!is_string($response) || $response === '') {
            $cache[$sha1Hash] = null;
            return null;
        }
    }

    $count = 0;
    $lines = explode("\n", str_replace("\r", '', (string)$response));
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, ':') === false) {
            continue;
        }
        [$hashPart, $hashCount] = explode(':', $line, 2);
        if (strtoupper(trim($hashPart)) === $suffix) {
            $count = max(0, (int)trim($hashCount));
            break;
        }
    }

    $cache[$sha1Hash] = $count;
    return $count;
}

function validate_password_policy(string $password, bool $checkBreached = true): array
{
    $password = (string)$password;

    if (strlen($password) < 10) {
        return ['ok' => false, 'error' => 'Password must be at least 10 characters long.'];
    }
    if (preg_match('/\s/', $password)) {
        return ['ok' => false, 'error' => 'Password must not contain spaces.'];
    }
    if (!preg_match('/[a-z]/', $password)) {
        return ['ok' => false, 'error' => 'Password must include at least one lowercase letter.'];
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return ['ok' => false, 'error' => 'Password must include at least one uppercase letter.'];
    }
    if (!preg_match('/\d/', $password)) {
        return ['ok' => false, 'error' => 'Password must include at least one number.'];
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return ['ok' => false, 'error' => 'Password must include at least one special character.'];
    }

    if ($checkBreached) {
        $breachCount = security_password_breach_count($password);
        if (is_int($breachCount) && $breachCount > 0) {
            return ['ok' => false, 'error' => 'This password appears in known data breaches. Please choose a different password.'];
        }
    }

    return ['ok' => true, 'error' => ''];
}

function security_parse_date_to_ymd(string $input): ?string
{
    $input = trim($input);
    if ($input === '' || strlen($input) > 40) {
        return null;
    }

    $formats = ['Y-m-d', 'Y/n/j', 'n/j/Y', 'm/d/Y', 'F j, Y', 'M j, Y'];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat('!' . $format, $input);
        $errors = DateTime::getLastErrors();
        $hasError = is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);
        if (!($dt instanceof DateTime) || $hasError) {
            continue;
        }

        $year = (int)$dt->format('Y');
        if ($year < 2000 || $year > 2100) {
            return null;
        }
        return $dt->format('Y-m-d');
    }

    $ts = strtotime($input);
    if ($ts === false) {
        return null;
    }
    $year = (int)date('Y', $ts);
    if ($year < 2000 || $year > 2100) {
        return null;
    }
    return date('Y-m-d', $ts);
}

function read_file_bytes(string $path, int $length, int $offset = 0): string
{
    if ($length <= 0) {
        return '';
    }
    $fh = @fopen($path, 'rb');
    if ($fh === false) {
        return '';
    }
    if ($offset > 0) {
        @fseek($fh, $offset);
    }
    $data = @fread($fh, $length);
    @fclose($fh);
    return is_string($data) ? $data : '';
}

function is_pdf_file(string $path): bool
{
    return read_file_bytes($path, 5) === '%PDF-';
}

function is_png_file(string $path): bool
{
    return read_file_bytes($path, 8) === "\x89PNG\r\n\x1a\n";
}

function is_jpeg_file(string $path): bool
{
    return substr(read_file_bytes($path, 3), 0, 2) === "\xFF\xD8";
}

function is_webp_file(string $path): bool
{
    $head = read_file_bytes($path, 12);
    return substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP';
}

function is_ole_compound_file(string $path): bool
{
    return read_file_bytes($path, 8) === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
}

function is_zip_container(string $path): bool
{
    return substr(read_file_bytes($path, 2), 0, 2) === 'PK';
}

function openxml_required_entries(string $ext): array
{
    $ext = strtolower(trim($ext));
    if ($ext === 'docx') {
        return ['[Content_Types].xml', 'word/document.xml'];
    }
    if ($ext === 'xlsx') {
        return ['[Content_Types].xml', 'xl/workbook.xml'];
    }
    if ($ext === 'pptx') {
        return ['[Content_Types].xml', 'ppt/presentation.xml'];
    }
    return [];
}

function is_openxml_office_file(string $path, string $ext): bool
{
    if (!is_zip_container($path)) {
        return false;
    }

    $required = openxml_required_entries($ext);
    if ($required === []) {
        return false;
    }

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        $opened = @$zip->open($path);
        if ($opened === true) {
            foreach ($required as $name) {
                if ($zip->locateName($name, ZipArchive::FL_NODIR) === false) {
                    $zip->close();
                    return false;
                }
            }
            $zip->close();
            return true;
        }
    }

    $blob = @file_get_contents($path);
    if (!is_string($blob) || $blob === '') {
        return false;
    }
    foreach ($required as $needle) {
        if (strpos($blob, $needle) === false) {
            return false;
        }
    }
    return true;
}

function validate_upload(array $file, array $allowedExt, array $allowedMime, int $maxBytes): array
{
    if (!isset($file['tmp_name'], $file['name'], $file['size'], $file['error'])) {
        return ['ok' => false, 'error' => 'Invalid upload payload.'];
    }
    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed.'];
    }
    $size = (int)$file['size'];
    if ($size <= 0 || $size > $maxBytes) {
        return ['ok' => false, 'error' => 'Invalid file size.'];
    }
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return ['ok' => false, 'error' => 'Invalid file type.'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)($finfo->file((string)$file['tmp_name']) ?: '');

    $mimeOk = in_array($mime, $allowedMime, true);
    if (!$mimeOk) {
        $tmp = (string)$file['tmp_name'];
        $signatureOk = false;

        if ($tmp !== '') {
            if ($ext === 'pdf') {
                $signatureOk = is_pdf_file($tmp);
            } elseif ($ext === 'png') {
                $signatureOk = is_png_file($tmp);
            } elseif ($ext === 'jpg' || $ext === 'jpeg') {
                $signatureOk = is_jpeg_file($tmp);
            } elseif ($ext === 'webp') {
                $signatureOk = is_webp_file($tmp);
            } elseif ($ext === 'doc') {
                $signatureOk = is_ole_compound_file($tmp);
            } elseif ($ext === 'docx' || $ext === 'xlsx' || $ext === 'pptx') {
                $signatureOk = is_openxml_office_file($tmp, $ext);
            }
        }

        if (!$signatureOk) {
            return ['ok' => false, 'error' => 'Invalid file content.'];
        }
    }
    return ['ok' => true, 'ext' => $ext, 'size' => $size, 'mime' => $mime];
}

function security_bootstrap(): void
{
    security_apply_runtime_ini();
    security_register_exception_handlers();
    security_require_encryption_keys_in_production();
    security_enforce_https();
    secure_session_start();
    security_apply_headers();
    security_enforce_csrf_for_state_changes();
}

security_bootstrap();

function security_download_secret(): string
{
    // Put this in .env as DOWNLOAD_SIGNING_KEY (recommended)
    $k = (string)(getenv('DOWNLOAD_SIGNING_KEY') ?: '');
    if ($k !== '') return $k;

    // fallback (dev only) - palitan mo ng long random
    return 'CHANGE_THIS_TO_LONG_RANDOM_SECRET_64CHARS_MIN';
}

function download_signed_url(string $storedPath, int $ttlSeconds = 600): string
{
    // Accept absolute like "/city_enro/uploads/iec/file.pdf" or relative "uploads/iec/file.pdf"
    $p = security_strip_legacy_base_path($storedPath);

    // must start with uploads/
    $p = ltrim($p, '/');
    if (!str_starts_with($p, 'uploads/')) {
        // if your DB stores only "iec/file.pdf", you can force it:
        // $p = 'uploads/' . ltrim($p, '/');
        return '#';
    }

    $exp = time() + max(60, $ttlSeconds);
    $data = $p . '|' . $exp;
    $sig  = hash_hmac('sha256', $data, security_download_secret());

    return url_with_base('download.php?path=' . rawurlencode($p) . '&exp=' . $exp . '&sig=' . $sig);
}
