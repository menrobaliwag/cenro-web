<?php
require_once dirname(__DIR__) . '/includes/security_bootstrap.php';
secure_session_start();

$requestPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
$moduleThemeName = '';
$basePrefix = rtrim((string)BASE_URL, '/');
$modulePattern = '#^' . ($basePrefix !== '' ? preg_quote($basePrefix, '#') : '') . '/modules/(mrf|eco_police|palitbasura|iec)(?:/|$)#';
if (preg_match($modulePattern, $requestPath, $m)) {
    $moduleThemeName = (string)$m[1];
}

$bodyClasses = ['app-shell'];
if ($moduleThemeName !== '') {
    $bodyClasses[] = 'module-pro';
    $bodyClasses[] = 'module-' . str_replace('_', '-', $moduleThemeName);
}
$allowedUiTones = [
    'green', 'navy', 'teal', 'forest',
    'emerald', 'royal', 'indigo', 'slate', 'charcoal',
    'maroon', 'sunset', 'plum', 'aqua', 'olive'
];
$sessionTopbarTone = (string)($_SESSION['topbar_tone'] ?? ($_SESSION['ui_tone'] ?? 'green'));
$sessionSidebarTone = (string)($_SESSION['sidebar_tone'] ?? ($_SESSION['ui_tone'] ?? 'green'));
if (!in_array($sessionTopbarTone, $allowedUiTones, true)) $sessionTopbarTone = 'green';
if (!in_array($sessionSidebarTone, $allowedUiTones, true)) $sessionSidebarTone = 'green';
$bodyClassAttr = implode(' ', $bodyClasses);

if (!function_exists('asset_mtime_cached')) {
    function asset_mtime_cached(string $relative): int {
        static $cache = [];
        if (isset($cache[$relative])) return $cache[$relative];
        $path = $_SERVER['DOCUMENT_ROOT'] . url_with_base($relative);
        $cache[$relative] = @filemtime($path) ?: 1;
        return $cache[$relative];
    }
}

