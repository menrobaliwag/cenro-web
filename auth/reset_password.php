<?php
require_once dirname(__DIR__) . '/includes/security_bootstrap.php';
secure_session_start();
include('../config/db.php');

date_default_timezone_set('Asia/Manila');
@mysqli_query($conn, "SET time_zone = '+08:00'");

$token = (string)($_GET['token'] ?? '');
$error = '';
$success = '';

if ($token === '') {
    die('Invalid or missing token.');
}

$tokenHash = hash('sha256', $token);
$stmt = $conn->prepare("SELECT * FROM user_form WHERE reset_token = ? LIMIT 1");
$stmt->bind_param("s", $tokenHash);
$stmt->execute();
$query = $stmt->get_result();
$stmt->close();

if (!$query || $query->num_rows === 0) {
    die('This reset link is invalid.');
}

$user = $query->fetch_assoc();
if (!empty($user['token_expiry']) && strtotime((string)$user['token_expiry']) < time()) {
    die('This reset link has expired.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();

    $newpass = (string)($_POST['newpass'] ?? '');
    $confpass = (string)($_POST['confpass'] ?? '');
    $passwordCheck = validate_password_policy($newpass, true);

    if (!$passwordCheck['ok']) {
        $error = (string)$passwordCheck['error'];
    } elseif ($newpass !== $confpass) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($newpass, PASSWORD_DEFAULT);
        $stmtUp = $conn->prepare(
            "UPDATE user_form
             SET password = ?, reset_token = NULL, token_expiry = NULL
             WHERE reset_token = ?"
        );
        $stmtUp->bind_param("ss", $hashed, $tokenHash);
        if ($stmtUp->execute()) {
            $success = 'Password updated successfully.';
        } else {
            $error = 'Failed to update password. Please try again.';
            error_log('Password reset update failed: ' . mysqli_error($conn));
        }
        $stmtUp->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>CENRO | Reset Password</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= url_with_base('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
  <link rel="stylesheet" href="../dist/css/reset_password.css">
</head>

<body>
  <div class="auth-box">

    <div class="text-center">
      <span class="mini-badge"><i class="bi bi-shield-lock"></i> Reset Password</span>
    </div>

    <h5 class="brand-title">Create a new password</h5>
    <div class="brand-sub">Make sure it is strong and easy to remember.</div>

    <?php if ($error !== ''): ?>
      <div class="alert alert-danger mb-3" role="alert">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
      <div class="alert alert-success mb-3" role="alert">
        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
      </div>

      <div class="d-grid gap-2">
        <a href="index.php" class="btn custom-green-btn">
          <i class="bi bi-box-arrow-in-right me-2"></i> Back to Login
        </a>
      </div>

    <?php else: ?>
      <form method="POST" novalidate>
        <?= csrf_input(); ?>

        <div class="mb-3 password-wrap">
          <label class="form-label fw-semibold">New Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input
              type="password"
              name="newpass"
              id="newpass"
              class="form-control"
              placeholder="Enter new password"
              minlength="10"
              required
            />
          </div>
        </div>

        <div class="mb-3 password-wrap">
          <label class="form-label fw-semibold">Confirm Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
            <input
              type="password"
              name="confpass"
              id="confpass"
              class="form-control"
              placeholder="Confirm new password"
              minlength="10"
              required
            />
          </div>
        </div>

        <div class="form-check mb-3">
          <input
            class="form-check-input"
            type="checkbox"
            id="showPasswords"
            onchange="toggleAllPasswords()"
          >
          <label class="form-check-label" for="showPasswords">
            Show Passwords
          </label>
        </div>

        <button type="submit" class="custom-green-btn">
          <i class="bi bi-arrow-repeat me-2"></i> Reset Password
        </button>
      </form>
    <?php endif; ?>

  </div>

  <script>
    function toggleAllPasswords() {
      const show = document.getElementById('showPasswords').checked;
      const newpass = document.getElementById('newpass');
      const confpass = document.getElementById('confpass');
      if (!newpass || !confpass) return;
      newpass.type = show ? 'text' : 'password';
      confpass.type = show ? 'text' : 'password';
    }
  </script>

</body>
</html>


