<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

security_bootstrap();

/* =========================================================
   AUTH CHECK (compatible with your auth system)
========================================================= */
$ok = false;

if (function_exists('auth_flow_is_fully_authenticated')) {
    $ok = auth_flow_is_fully_authenticated();
} elseif (!empty($_SESSION['fully_authenticated'])) {
    $ok = ($_SESSION['fully_authenticated'] === true);
} elseif (!empty($_SESSION['user_id']) || !empty($_SESSION['id'])) {
    $ok = true; // fallback logged-in
}

if (!$ok) {
    http_response_code(401);
    exit('Unauthorized');
}

/* =========================================================
   READ & VALIDATE SIGNED URL
========================================================= */
$path = ltrim((string)($_GET['path'] ?? ''), '/');
$exp  = (int)($_GET['exp'] ?? 0);
$sig  = (string)($_GET['sig'] ?? '');

if ($path === '' || $exp < time() || $sig === '') {
    http_response_code(400);
    exit('Invalid link');
}

/* signature verify */
$data = $path . '|' . $exp;
$good = hash_hmac('sha256', $data, security_download_secret());

if (!hash_equals($good, $sig)) {
    http_response_code(403);
    exit('Forbidden');
}

/* =========================================================
   BASIC PERMISSION GATE
========================================================= */
$roleKey = rbac_current_role();
$isHeadAdmin = is_super_role($roleKey);
$myBarangay = trim((string)($_SESSION['barangay'] ?? ''));

/* IEC files must have IEC permission */
if (str_starts_with($path, 'uploads/iec/')) {
    requirePermission('iec.manage');
}

/* =========================================================
   SAFE FILE RESOLUTION
========================================================= */
$uploadsRoot = realpath(__DIR__ . '/uploads');
if ($uploadsRoot === false) {
    http_response_code(500);
    exit('Storage missing');
}

/* block traversal */
if (str_contains($path, '..') || str_contains($path, "\0")) {
    http_response_code(400);
    exit('Bad path');
}

$full = realpath(__DIR__ . '/' . $path);

if ($full === false || !str_starts_with($full, $uploadsRoot)) {
    http_response_code(404);
    exit('Not found');
}

if (!is_file($full) || !is_readable($full)) {
    http_response_code(404);
    exit('Not found');
}

/* =========================================================
   STREAM FILE SECURELY
========================================================= */
$filename = basename($full);

/* detect mime */
$mime = 'application/octet-stream';
if (function_exists('mime_content_type')) {
    $m = @mime_content_type($full);
    if (is_string($m) && $m !== '') {
        $mime = $m;
    }
}

/* headers */
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($full));
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/* inline preview for pdf/images */
$disp = (preg_match('~^(application/pdf|image/)~', $mime)) ? 'inline' : 'attachment';
header('Content-Disposition: ' . $disp . '; filename="' . addslashes($filename) . '"');

/* output */
readfile($full);
exit;
