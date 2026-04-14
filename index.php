<?php
require_once __DIR__ . '/includes/security_bootstrap.php';
secure_session_start();
if (isset($_SESSION['admin_name'])) {
  header('Location: ' . url_with_base('admin/dashboard.php'));
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>CENRO | Welcome</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="<?= url_with_base('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet" />
  <link href="<?= url_with_base('dist/css/landing_page.css') ?>" rel="stylesheet">
</head>

<body>
  <div class="bg-blobs" aria-hidden="true">
    <div class="blob b1"></div>
    <div class="blob b2"></div>
    <div class="blob b3"></div>
  </div>

  <main class="landing-wrapper">
    <section class="hero-card">

      <!-- LEFT CONTENT -->
      <div class="hero-left glass-panel">
        <div class="top-badge">
          <i class="bi bi-shield-lock-fill"></i>
          <span>Secure Government Portal</span>
        </div>

        <h1 class="hero-title">
          Welcome to <span>City Enro</span>
        </h1>

        <p class="hero-subtitle">
          Access the City Environment and Natural Resources Office portal for secure dashboard tools,
          records management, and operational reports.
        </p>

        <div class="feature-list">
          <div class="feature-item">
            <i class="bi bi-speedometer2"></i>
            <span>Fast Dashboard Access</span>
          </div>
          <div class="feature-item">
            <i class="bi bi-shield-check"></i>
            <span>Protected Internal System</span>
          </div>
          <div class="feature-item">
            <i class="bi bi-bar-chart-line-fill"></i>
            <span>Reports & Monitoring</span>
          </div>
          <div class="feature-item">
            <i class="bi bi-database-check"></i>
            <span>Reliable Data Records</span>
          </div>
        </div>

        <div class="hero-actions">
          <a href="<?= url_with_base('auth/index.php') ?>" class="btn btn-main">
            <i class="bi bi-box-arrow-in-right"></i>
            Continue to Login
          </a>

          <div class="quick-note">
            <i class="bi bi-info-circle-fill"></i>
            Authorized personnel only
          </div>
        </div>
      </div>

      <!-- RIGHT CONTENT -->
      <aside class="hero-right glass-panel">
        <div class="logo-wrap">
          <div class="logo-circle">
            <img src="<?= url_with_base('assets/images/logo.png') ?>" alt="CENRO Logo" class="logo-img">
          </div>
          <h2 class="panel-title">Official Portal</h2>
          <p class="panel-subtitle">City Environment and Natural Resources Office</p>
        </div>

        <div class="info-card">
          <div class="info-icon">
            <i class="bi bi-person-badge-fill"></i>
          </div>
          <div>
            <h6>Authorized Access</h6>
            <p>Use your official account credentials to enter the system securely.</p>
          </div>
        </div>

        <div class="info-card">
          <div class="info-icon">
            <i class="bi bi-folder-check"></i>
          </div>
          <div>
            <h6>Internal Tools</h6>
            <p>Manage records, monitor reports, and navigate operational resources.</p>
          </div>
        </div>

        <div class="system-status">
          <span class="status-dot"></span>
          <span>System Status: Online</span>
          <i class="bi bi-check-circle-fill text-success"></i>
        </div>

        <div class="footer-note">
          &copy; <?= date("Y") ?> CENRO. All Rights Reserved.
        </div>
      </aside>

    </section>
  </main>
</body>
</html>
