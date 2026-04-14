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

/**
 * Canonical public origin used for generating absolute outbound links (e.g., password reset emails).
 *
 * SECURITY: Do NOT derive this from request headers like HTTP_HOST.
 *
 * Configure via environment variable `APP_URL` (recommended), e.g.:
 * - https://enro.city.gov
 * - http://localhost:8080
 *
 * Note: Do not include a path (like /city_enro); code will append app paths explicitly.
 */
if (!defined('APP_URL')) {
    $appUrlEnv = getenv('APP_URL');
    $runtimeAppUrl = trim((string)($runtimeConfig['app_url'] ?? ''));
    $appUrl = ($appUrlEnv !== false && trim((string)$appUrlEnv) !== '') ? (string)$appUrlEnv : $runtimeAppUrl;
    define('APP_URL', rtrim($appUrl, '/'));
}

return (string)APP_URL;
