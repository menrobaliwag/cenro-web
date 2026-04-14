<?php
/** @var array<string, mixed> $runtimeConfig */
$runtimeConfig = [];
$runtimeConfigPath = __DIR__ . '/runtime.php';
if (is_file($runtimeConfigPath)) {
    $loadedRuntimeConfig = require $runtimeConfigPath;
    if (is_array($loadedRuntimeConfig)) {
        $runtimeConfig = $loadedRuntimeConfig;
    }
}

$runtimeBrevoConfig = [];
if (isset($runtimeConfig['brevo']) && is_array($runtimeConfig['brevo'])) {
    $runtimeBrevoConfig = $runtimeConfig['brevo'];
}

$runtimeMailConfig = [];
if (isset($runtimeConfig['mail']) && is_array($runtimeConfig['mail'])) {
    $runtimeMailConfig = $runtimeConfig['mail'];
}

$smtpHost = getenv('SMTP_HOST');
if ($smtpHost === false || trim((string)$smtpHost) === '') {
    $smtpHost = (string)($runtimeMailConfig['host'] ?? ($runtimeBrevoConfig !== [] ? 'smtp-relay.brevo.com' : ''));
}

$smtpPortRaw = getenv('SMTP_PORT');
if ($smtpPortRaw === false || trim((string)$smtpPortRaw) === '') {
    $smtpPortRaw = (string)($runtimeMailConfig['port'] ?? ($runtimeBrevoConfig !== [] ? '587' : '587'));
}

$smtpSecure = getenv('SMTP_SECURE');
if ($smtpSecure === false || trim((string)$smtpSecure) === '') {
    $smtpSecure = (string)($runtimeMailConfig['secure'] ?? 'tls');
}

$smtpAuthRaw = getenv('SMTP_AUTH');
if ($smtpAuthRaw === false || trim((string)$smtpAuthRaw) === '') {
    $smtpAuthRaw = (string)($runtimeMailConfig['auth'] ?? '1');
}

$smtpUser = getenv('SMTP_USER');
if ($smtpUser === false || trim((string)$smtpUser) === '') {
    $smtpUser = (string)($runtimeMailConfig['user'] ?? ($runtimeBrevoConfig['smtp_user'] ?? ''));
}

$smtpPass = getenv('SMTP_PASS');
if ($smtpPass === false || trim((string)$smtpPass) === '') {
    $smtpPass = (string)($runtimeMailConfig['pass'] ?? ($runtimeBrevoConfig['smtp_pass'] ?? ''));
}

$smtpSender = getenv('SMTP_SENDER');
if ($smtpSender === false || trim((string)$smtpSender) === '') {
    $smtpSender = (string)($runtimeMailConfig['sender'] ?? ($runtimeBrevoConfig['sender'] ?? 'no-reply@example.com'));
}

$smtpSenderName = getenv('SMTP_SENDER_NAME');
if ($smtpSenderName === false || trim((string)$smtpSenderName) === '') {
    $smtpSenderName = (string)($runtimeMailConfig['sender_name'] ?? ($runtimeBrevoConfig['sender_name'] ?? 'CENRO Support'));
}

define('SMTP_HOST', trim((string)$smtpHost));
define('SMTP_PORT', ctype_digit(trim((string)$smtpPortRaw)) ? (int)$smtpPortRaw : 587);
define('SMTP_SECURE', strtolower(trim((string)$smtpSecure)));
define('SMTP_AUTH', !in_array(strtolower(trim((string)$smtpAuthRaw)), ['0', 'false', 'off', 'no'], true));
define('SMTP_USER', trim((string)$smtpUser));
define('SMTP_PASS', (string)$smtpPass);
define('SMTP_SENDER', trim((string)$smtpSender));
define('SMTP_SENDER_NAME', trim((string)$smtpSenderName));

define('BREVO_API_KEY', getenv('BREVO_API_KEY') ?: (string)($runtimeBrevoConfig['api_key'] ?? ''));
define('BREVO_SMTP_USER', getenv('BREVO_SMTP_USER') ?: SMTP_USER);
define('BREVO_SMTP_PASS', getenv('BREVO_SMTP_PASS') ?: SMTP_PASS);
define('BREVO_SMTP_SENDER', getenv('BREVO_SMTP_SENDER') ?: SMTP_SENDER);
define('BREVO_SMTP_SENDER_NAME', getenv('BREVO_SMTP_SENDER_NAME') ?: SMTP_SENDER_NAME);
