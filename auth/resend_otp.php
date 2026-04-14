<?php
require_once dirname(__DIR__) . '/includes/security_bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth_flow.php';
require_once dirname(__DIR__) . '/includes/auth_finalize.php';
secure_session_start();
require_once('../config/db.php');
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');

if (!auth_flow_is_pending_auth_valid()) {
    clear_pending_auth_state();
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "Unauthorized: no pending login session"]);
    exit;
}

$pendingEmail = trim((string)($_SESSION['pending_email'] ?? ''));
$pendingUserId = (int)($_SESSION['pending_user_id'] ?? 0);
$otpPurpose = trim((string)($_SESSION['pending_otp_purpose'] ?? ''));
$requiresEmailOtp = (int)($_SESSION['pending_requires_email_otp'] ?? 0) === 1;
$emailVerified = (bool)($_SESSION['email_verified'] ?? false);

if ($pendingEmail === '' || $pendingUserId <= 0 || !$requiresEmailOtp || $emailVerified || !auth_flow_otp_purpose_allowed($otpPurpose)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "OTP resend is not available for this session."]);
    exit;
}

require_csrf();
require_rate_limit('resend_otp:' . strtolower($pendingEmail), 3, 300);

$user = data_find_user_by_id($conn, $pendingUserId);
if (!$user) {
    clear_pending_auth_state();
    echo json_encode(["success" => false, "error" => "Account not found."]);
    exit;
}

$email = data_normalize_email((string)($user['email'] ?? $pendingEmail));
if ($email === '' || $email !== data_normalize_email($pendingEmail)) {
    clear_pending_auth_state();
    echo json_encode(["success" => false, "error" => "Session mismatch. Please log in again."]);
    exit;
}

$cooldownSeconds = 30;
$now = time();

if (!isset($_SESSION['resend_available_at'])) {
    $_SESSION['resend_available_at'] = $now;
}

if ($now < (int)$_SESSION['resend_available_at']) {
    echo json_encode([
        "success" => false,
        "cooldown" => true,
        "remaining" => (int)$_SESSION['resend_available_at'] - $now
    ]);
    exit;
}

$result = auth_flow_issue_and_send_email_otp($conn, $pendingUserId, $email, $otpPurpose);
if (empty($result['ok'])) {
    echo json_encode([
        "success" => false,
        "error" => (string)($result['error'] ?? 'Unable to send OTP.')
    ]);
    exit;
}

$_SESSION['notice'] = 'A new OTP has been sent to your email.';
$_SESSION['resend_available_at'] = $now + $cooldownSeconds;
echo json_encode(["success" => true]);
exit;
