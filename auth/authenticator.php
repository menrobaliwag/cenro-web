<?php
require_once dirname(__DIR__) . '/includes/security_bootstrap.php';
require_once dirname(__DIR__) . '/includes/role_utils.php';
require_once dirname(__DIR__) . '/includes/session_activity_audit.php';
require_once dirname(__DIR__) . '/includes/totp.php';
require_once dirname(__DIR__) . '/includes/auth_flow.php';
require_once dirname(__DIR__) . '/includes/auth_finalize.php';
secure_session_start();
include('../config/db.php');
date_default_timezone_set('Asia/Manila');

if (auth_flow_is_fully_authenticated()) {
    header('Location: ' . url_with_base('auth/redirect.php'));
    exit();
}

if (!auth_flow_is_pending_auth_valid()) {
    clear_pending_auth_state();
    header('Location: ' . url_with_base('auth/index.php'));
    exit();
}

$pendingUserId = (int)($_SESSION['pending_user_id'] ?? 0);
$pendingEmail = trim((string)($_SESSION['pending_email'] ?? ''));
$requiresEmailOtp = (int)($_SESSION['pending_requires_email_otp'] ?? 0) === 1;
$emailVerified = (bool)($_SESSION['email_verified'] ?? false);
$requiresSetup = (int)($_SESSION['pending_requires_totp_setup'] ?? 0) === 1;
$requiresTotpVerify = (int)($_SESSION['pending_requires_totp_verify'] ?? 0) === 1;
$pendingFlow = trim((string)($_SESSION['pending_flow'] ?? ''));

if ($pendingUserId <= 0 || $pendingEmail === '') {
    clear_pending_auth_state();
    header('Location: ' . url_with_base('auth/index.php'));
    exit();
}

if (!(bool)($_SESSION['password_verified'] ?? false)) {
    clear_pending_auth_state();
    header('Location: ' . url_with_base('auth/index.php'));
    exit();
}

if ($requiresEmailOtp && !$emailVerified) {
    header('Location: ' . url_with_base('auth/otp.php'));
    exit();
}
if (!$requiresEmailOtp) {
    auth_flow_mark_email_verified(true);
}

if (!$requiresSetup && !$requiresTotpVerify) {
    clear_pending_auth_state();
    header('Location: ' . url_with_base('auth/index.php'));
    exit();
}

$userRow = data_find_user_by_id($conn, $pendingUserId);
if (!$userRow) {
    clear_pending_auth_state();
    header('Location: ' . url_with_base('auth/index.php'));
    exit();
}

$resolvedEmail = data_normalize_email((string)($userRow['email'] ?? ''));
if ($resolvedEmail === '' || $resolvedEmail !== data_normalize_email($pendingEmail)) {
    clear_pending_auth_state();
    header('Location: ' . url_with_base('auth/index.php'));
    exit();
}

if ((string)($userRow['status'] ?? '') !== 'active') {
    clear_pending_auth_state();
    header('Location: ' . url_with_base('auth/index.php'));
    exit();
}

$totpConfig = totp_load_user_config($conn, $pendingUserId);
$storedTotpSecret = trim((string)($totpConfig['secret'] ?? ''));
$totpEnabled = !empty($totpConfig['enabled']) && $storedTotpSecret !== '';

// Safety fallback if account has TOTP disabled while expecting a verify step.
if ($requiresTotpVerify && !$requiresSetup && !$totpEnabled) {
    $requiresSetup = true;
    $requiresTotpVerify = false;
    $_SESSION['pending_requires_totp_setup'] = 1;
    $_SESSION['pending_requires_totp_verify'] = 0;
}

$setupSecret = '';
$totpProvisioningUri = '';
if ($requiresSetup) {
    $setupSecret = trim((string)($_SESSION['pending_totp_setup_secret'] ?? ''));
    $setupTs = (int)($_SESSION['pending_totp_setup_ts'] ?? 0);
    if ($setupSecret === '' || $setupTs <= 0 || (time() - $setupTs) > 900) {
        $setupSecret = totp_generate_secret(20);
        $_SESSION['pending_totp_setup_secret'] = $setupSecret;
        $_SESSION['pending_totp_setup_ts'] = time();
    }

    $issuer = 'CITY ENRO';
    $account = $resolvedEmail !== '' ? $resolvedEmail : ('user' . $pendingUserId . '@city-enro.local');
    $totpProvisioningUri = totp_build_provisioning_uri($issuer, $account, $setupSecret);
}

