<?php
declare(strict_types=1);

/** @var array<string, mixed> $runtimeConfig */
$runtimeConfig = [];
$runtimeConfigPath = __DIR__ . '/runtime.php';
if (is_file($runtimeConfigPath)) {
    $loadedRuntimeConfig = require $runtimeConfigPath;
    if (is_array($loadedRuntimeConfig)) {
        $runtimeConfig = $loadedRuntimeConfig;
    }
}

$runtimeDbConfig = [];
if (isset($runtimeConfig['db']) && is_array($runtimeConfig['db'])) {
    $runtimeDbConfig = $runtimeConfig['db'];
}

$hostEnv = getenv('DB_HOST');
$userEnv = getenv('DB_USER');
$passEnv = getenv('DB_PASS');
$dbNameEnv = getenv('DB_NAME');
$portEnv = getenv('DB_PORT');

$runtimeHost = trim((string)($runtimeDbConfig['host'] ?? ''));
$runtimeUser = trim((string)($runtimeDbConfig['user'] ?? ''));
$runtimePass = array_key_exists('pass', $runtimeDbConfig) ? (string)$runtimeDbConfig['pass'] : null;
$runtimeDbName = trim((string)($runtimeDbConfig['name'] ?? ''));
$runtimePortRaw = (string)($runtimeDbConfig['port'] ?? '3307');
$portRaw = (string)(($portEnv !== false && $portEnv !== '') ? $portEnv : $runtimePortRaw);

$host = (string)(($hostEnv !== false && trim((string)$hostEnv) !== '') ? $hostEnv : ($runtimeHost !== '' ? $runtimeHost : '127.0.0.1'));
$username = (string)(($userEnv !== false && trim((string)$userEnv) !== '') ? $userEnv : ($runtimeUser !== '' ? $runtimeUser : 'root'));
$password = ($passEnv !== false) ? (string)$passEnv : (($runtimePass !== null) ? $runtimePass : '');
$dbname = (string)(($dbNameEnv !== false && trim((string)$dbNameEnv) !== '') ? $dbNameEnv : ($runtimeDbName !== '' ? $runtimeDbName : 'newcityenro'));
$port = ctype_digit($portRaw) ? (int)$portRaw : 3307;

$appEnv = strtolower(trim((string)(getenv('APP_ENV') ?: 'local')));
if ($appEnv === 'production') {
    if ($username === '' || $dbname === '' || !ctype_digit($portRaw)) {
        error_log('Blocked DB config in production: DB configuration is incomplete.');
        http_response_code(500);
        exit('Database configuration error.');
    }
    if ($username === 'root' || $password === '') {
        error_log('Blocked insecure DB configuration in production (root user or empty DB password).');
        http_response_code(500);
        exit('Database configuration error.');
    }
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @mysqli_connect($host, $username, $password, $dbname, $port);
if (!$conn) {
    error_log('Database connection failed: ' . mysqli_connect_error());
    http_response_code(500);
    exit('Database connection error.');
}

@mysqli_set_charset($conn, 'utf8mb4');

date_default_timezone_set('Asia/Manila');
@mysqli_query($conn, "SET time_zone = '+08:00'");
