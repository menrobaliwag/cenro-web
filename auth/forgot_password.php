<?php
require_once dirname(__DIR__) . '/includes/security_bootstrap.php';
secure_session_start();
include('../config/db.php');
require_once('../config/secrets.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../sendphpmailer/PHPMailer.php';
require '../sendphpmailer/SMTP.php';
require '../sendphpmailer/Exception.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['email'])) {
    require_csrf();
    $email = trim((string)($_POST['email'] ?? ''));
    if (strlen($email) > 190) {
        $email = substr($email, 0, 190);
    }
    require_rate_limit('forgot:' . strtolower($email), 5, 900);

    $user = data_find_user_by_email($conn, $email);
    if ($user) {
        $userId = (int)($user['id'] ?? 0);
        $resolvedEmail = data_normalize_email((string)($user['email'] ?? $email));
        $userStatus = strtolower(trim((string)($user['status'] ?? 'active')));
        if ($userId > 0 && $resolvedEmail !== '') {
        if ($userStatus !== 'active') {
            error_log('Password reset blocked for inactive account id=' . $userId . ' email=' . $resolvedEmail);
        } else {
        // Generate token + expiry
        $token = bin2hex(random_bytes(32)); // 64 characters
        $tokenHash = hash('sha256', $token);
        $expiry = date("Y-m-d H:i:s", strtotime("+30 minutes"));

        // Save token to DB
        $stmtUp = $conn->prepare("UPDATE user_form SET reset_token = ?, token_expiry = ? WHERE id = ? LIMIT 1");
        $stmtUp->bind_param("ssi", $tokenHash, $expiry, $userId);
        $updateOk = $stmtUp->execute();
        $stmtUp->close();

        if ($updateOk) {
            // Build reset link with token
            // Build reset link from trusted origin, not request host.
            $origin = (string)(defined('APP_URL') ? APP_URL : '');
            if ($origin === '') {
                $scheme = is_https() ? 'https' : 'http';
                $host = security_host_without_port((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
                $origin = $scheme . '://' . $host;
            }
            $reset_link = rtrim($origin, '/') . url_with_base('auth/reset_password.php?token=' . urlencode($token));

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = SMTP_AUTH;
                if (SMTP_HOST === '') {
                    throw new Exception("SMTP host is not configured.");
                }
                if (SMTP_AUTH && (SMTP_USER === '' || SMTP_PASS === '')) {
                    throw new Exception("SMTP credentials are not configured.");
                }
                $mail->Username = SMTP_USER;
                $mail->Password = SMTP_PASS;
                if (in_array(SMTP_SECURE, ['tls', 'starttls'], true)) {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } elseif (in_array(SMTP_SECURE, ['ssl', 'smtps'], true)) {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } else {
                    $mail->SMTPSecure = '';
                    $mail->SMTPAutoTLS = false;
                }
                $mail->Port = SMTP_PORT;
                $mail->Timeout = 10;

                // Sender/Receiver
                $mail->setFrom(SMTP_SENDER, SMTP_SENDER_NAME);
                $mail->addAddress($resolvedEmail);

                // Email body
                $mail->isHTML(true);
                $mail->Subject = 'Reset Your Password';

                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; border: 1px solid #ddd; padding: 20px; border-radius: 10px; background-color: #f9f9f9;'>
                        <h2 style='color: #333;'>Password Reset Request</h2>
                        <p style='font-size: 15px; color: #555;'>We received a request to reset your password. Click the button below to proceed:</p>
                        <p style='text-align: center; margin: 30px 0;'>
                            <a href='$reset_link' style='padding: 12px 25px; background-color: #007bff; color: #fff; text-decoration: none; border-radius: 5px; font-weight: bold;'>Reset Password</a>
                        </p>
                        <p style='font-size: 14px; color: #777;'>This link will expire in <strong>30 minutes</strong> for security reasons.</p>
                        <p style='font-size: 14px; color: #777;'>If you did not request a password reset, please ignore this email. No changes will be made to your account.</p>
                        <hr style='margin: 30px 0;'>
                        <p style='font-size: 14px; color: #999;'>Thank you,<br>The Cenro Team</p>
                    </div>
                ";

                $mail->send();
                $_SESSION['notice'] = "If an account exists for that email, a reset link has been sent.";
            } catch (Exception $e) {
                error_log('Password reset email send failed for user #' . $userId . ' (' . $resolvedEmail . '): ' . $mail->ErrorInfo);
                $_SESSION['notice'] = "If an account exists for that email, a reset link has been sent.";
            }
        } else {
            error_log('Password reset token update failed for user #' . $userId . ' (' . $resolvedEmail . '): ' . mysqli_error($conn));
        }
        }
        }
    } else {
        error_log('Password reset requested for unknown email: ' . data_normalize_email($email));
    }
    if (empty($_SESSION['notice'])) {
        $_SESSION['notice'] = "If an account exists for that email, a reset link has been sent.";
    }

    // Redirect back to this page (NOT reset_password.php)
    header("Location: forgot_password.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>CENRO | Forgot Password</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= url_with_base('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
  <link href="../dist/css/forgot_password.css" rel="stylesheet">
  
</head>

<body>
  <div class="auth-box">

    <div class="text-center">
      <span class="mini-badge"><i class="bi bi-envelope-paper"></i> Password Reset</span>
    </div>

    <h5 class="brand-title">Forgot your password?</h5>
    <div class="brand-sub">Enter your email to receive a reset link.</div>

    <?php if (!empty($_SESSION['notice'])): ?>
      <div class="text-center mb-3" id="success-msg">
        <div class="checkmark-container">
          <svg class="checkmark" viewBox="0 0 52 52" xmlns="http://www.w3.org/2000/svg">
            <path d="M14 27l7 7 17-17" />
          </svg>
        </div>
        <p class="text-success fw-semibold mt-2">
          <?= htmlspecialchars($_SESSION['notice']); unset($_SESSION['notice']); ?>
        </p>
      </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
      <div class="alert alert-danger mb-3" role="alert" id="msg">
        <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
      </div>
    <?php endif; ?>

    <form method="POST" id="forgot-form" novalidate>
      <?= csrf_input(); ?>
      <div class="input-group mb-3">
        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
        <input
          type="email"
          name="email"
          id="email"
          class="form-control"
          placeholder="Email address"
          required
          autofocus
          autocomplete="email"
        >
      </div>

      <button type="submit" class="custom-green-btn" id="submit-btn">
        <span id="btn-text"><i class="bi bi-send me-2"></i>Send Reset Link</span>
        <span class="spinner-border spinner-border-sm text-light d-none" role="status" id="btn-spinner"></span>
      </button>

      <div class="text-center mt-3">
        <a href="index.php" class="text-decoration-none small">
          <i class="bi bi-arrow-left"></i> Back to Login
        </a>
      </div>
    </form>
  </div>

<script>
  // Auto-hide success or error messages
  setTimeout(() => {
    const success = document.getElementById("success-msg");
    const error = document.getElementById("msg");
    if (success) {
      success.classList.add("fade-out");
      setTimeout(() => success.style.display = "none", 500);
    }
    if (error) {
      error.classList.add("fade-out");
      setTimeout(() => error.style.display = "none", 500);
    }
  }, 3000);

  // Show spinner and disable button on form submit
  const form = document.getElementById("forgot-form");
  const btn = document.getElementById("submit-btn");
  const btnText = document.getElementById("btn-text");
  const btnSpinner = document.getElementById("btn-spinner");

  form.addEventListener("submit", function () {
    btn.disabled = true;
    btnText.classList.add("d-none");
    btnSpinner.classList.remove("d-none");
  });

  // ✅ Redirect to index.php after 5s if successful
  if (document.getElementById("success-msg")) {
    setTimeout(() => {
      window.location.href = "index.php";
    }, 5000);
  }
</script>

</body>
</html>


