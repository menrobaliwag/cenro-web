<?php
require_once dirname(__DIR__) . '/includes/security_bootstrap.php';
require_once dirname(__DIR__) . '/includes/role_utils.php';
require_once dirname(__DIR__) . '/includes/auth_flow.php';
require_once dirname(__DIR__) . '/includes/totp.php';
require_once dirname(__DIR__) . '/includes/auth_finalize.php';
secure_session_start();
include('../config/db.php');

date_default_timezone_set('Asia/Manila');

function login_role_name(mysqli $conn, int $roleId): string
{
    if ($roleId <= 0) {
        return 'guest';
    }

    $stmt = $conn->prepare('SELECT role_name FROM roles WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return 'guest';
    }

    $stmt->bind_param('i', $roleId);
    $stmt->execute();
    $res = $stmt->get_result();

    $roleName = 'guest';
    if ($res && $res->num_rows > 0) {
        $roleName = (string)($res->fetch_assoc()['role_name'] ?? 'guest');
    }
    $stmt->close();

    return normalize_role_key($roleName);
}

function login_role_permissions(mysqli $conn, int $roleId): array
{
    if ($roleId <= 0) {
        return [];
    }

    $stmt = $conn->prepare(
        'SELECT p.perm_key
         FROM role_permissions rp
         JOIN permissions p ON p.id = rp.permission_id
         WHERE rp.role_id = ?'
    );

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $roleId);
    $stmt->execute();
    $res = $stmt->get_result();

    $perms = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $perm = trim((string)($row['perm_key'] ?? ''));
        if ($perm !== '') {
            $perms[] = $perm;
        }
    }

    $stmt->close();
    return $perms;
}

if (auth_flow_is_fully_authenticated()) {
    header('Location: ' . url_with_base('auth/redirect.php'));
    exit();
}

if ((int)($_SESSION['user_id'] ?? 0) > 0 && !auth_flow_is_fully_authenticated()) {
    auth_flow_clear_authenticated_identity();
}

if (auth_flow_is_pending_auth_valid()) {
    $requiresEmailOtp = (int)($_SESSION['pending_requires_email_otp'] ?? 0) === 1;
    $emailVerified = (bool)($_SESSION['email_verified'] ?? false);
    if ($requiresEmailOtp && !$emailVerified) {
        header('Location: ' . url_with_base('auth/otp.php'));
        exit();
    }
    header('Location: ' . url_with_base('auth/authenticator.php'));
    exit();
}

if (isset($_SESSION['pending_user_id']) || isset($_SESSION['pending_email'])) {
    clear_pending_auth_state();
}

$email_cookie = $_COOKIE['remember_email'] ?? '';
$error = [];
$sessionExpiredMessage = '';
if (!empty($_SESSION['error'])) {
    $error[] = (string)$_SESSION['error'];
    unset($_SESSION['error']);
}
if ((string)($_GET['blocked'] ?? '') === 'ip') {
    $error[] = 'Access denied from this IP address for Head Admin account.';
}
if ((string)($_GET['session'] ?? '') === 'expired') {
    $sessionExpiredMessage = 'Your session expired. Please sign in again.';
}