$error = '';
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_totp'])) {
    require_csrf();
    require_rate_limit('totp:' . strtolower($pendingEmail), 8, 900);

    $input = trim((string)($_POST['totp_code'] ?? ''));
    if ($requiresSetup) {
        if (!preg_match('/^\d{6}$/', $input)) {
            $error = 'Please enter a valid 6-digit authenticator code.';
        } elseif (!totp_verify_code($setupSecret, $input, 1)) {
            $error = 'Invalid authenticator code.';
        } elseif (!totp_enable_for_user($conn, $pendingUserId, $setupSecret)) {
            $error = 'Unable to enable authenticator right now.';
        } else {
            $backupCodes = auth_flow_replace_backup_codes($conn, $pendingUserId, 8);
            if ($backupCodes !== []) {
                auth_flow_send_backup_codes_email($resolvedEmail, $backupCodes);
                $notice = 'Authenticator enabled. Backup codes were sent to your email.';
            } else {
                $notice = 'Authenticator enabled. Backup code generation failed; contact administrator.';
            }

            unset($_SESSION['pending_totp_setup_secret'], $_SESSION['pending_totp_setup_ts']);
            unset($_SESSION['pending_totp_failures']);
            $_SESSION['pending_requires_totp_setup'] = 0;
            $_SESSION['pending_requires_totp_verify'] = 1;
            $_SESSION['pending_totp_required'] = 1;
            auth_flow_mark_twofa_verified(true);
            $_SESSION['pending_totp_verified_at'] = time();
            finalize_login_and_redirect($conn, $userRow, 'OTP+TOTP_SETUP');
        }
    } else {
        $verifiedByTotp = false;
        $verifiedByBackup = false;

        if (preg_match('/^\d{6}$/', $input)) {
            $verifiedByTotp = totp_verify_code($storedTotpSecret, $input, 1);
        } else {
            $verifiedByBackup = auth_flow_verify_backup_code($conn, $pendingUserId, $input);
        }

        if (!$verifiedByTotp && !$verifiedByBackup) {
            $error = 'Invalid authenticator or backup code.';
        } else {
            if ($verifiedByTotp) {
                totp_mark_used_for_user($conn, $pendingUserId);
            }
            unset($_SESSION['pending_totp_failures']);
            auth_flow_mark_twofa_verified(true);
            $_SESSION['pending_totp_verified_at'] = time();
            $method = $verifiedByBackup ? 'BACKUP_CODE' : 'TOTP';
            if ($pendingFlow === 'suspicious_login' && !$verifiedByBackup) {
                $method = 'OTP+TOTP';
            }
            finalize_login_and_redirect($conn, $userRow, $method);
        }
    }

    if ($error !== '') {
        $failures = (int)($_SESSION['pending_totp_failures'] ?? 0) + 1;
        $_SESSION['pending_totp_failures'] = $failures;
        if ($failures >= 5) {
            clear_pending_auth_state();
            $_SESSION['error'] = 'Too many invalid authenticator attempts. Please log in again.';
            header('Location: ' . url_with_base('auth/index.php'));
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>CENRO | Authenticator Verification</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= url_with_base('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
  <link href="<?= url_with_base('dist/css/login_form.css?v=20260211-6') ?>" rel="stylesheet">
</head>

<body class="auth-login">
  <div class="auth-box">
    <div class="text-center mt-3 mb-2">
      <img src="<?= url_with_base('assets/images/logo.png') ?>" alt="CENRO logo" style="width: 110px; height: 102px; object-fit:contain;">
    </div>

    <?php if ($requiresSetup): ?>
      <h5 class="brand-title">Set Up Authenticator</h5>
      <div class="brand-sub">Scan the QR code, then enter the 6-digit code from your authenticator app.</div>
    <?php else: ?>
      <h5 class="brand-title">Authenticator Verification</h5>
      <div class="brand-sub">Enter your 6-digit authenticator code (or a backup code).</div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
      <div class="alert alert-danger mb-3" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($notice !== ''): ?>
      <div class="alert alert-success mb-3" role="alert"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($requiresSetup): ?>
      <div class="text-center mb-3">
        <?php if ($totpProvisioningUri !== ''): ?>
          <div
            id="totp-qr"
            data-otpauth="<?= htmlspecialchars($totpProvisioningUri, ENT_QUOTES, 'UTF-8') ?>"
            style="width:180px;height:180px;margin:0 auto;"
          ></div>
          <script src="<?= url_with_base('assets/vendor/qrcodejs/qrcode.min.js') ?>"></script>
          <script>
            (function () {
              const el = document.getElementById('totp-qr');
              if (!el) return;
              const data = el.getAttribute('data-otpauth') || '';
              if (!data || typeof QRCode === 'undefined') return;
              el.innerHTML = '';
              new QRCode(el, { text: data, width: 180, height: 180, correctLevel: QRCode.CorrectLevel.M });
              el.removeAttribute('data-otpauth');
            })();
          </script>
        <?php endif; ?>
        <div class="small text-muted mt-2">Manual key:</div>
        <code><?= htmlspecialchars($setupSecret, ENT_QUOTES, 'UTF-8') ?></code>
      </div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <?= csrf_input(); ?>
      <div class="input-group mb-3">
        <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
        <input
          type="text"
          name="totp_code"
          class="form-control"
          maxlength="20"
          inputmode="text"
          placeholder="<?= $requiresSetup ? '6-digit code' : '6-digit code or backup code' ?>"
          autocomplete="one-time-code"
          required
        >
      </div>

      <div class="d-grid">
        <button class="btn custom-login-btn text-white" name="verify_totp" type="submit">
          <i class="bi bi-check2-circle me-2"></i> Verify Code
        </button>
      </div>
    </form>

    <div class="text-center mt-3 small text-muted">
      Lost access to Authenticator?
      <br>
      Use a backup code or ask Head Admin to reset your 2FA.
    </div>
  </div>
</body>
</html>

