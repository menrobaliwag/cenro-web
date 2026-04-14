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

if (!auth_flow_is_pending_auth_valid()) {
    clear_pending_auth_state();
    header('Location: ' . url_with_base('auth/index.php'));
    exit();
}

$pendingEmail = trim((string)$_SESSION['pending_email']);
$pendingUserId = (int)$_SESSION['pending_user_id'];
$otpPurpose = trim((string)($_SESSION['pending_otp_purpose'] ?? ''));
$requiresEmailOtp = (int)($_SESSION['pending_requires_email_otp'] ?? 0) === 1;
$emailVerified = (bool)($_SESSION['email_verified'] ?? false);

if ($pendingEmail === '' || $pendingUserId <= 0 || !$requiresEmailOtp || !auth_flow_otp_purpose_allowed($otpPurpose)) {
    clear_pending_auth_state();
    header('Location: ' . url_with_base('auth/index.php'));
    exit();
}

if ($emailVerified) {
    header('Location: ' . url_with_base('auth/authenticator.php'));
    exit();
}

if (!isset($_SESSION['resend_available_at'])) {
    $_SESSION['resend_available_at'] = time();
}
$remaining = max(0, (int)$_SESSION['resend_available_at'] - time());

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    require_csrf();
    require_rate_limit('otp:' . strtolower($pendingEmail), 8, 900);

    $otp = trim((string)($_POST['otp'] ?? ''));

    if (!preg_match('/^\d{6}$/', $otp)) {
        $_SESSION['error'] = 'Please enter a valid 6-digit OTP.';
        header('Location: ' . url_with_base('auth/otp.php'));
        exit();
    }

    $row = data_find_user_by_id($conn, $pendingUserId);
    if (!$row) {
        clear_pending_auth_state();
        $_SESSION['error'] = 'Session mismatch. Please log in again.';
        header('Location: ' . url_with_base('auth/index.php'));
        exit();
    }

    $resolvedEmail = data_normalize_email((string)($row['email'] ?? ''));
    if ($resolvedEmail === '' || $resolvedEmail !== data_normalize_email($pendingEmail)) {
        clear_pending_auth_state();
        $_SESSION['error'] = 'Session mismatch. Please log in again.';
        header('Location: ' . url_with_base('auth/index.php'));
        exit();
    }

    if (isset($row['status']) && (string)$row['status'] !== 'active') {
        clear_pending_auth_state();
        $_SESSION['error'] = 'Account is inactive.';
        header('Location: ' . url_with_base('auth/index.php'));
        exit();
    }

    $verify = auth_flow_verify_email_otp($conn, $pendingUserId, $otpPurpose, $otp);
    if (empty($verify['ok'])) {
        if ((int)($verify['remaining'] ?? 0) <= 0) {
            clear_pending_auth_state();
            $_SESSION['error'] = (string)($verify['error'] ?? 'Maximum OTP attempts reached. Please log in again.');
            header('Location: ' . url_with_base('auth/index.php'));
            exit();
        }
        $_SESSION['error'] = (string)($verify['error'] ?? 'Invalid OTP. Please try again.');
        header('Location: ' . url_with_base('auth/otp.php'));
        exit();
    }

    $_SESSION['pending_otp_verified'] = 1;
    $_SESSION['pending_otp_verified_at'] = time();
    auth_flow_mark_email_verified(true);

    $requiresTotp = (int)($_SESSION['pending_requires_totp_verify'] ?? 0) === 1;
    $requiresSetup = (int)($_SESSION['pending_requires_totp_setup'] ?? 0) === 1;
    if (!$requiresTotp && !$requiresSetup) {
        // Safety fallback: if no next factor is configured, reset and restart.
        clear_pending_auth_state();
        $_SESSION['error'] = 'Unable to verify OTP right now.';
        header('Location: ' . url_with_base('auth/index.php'));
        exit();
    }
    header('Location: ' . url_with_base('auth/authenticator.php'));
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>CENRO | OTP Verification</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= url_with_base('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
  <link rel="stylesheet" href="../dist/css/otp.css?v=20260211-1">
</head>

<body>
  <div class="auth-box">
    <div class="text-center">
      <span class="mini-badge"><i class="bi bi-shield-lock"></i> OTP Verification</span>
    </div>

    <h5 class="brand-title">Enter your code</h5>
    <div class="brand-sub">
      We sent a 6-digit code to<br>
      <strong><?= htmlspecialchars($pendingEmail, ENT_QUOTES, 'UTF-8') ?></strong>
    </div>

    <?php if (!empty($_SESSION['error'])): ?>
      <div class="alert alert-danger mb-3" id="errorAlert" role="alert">
        <?= htmlspecialchars((string)$_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['notice'])): ?>
      <div class="alert alert-success mb-3" id="noticeAlert" role="alert">
        <?= htmlspecialchars((string)$_SESSION['notice'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['notice']); ?>
      </div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <?= csrf_input(); ?>

      <div class="otp-inputs d-flex justify-content-center mb-3">
        <input type="tel" maxlength="1" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" required aria-label="Digit 1">
        <input type="tel" maxlength="1" inputmode="numeric" pattern="[0-9]*" required aria-label="Digit 2">
        <input type="tel" maxlength="1" inputmode="numeric" pattern="[0-9]*" required aria-label="Digit 3">
        <input type="tel" maxlength="1" inputmode="numeric" pattern="[0-9]*" required aria-label="Digit 4">
        <input type="tel" maxlength="1" inputmode="numeric" pattern="[0-9]*" required aria-label="Digit 5">
        <input type="tel" maxlength="1" inputmode="numeric" pattern="[0-9]*" required aria-label="Digit 6">
      </div>

      <input type="hidden" name="otp" id="otp-full">

      <div class="d-grid">
        <button type="submit" name="verify" class="btn custom-login-btn text-white" id="verifyBtn">
          <i class="bi bi-check2-circle me-2"></i>Verify OTP
        </button>
      </div>
    </form>

    <div class="text-center mt-3 small text-muted">
      Did not get the code?
      <button id="resendBtn" class="btn btn-link p-0 align-baseline" type="button">Resend OTP</button>
      <span id="countdown" class="text-danger ms-2"></span>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const inputs = document.querySelectorAll('.otp-inputs input');
    const otpHidden = document.getElementById('otp-full');
    const resendBtn = document.getElementById('resendBtn');
    const countdown = document.getElementById('countdown');

    function updateOtpHidden() {
      otpHidden.value = Array.from(inputs).map(input => input.value).join('');
    }

    function focusFirstEmpty() {
      for (const input of inputs) {
        if (!input.value) {
          input.focus();
          return;
        }
      }
      inputs[inputs.length - 1].focus();
    }

    inputs.forEach((input, i) => {
      input.addEventListener('input', () => {
        input.value = input.value.replace(/[^0-9]/g, '').slice(0, 1);
        updateOtpHidden();
        if (input.value && i < inputs.length - 1) {
          inputs[i + 1].focus();
        }
      });

      input.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !input.value && i > 0) {
          inputs[i - 1].focus();
        }
      });
    });

    document.querySelector('form').addEventListener('submit', (e) => {
      updateOtpHidden();
      if (otpHidden.value.length !== 6) {
        e.preventDefault();
        alert('Please enter the 6-digit OTP.');
        focusFirstEmpty();
      }
    });

    function startCountdown(seconds) {
      let timeLeft = Math.max(0, Number(seconds) || 0);
      resendBtn.disabled = timeLeft > 0;
      countdown.textContent = timeLeft > 0 ? `(${timeLeft}s)` : '';
      if (timeLeft <= 0) return;

      const timer = setInterval(() => {
        timeLeft -= 1;
        countdown.textContent = timeLeft > 0 ? `(${timeLeft}s)` : '';
        if (timeLeft <= 0) {
          clearInterval(timer);
          resendBtn.disabled = false;
        }
      }, 1000);
    }

    startCountdown(<?= (int)$remaining ?>);
    focusFirstEmpty();

    resendBtn.addEventListener('click', (e) => {
      e.preventDefault();
      if (resendBtn.disabled) return;
      resendBtn.disabled = true;
      countdown.textContent = 'Sending...';

      fetch('resend_otp.php', {
        method: 'POST',
        headers: {
          'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          location.reload();
          return;
        }
        if (data.cooldown) {
          startCountdown(data.remaining);
          return;
        }
        alert(data.error || 'Failed to resend OTP.');
        resendBtn.disabled = false;
        countdown.textContent = '';
      })
      .catch(() => {
        alert('Something went wrong.');
        resendBtn.disabled = false;
        countdown.textContent = '';
      });
    });
  </script>
</body>
</html>