if (isset($_POST['submit'])) {
    require_csrf();

    $email = trim((string)($_POST['email'] ?? ''));
    $inputPassword = (string)($_POST['password'] ?? '');
    $remember = isset($_POST['remember']) ? 1 : 0;

    if ($email === '' || $inputPassword === '') {
        $error[] = 'Email and password are required.';
    } elseif (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error[] = 'Incorrect email or password!';
    } elseif (strlen($inputPassword) > 255) {
        $error[] = 'Incorrect email or password!';
    } else {
        require_rate_limit('login:' . strtolower($email), 5, 900);

        $row = data_find_user_by_email($conn, $email);
        if ($row) {
            $resolvedEmail = data_normalize_email((string)($row['email'] ?? $email));
            if ($resolvedEmail === '') {
                $resolvedEmail = data_normalize_email($email);
            }

            if (isset($row['status']) && (string)$row['status'] !== 'active') {
                $error[] = 'Account is inactive.';
            } else {
                $storedPassword = (string)($row['password'] ?? '');
                $passwordVerified = password_verify($inputPassword, $storedPassword);

                // Backward compatibility: legacy MD5 passwords from old user_form schema.
                if (!$passwordVerified) {
                    $isLegacyMd5 = (strlen($storedPassword) === 32 && ctype_xdigit($storedPassword));
                    if ($isLegacyMd5 && hash_equals(strtolower($storedPassword), md5($inputPassword))) {
                        $passwordVerified = true;

                        // One-time transparent upgrade to password_hash().
                        $newHash = password_hash($inputPassword, PASSWORD_DEFAULT);
                        $uid = (int)($row['id'] ?? 0);
                        if ($newHash !== false && $uid > 0) {
                            $stmtRehash = $conn->prepare('UPDATE user_form SET password = ? WHERE id = ? LIMIT 1');
                            if ($stmtRehash) {
                                $stmtRehash->bind_param('si', $newHash, $uid);
                                $stmtRehash->execute();
                                $stmtRehash->close();
                            }
                        }
                    }
                }

                if (!$passwordVerified) {
                    $error[] = 'Incorrect email or password!';
                } else {
                    $roleId = (int)($row['role_id'] ?? 0);
                    $uid = (int)($row['id'] ?? 0);
                    $pendingEmail = $resolvedEmail !== '' ? $resolvedEmail : data_normalize_email($email);
                    $pendingName = (string)(!empty($row['name']) ? $row['name'] : ucfirst(explode('@', $pendingEmail)[0]));
                    $resolvedRole = login_role_name($conn, $roleId);

                    clear_pending_auth_state();

                    $_SESSION['pending_user_id'] = $uid;
                    $_SESSION['pending_email'] = $pendingEmail;
                    $_SESSION['pending_name'] = $pendingName;
                    $_SESSION['pending_role_id'] = $roleId;
                    $_SESSION['pending_barangay'] = (string)($row['barangay'] ?? '');
                    $_SESSION['pending_role'] = $resolvedRole;
                    $_SESSION['pending_role_name'] = $resolvedRole;
                    $_SESSION['pending_permissions'] = login_role_permissions($conn, $roleId);
                    $_SESSION['pending_remember'] = $remember;
                    $_SESSION['pending_auth_ts'] = time();
                    $_SESSION['pending_otp_verified'] = 0;
                    $_SESSION['pending_totp_required'] = 1;
                    $_SESSION['pending_requires_email_otp'] = 0;
                    $_SESSION['pending_requires_totp_verify'] = 1;
                    $_SESSION['pending_requires_totp_setup'] = 0;
                    $_SESSION['pending_trust_device_eligible'] = 0;
                    $_SESSION['pending_otp_purpose'] = '';
                    $_SESSION['pending_flow'] = 'trusted_login';
                    unset(
                        $_SESSION['pending_totp_verified_at'],
                        $_SESSION['pending_otp_verified_at'],
                        $_SESSION['pending_totp_setup_secret'],
                        $_SESSION['pending_totp_setup_ts']
                    );

                    auth_flow_set_state(true, false, false);

                    $totpConfig = totp_load_user_config($conn, $uid);
                    $hasTotp = !empty($totpConfig['enabled']) && trim((string)($totpConfig['secret'] ?? '')) !== '';

                    if (!$hasTotp) {
                        $_SESSION['pending_flow'] = 'first_time_setup';
                        $_SESSION['pending_otp_purpose'] = 'setup_2fa_first_time';
                        $_SESSION['pending_requires_email_otp'] = 1;
                        $_SESSION['pending_requires_totp_setup'] = 1;
                        $_SESSION['pending_totp_required'] = 1;
                        $_SESSION['pending_trust_device_eligible'] = 1;

                        $otpResult = auth_flow_issue_and_send_email_otp($conn, $uid, $pendingEmail, 'setup_2fa_first_time');
                        if (!empty($otpResult['ok'])) {
                            $_SESSION['resend_available_at'] = time() + 30;
                            header('Location: ' . url_with_base('auth/otp.php'));
                            exit();
                        }

                        clear_pending_auth_state();
                        $error[] = (string)($otpResult['error'] ?? 'Unable to send OTP right now. Please try again.');
                    } else {
                        $_SESSION['pending_requires_totp_verify'] = 1;
                        $_SESSION['pending_totp_required'] = 1;
                        $trustedDevice = auth_flow_is_trusted_device($conn, $uid);

                        if ($trustedDevice) {
                            auth_flow_mark_email_verified(true);
                            $_SESSION['pending_requires_email_otp'] = 0;
                            $_SESSION['pending_otp_purpose'] = '';
                            $_SESSION['pending_flow'] = 'trusted_login';
                            $_SESSION['pending_trust_device_eligible'] = 0;
                            header('Location: ' . url_with_base('auth/authenticator.php'));
                            exit();
                        }

                        $_SESSION['pending_flow'] = 'suspicious_login';
                        $_SESSION['pending_otp_purpose'] = 'suspicious_login';
                        $_SESSION['pending_requires_email_otp'] = 1;
                        $_SESSION['pending_trust_device_eligible'] = 1;

                        $otpResult = auth_flow_issue_and_send_email_otp($conn, $uid, $pendingEmail, 'suspicious_login');
                        if (!empty($otpResult['ok'])) {
                            $_SESSION['resend_available_at'] = time() + 30;
                            header('Location: ' . url_with_base('auth/otp.php'));
                            exit();
                        }

                        clear_pending_auth_state();
                        $error[] = (string)($otpResult['error'] ?? 'Unable to send OTP right now. Please try again.');
                    }
                }
            }
        } else {
            $error[] = 'Incorrect email or password!';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>CENRO | Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= url_with_base('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
  <link href="<?= url_with_base('dist/css/login_form.css?v=20260211-6') ?>" rel="stylesheet">
</head>

<body class="auth-login">
  <div class="auth-box">
    <div class="text-center mt-3 mb-2">
      <img src="<?= url_with_base('assets/images/logo.png') ?>" alt="CENRO logo" style="width: 118px; height: 110px; object-fit:contain;">
    </div>

    <h5 class="brand-title">Welcome back</h5>
    <div class="brand-sub">Sign in to continue to CENRO portal</div>

    <?php if ($sessionExpiredMessage !== ''): ?>
      <div id="sessionExpiredAlert" class="alert alert-warning mb-3" role="alert">
        <?= htmlspecialchars($sessionExpiredMessage, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger mb-3" role="alert">
        <?php foreach ($error as $err) echo '<div>' . htmlspecialchars((string)$err, ENT_QUOTES, 'UTF-8') . '</div>'; ?>
      </div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <?= csrf_input(); ?>
      <div class="input-group mb-3">
        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
        <input
          type="email"
          name="email"
          class="form-control"
          placeholder="Email"
          required
          autocomplete="email"
          value="<?= htmlspecialchars((string)$email_cookie, ENT_QUOTES, 'UTF-8') ?>"
        >
      </div>

      <div class="input-group mb-3 password-wrap">
        <span class="input-group-text"><i class="bi bi-lock"></i></span>
        <input
          type="password"
          name="password"
          class="form-control"
          id="passwordInput"
          placeholder="Password"
          required
          autocomplete="current-password"
        >
        <button
          type="button"
          class="btn p-0 border-0 bg-transparent"
          aria-label="Toggle password visibility"
          onclick="togglePassword()"
          style="position:absolute; right:10px; top:50%; transform:translateY(-50%);"
        >
          <i class="bi bi-eye toggle-password" id="toggleIcon"></i>
        </button>
      </div>

      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="form-check">
          <input
            class="form-check-input"
            type="checkbox"
            name="remember"
            id="rememberMe"
            <?= !empty($email_cookie) ? 'checked' : '' ?>
          >
          <label class="form-check-label" for="rememberMe">Remember me</label>
        </div>

        <a href="forgot_password.php" class="text-decoration-none small">Forgot password?</a>
      </div>

      <div class="d-grid">
        <button class="btn custom-login-btn text-white" name="submit" type="submit">
          <i class="bi bi-box-arrow-in-right me-2"></i> LOG IN
        </button>
      </div>
      <div class="auth-security-note" role="note">
        <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Step 2: Authenticator verification (email OTP only for setup/new device).
      </div>
    </form>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function togglePassword() {
      const input = document.getElementById('passwordInput');
      const icon = document.getElementById('toggleIcon');
      const isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';
      icon.classList.toggle('bi-eye', !isHidden);
      icon.classList.toggle('bi-eye-slash', isHidden);
    }

    (function () {
      const alertEl = document.getElementById('sessionExpiredAlert');
      if (!alertEl) return;

      try {
        const url = new URL(window.location.href);
        if (url.searchParams.get('session') === 'expired') {
          url.searchParams.delete('session');
          const next = url.pathname + (url.search ? url.search : '') + url.hash;
          window.history.replaceState({}, '', next);
        }
      } catch (e) {
        // no-op
      }

      document.querySelectorAll('.alert').forEach(function (el) {
        window.setTimeout(function () {
          if (!el.parentNode) return;
          el.style.transition = 'opacity .3s ease, transform .3s ease';
          el.style.opacity = '0';
          el.style.transform = 'translateY(-4px)';
          window.setTimeout(function () {
            if (el.parentNode) {
              el.parentNode.removeChild(el);
            }
          }, 320);
        }, 2200);
      });
    })();
  </script>
</body>
</html>


