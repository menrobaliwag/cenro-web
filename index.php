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
    <div class="blob b3"></div>
    <div class="blob b2"></div>
  </div>

  <main class="wrap">
    <section class="hero">
      <!-- LEFT -->
      <div class="left glass" aria-label="CENRO Welcome">
        <span class="badge-secure">
          <i class="bi bi-shield-lock-fill"></i>
          Secure City Access
        </span>

        <h1 class="title">
          Welcome to <span class="grad">CENRO</span>
        </h1>

        <p class="sub">
          City Environment and Natural Resources Office portal.
        </p>

        <div class="chips" aria-label="Highlights">
          <span class="chip"><i class="bi bi-speedometer2"></i> Fast Dashboard</span>
          <span class="chip"><i class="bi bi-lock-fill"></i> Protected Access</span>
          <span class="chip"><i class="bi bi-graph-up-arrow"></i> Reports</span>
          <span class="chip"><i class="bi bi-cloud-check"></i> Reliable Data</span>
        </div>

        <div class="cta">
          <a href="<?= url_with_base('auth/index.php') ?>" class="btn btn-brand text-white w-100">
            <i class="bi bi-box-arrow-in-right me-2"></i> Continue to Login
          </a>
        </div>
      </div>

      <!-- RIGHT -->
      <aside class="right glass" aria-label="CENRO Info Panel">
        <div class="right-top">
          <img src="<?= url_with_base('assets/images/logo.png') ?>" alt="CENRO Logo" class="logo">
        </div>

        <div class="mini-card">
          <p class="k mb-1"><i class="bi bi-building me-2"></i>Official Portal</p>
          <p class="v mb-0">
            Use your authorized account to access internal tools and records.
          </p>
        </div>

        <div class="status">
          <span class="pulse" aria-hidden="true"></span>
          System Status: Online
          <span class="ms-2"><i class="bi bi-check-circle-fill"></i></span>
        </div>

        <div class="footer-note">
          &copy; <?= date("Y") ?> City Environment and Natural Resources Office
        </div>
      </aside>
    </section>
  </main>
</body>
</html>

