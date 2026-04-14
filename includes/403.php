<?php
require_once dirname(__DIR__) . '/includes/security.php';
secure_session_start();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>403 - Unauthorized | City ENRO</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Font & Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

  <style>
    :root{
      --primary:#16a34a;
      --primary-dark:#15803d;
      --bg:#f3f6fb;
      --text:#1f2933;
      --muted:#6b7280;
    }

    *{box-sizing:border-box}

    body{
      font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,sans-serif;
      background:
        radial-gradient(circle at top left, rgba(22,163,74,.12), transparent 40%),
        radial-gradient(circle at bottom right, rgba(45,126,247,.12), transparent 40%),
        var(--bg);
      margin:0;
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:20px;
    }

    .card{
      background:#fff;
      max-width:520px;
      width:100%;
      border-radius:22px;
      padding:40px 34px;
      text-align:center;
      box-shadow:0 25px 60px rgba(0,0,0,.12);
      animation:floatIn .6s ease-out both;
      position:relative;
      overflow:hidden;
    }

    .card::before{
      content:"";
      position:absolute;
      top:0;
      left:0;
      right:0;
      height:6px;
      background:linear-gradient(90deg, var(--primary), #2d7ef7);
    }

    .icon-wrap{
      width:90px;
      height:90px;
      border-radius:50%;
      background:linear-gradient(135deg, var(--primary), #2d7ef7);
      display:flex;
      align-items:center;
      justify-content:center;
      margin:0 auto 20px;
      color:#fff;
      font-size:38px;
      box-shadow:0 15px 30px rgba(22,163,74,.35);
      animation:pulse 2.5s infinite;
    }

    h1{
      margin:0 0 10px;
      font-size:28px;
      color:var(--text);
      letter-spacing:.3px;
    }

    .code{
      font-size:54px;
      font-weight:800;
      background:linear-gradient(135deg, var(--primary), #2d7ef7);
      -webkit-background-clip:text;
      -webkit-text-fill-color:transparent;
      margin-bottom:10px;
    }

    p{
      color:var(--muted);
      line-height:1.6;
      margin:0 0 26px;
      font-size:15px;
    }

    .btn{
      display:inline-flex;
      align-items:center;
      gap:10px;
      padding:12px 20px;
      border-radius:14px;
      text-decoration:none;
      font-weight:600;
      font-size:14px;
      color:#fff;
      background:linear-gradient(135deg, var(--primary), var(--primary-dark));
      box-shadow:0 10px 24px rgba(22,163,74,.35);
      transition:transform .2s ease, box-shadow .2s ease, filter .2s ease;
    }

    .btn:hover{
      transform:translateY(-2px);
      box-shadow:0 14px 34px rgba(22,163,74,.45);
      filter:brightness(1.05);
    }

    .footer{
      margin-top:26px;
      font-size:12px;
      color:#9ca3af;
    }

    @keyframes floatIn{
      from{opacity:0;transform:translateY(20px) scale(.98)}
      to{opacity:1;transform:none}
    }

    @keyframes pulse{
      0%,100%{box-shadow:0 15px 30px rgba(22,163,74,.35)}
      50%{box-shadow:0 20px 45px rgba(45,126,247,.55)}
    }

    @media(max-width:480px){
      .card{padding:30px 22px}
      .code{font-size:44px}
      h1{font-size:22px}
    }
  </style>
</head>
<body>

  <div class="card">
    <div class="icon-wrap">
      <i class="fa-solid fa-lock"></i>
    </div>

    <div class="code">403</div>
    <h1>Access Denied</h1>

    <p>
      Pasensya na, wala kang permiso para ma-access ang page na ito.
      Kung sa tingin mo ay may mali, makipag-ugnayan sa system administrator.
    </p>

    <a class="btn" href="<?= url_with_base('auth/index.php') ?>">
      <i class="fa-solid fa-arrow-left"></i>
      Back to Login
    </a>

    <div class="footer">
      © <?= date('Y') ?> Baliwag City ENRO System
    </div>
  </div>

</body>
</html>