$enroThemeVer = asset_mtime_cached('assets/css/enro-theme.css');
$dtThemeVer = asset_mtime_cached('assets/css/datatable-theme.css');
$mbcurpThemeVer = asset_mtime_cached('dist/css/mbcurp.css');
$monitoringFilesThemeVer = asset_mtime_cached('dist/css/monitoring_files.css');
$parksFilesThemeVer = asset_mtime_cached('dist/css/parks_files.css');
?>
<!DOCTYPE html>
<html dir="ltr" lang="en"
      data-ui-tone="<?= htmlspecialchars($sessionTopbarTone, ENT_QUOTES, 'UTF-8') ?>"
      data-topbar-tone="<?= htmlspecialchars($sessionTopbarTone, ENT_QUOTES, 'UTF-8') ?>"
      data-sidebar-tone="<?= htmlspecialchars($sessionSidebarTone, ENT_QUOTES, 'UTF-8') ?>">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <script>
      (function () {
        var allowed = ['green', 'navy', 'teal', 'forest', 'emerald', 'royal', 'indigo', 'slate', 'charcoal', 'maroon', 'sunset', 'plum', 'aqua', 'olive'];
        var sessionTopbarTone = <?= json_encode($sessionTopbarTone, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        var sessionSidebarTone = <?= json_encode($sessionSidebarTone, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        var topbarTone = allowed.indexOf(sessionTopbarTone) !== -1 ? sessionTopbarTone : 'green';
        var sidebarTone = allowed.indexOf(sessionSidebarTone) !== -1 ? sessionSidebarTone : 'green';
        try {
          localStorage.setItem('enro_ui_tone', topbarTone);
          localStorage.setItem('enro_topbar_tone', topbarTone);
          localStorage.setItem('enro_sidebar_tone', sidebarTone);
        } catch (e) {
          // no-op
        }
        document.documentElement.setAttribute('data-ui-tone', topbarTone);
        document.documentElement.setAttribute('data-topbar-tone', topbarTone);
        document.documentElement.setAttribute('data-sidebar-tone', sidebarTone);
      })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Favicon icon -->
    <link rel="icon" type="image/png" sizes="16x16" href="<?= url_with_base('assets/images/logo.png') ?>">
    <title>Baliwag City Enro</title>
    <!-- Custom CSS -->
    <link href="<?= url_with_base('assets/libs/chartist/dist/chartist.min.css') ?>" rel="stylesheet">
    <link href="<?= url_with_base('assets/extra-libs/c3/c3.min.css') ?>" rel="stylesheet">
    <link href="<?= url_with_base('assets/extra-libs/jvector/jquery-jvectormap-2.0.2.css') ?>" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= url_with_base('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">

    <!-- STYLES -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">

    <!-- DataTables CSS with Bootstrap 5 styling -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

    <!-- ✅ Corrected paths -->
    <link href="<?= url_with_base('assets/libs/datatables.net-bs4/css/dataTables.bootstrap4.css') ?>" rel="stylesheet">
    <link href="<?= url_with_base('dist/css/style.css') ?>" rel="stylesheet">
    <link href="<?= url_with_base('dist/css/time.css') ?>" rel="stylesheet">
    <link href="<?= url_with_base('dist/css/style.min.css') ?>" rel="stylesheet">
    <link href="<?= url_with_base('dist/css/theme.css') ?>" rel="stylesheet">
    <link href="<?= url_with_base('dist/css/logo.css') ?>" rel="stylesheet">
    <link href="<?= url_with_base('dist/css/enterprise.css') ?>" rel="stylesheet">

    <?php if (strpos($_SERVER['REQUEST_URI'] ?? '', '/modules/iec/index.php') !== false): ?>
      <link href="<?= url_with_base('dist/css/iec_modals.css') ?>" rel="stylesheet">
    <?php endif; ?>

    <?php if (strpos($_SERVER['REQUEST_URI'] ?? '', '/modules/mbcurp/') !== false): ?>
      <link href="<?= url_with_base('dist/css/mbcurp.css') ?>?v=<?= (int)($mbcurpThemeVer ?: 1) ?>" rel="stylesheet">
    <?php endif; ?>

    <?php if (strpos($_SERVER['REQUEST_URI'] ?? '', '/modules/monitoring/files.php') !== false): ?>
      <link href="<?= url_with_base('dist/css/monitoring_files.css') ?>?v=<?= (int)($monitoringFilesThemeVer ?: 1) ?>" rel="stylesheet">
    <?php endif; ?>

    <?php if (strpos($_SERVER['REQUEST_URI'] ?? '', '/modules/parks/files.php') !== false): ?>
      <link href="<?= url_with_base('dist/css/parks_files.css') ?>?v=<?= (int)($parksFilesThemeVer ?: 1) ?>" rel="stylesheet">
    <?php endif; ?>

    <?php if ($moduleThemeName !== ''): ?>
      <link href="<?= url_with_base('dist/css/module_professional.css') ?>" rel="stylesheet">
    <?php endif; ?>
    <link href="<?= url_with_base('dist/css/product_shell.css') ?>" rel="stylesheet">
    <link href="<?= url_with_base('assets/css/enro-theme.css') ?>?v=<?= (int)($enroThemeVer ?: 1) ?>" rel="stylesheet">
    <link href="<?= url_with_base('assets/css/datatable-theme.css') ?>?v=<?= (int)($dtThemeVer ?: 1) ?>" rel="stylesheet">
</head>
<body class="<?= htmlspecialchars($bodyClassAttr, ENT_QUOTES, 'UTF-8') ?>">
    <!-- ============================================================== -->
    <!-- Preloader - style you can find in spinners.css -->
    <!-- ============================================================== -->
    <div class="preloader">
        <div class="lds-ripple">
            <div class="lds-pos"></div>
            <div class="lds-pos"></div>
        </div>
    </div>
    <!-- ============================================================== -->
    <!-- Main wrapper - style you can find in pages.scss -->
    <!-- ============================================================== -->
    <div id="main-wrapper">
        
