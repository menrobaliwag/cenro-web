<?php
require_once dirname(__DIR__) . '/includes/auth_flow.php';

// Require login
if (!auth_flow_is_fully_authenticated()) {
    header('Location: ' . url_with_base('auth/index.php'));
    exit;
}

require_once dirname(__DIR__) . '/includes/permissions.php';
require_once dirname(__DIR__) . '/includes/paths.php';
require_once dirname(__DIR__) . '/config/db.php';

// Base path per role folder (admin/mrf/eco_police/etc.)
$base = basePath();

// Set default/fallback values
$user_name   = $_SESSION['user_name'] ?? 'Guest';
$user_email  = $_SESSION['user_email'] ?? '';
$user_avatar = $_SESSION['user_avatar'] ?? url_with_base('assets/images/user.png');
$sidebarRoleKey = normalize_role_key((string)(
    $_SESSION['role_name']
    ?? ($_SESSION['role'] ?? ($_SESSION['user_role'] ?? ''))
));
$isHeadAdminSidebar = $sidebarRoleKey === 'head_admin';

// Notification Center (role-aware)
$notifications = [];
$notifCount = 0;
$uid = (int)($_SESSION['user_id'] ?? 0);
$sessionBarangay = trim((string)($_SESSION['barangay'] ?? ''));

if (!function_exists('topbar_notif_cache_get')) {
    function topbar_notif_cache_get(int $uid, string $roleKey, string $barangay): ?array
    {
        $cache = $_SESSION['topbar_notif_cache'] ?? null;
        if (!is_array($cache)) {
            return null;
        }
        if ((int)($cache['uid'] ?? 0) !== $uid) {
            return null;
        }
        if ((string)($cache['role'] ?? '') !== $roleKey) {
            return null;
        }
        if ((string)($cache['barangay'] ?? '') !== $barangay) {
            return null;
        }
        $ts = (int)($cache['ts'] ?? 0);
        if ($ts <= 0 || (time() - $ts) > 20) {
            return null;
        }
        $summary = $cache['summary'] ?? null;
        return is_array($summary) ? $summary : null;
    }
}

if (!function_exists('topbar_notif_cache_set')) {
    function topbar_notif_cache_set(int $uid, string $roleKey, string $barangay, array $summary): void
    {
        $_SESSION['topbar_notif_cache'] = [
            'uid' => $uid,
            'role' => $roleKey,
            'barangay' => $barangay,
            'ts' => time(),
            'summary' => $summary,
        ];
    }
}

if (!function_exists('topbar_notif_for_head_admin')) {
    function topbar_notif_for_head_admin(mysqli $conn, int $limit = 5): array
    {
        $limit = max(1, min(10, $limit));

        $count = null;
        $stmtCount = @$conn->prepare(
            "SELECT COUNT(*) AS c
             FROM audit_logs
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        if ($stmtCount) {
            $stmtCount->execute();
            $res = $stmtCount->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $count = (int)($row['c'] ?? 0);
            $stmtCount->close();
        }

        $items = [];
        $sql = "SELECT user_id, user_name, email, action, module, description, created_at
                FROM audit_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY created_at DESC
                LIMIT {$limit}";
        $stmt = @$conn->prepare($sql);
        if ($stmt) {
            $stmt->execute();
            $res = $stmt->get_result();
            while ($n = $res->fetch_assoc()) {
                $actor = trim((string)($n['user_name'] ?? ''));
                if ($actor === '') {
                    $actor = trim((string)($n['email'] ?? ''));
                }
                if ($actor === '') {
                    $actor = 'User #' . (int)($n['user_id'] ?? 0);
                }

                $module = trim((string)($n['module'] ?? ''));
                $action = trim((string)($n['action'] ?? ''));
                $title = $module !== '' ? ($module . ': ' . $action) : $action;

                $desc = $actor;
                $body = trim((string)($n['description'] ?? ''));
                if ($body !== '') {
                    $desc .= ' - ' . $body;
                }

                $items[] = [
                    'action' => $title,
                    'description' => $desc,
                    'created_at' => (string)($n['created_at'] ?? ''),
                ];
            }
            $stmt->close();
        }

        // If count query failed, fall back to the visible items.
        if ($count === null) {
            $count = count($items);
        }

        return ['count' => $count, 'items' => $items];
    }
}

if (!function_exists('topbar_notif_for_user_audit')) {
    function topbar_notif_for_user_audit(mysqli $conn, int $uid, int $limit = 5): array
    {
        if ($uid <= 0) {
            return ['count' => 0, 'items' => []];
        }
        $limit = max(1, min(10, $limit));

        $count = null;
        $stmtCount = @$conn->prepare(
            "SELECT COUNT(*) AS c
             FROM audit_logs
             WHERE user_id = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        if ($stmtCount) {
            $stmtCount->bind_param("i", $uid);
            $stmtCount->execute();
            $res = $stmtCount->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $count = (int)($row['c'] ?? 0);
            $stmtCount->close();
        }

        $items = [];
        $sql = "SELECT action, description, created_at
                FROM audit_logs
                WHERE user_id = ?
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY created_at DESC
                LIMIT {$limit}";
        $stmt = @$conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($n = $res->fetch_assoc()) {
                $items[] = [
                    'action' => (string)($n['action'] ?? ''),
                    'description' => (string)($n['description'] ?? ''),
                    'created_at' => (string)($n['created_at'] ?? ''),
                ];
            }
            $stmt->close();
        }

        if ($count === null) {
            $count = count($items);
        }

        return ['count' => $count, 'items' => $items];
    }
}

if (!function_exists('topbar_iec_latest_deadline')) {
    function topbar_iec_latest_deadline(mysqli $conn, string $sectionKey, string $barangay): ?array
    {
        $sectionKey = trim($sectionKey);
        $barangay = trim($barangay);
        if ($sectionKey === '' || $barangay === '') {
            return null;
        }

         $stmt = @$conn->prepare(
            "SELECT d.id, d.section_key, d.scope, d.barangay, d.deadline_date, d.notes, d.created_at
             FROM iec_deadlines d
             LEFT JOIN user_form u ON u.id = d.created_by
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE d.section_key = ?
               AND ( (d.scope = 'barangay' AND d.barangay = ?) OR d.scope = 'global' )
               AND r.role_name = 'iec_admin'
             ORDER BY
               CASE WHEN d.scope = 'barangay' THEN 0 ELSE 1 END,
               d.deadline_date DESC,
               d.created_at DESC
             LIMIT 1"
         );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('ss', $sectionKey, $barangay);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return is_array($row) ? $row : null;
    }
}

if (!function_exists('topbar_iec_has_submission_since')) {
    function topbar_iec_has_submission_since(mysqli $conn, string $table, string $dateCol, string $barangay, string $since): bool
    {
        $table = trim($table);
        $dateCol = trim($dateCol);
        $barangay = trim($barangay);
        $since = trim($since);
        if ($table === '' || $dateCol === '' || $barangay === '' || $since === '') {
            return false;
        }

        // Table/column names are hard-coded in this file (not user input), safe to embed.
        $sql = "SELECT 1
                FROM {$table}
                WHERE is_deleted = 0
                  AND barangay = ?
                  AND {$dateCol} >= ?
                LIMIT 1";
        $stmt = @$conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ss', $barangay, $since);
        $stmt->execute();
        $res = $stmt->get_result();
        $has = ($res instanceof mysqli_result) && $res->num_rows > 0;
        $stmt->close();
        return $has;
    }
}

if (!function_exists('topbar_notif_for_iec_secretary')) {
    function topbar_notif_for_iec_secretary(mysqli $conn, string $barangay, int $limit = 5): array
    {
        $barangay = trim($barangay);
        if ($barangay === '') {
            return ['count' => 0, 'items' => []];
        }
        $limit = max(1, min(10, $limit));

        $sections = [
            'board_docs' => [
                'label' => 'Board Docs',
                'table' => 'iec_board_docs',
                'date_col' => 'created_at',
                'url' => url_with_base(''),
            ],
            'general_files' => [
                'label' => 'General Files',
                'table' => 'iec_general_files',
                'date_col' => 'uploaded_at',
                'url' => url_with_base(''),
            ],
            'minutes_meeting' => [
                'label' => 'Minutes of Meeting',
                'table' => 'iec_minutes_meeting',
                'date_col' => 'created_at',
                'url' => url_with_base(''),
            ],
            'cleanup_drive' => [
                'label' => 'Clean-Up Drive',
                'table' => 'iec_cleanup_drive',
                'date_col' => 'created_at',
                'url' => url_with_base(''),
            ],
        ];

        $today = new DateTime(date('Y-m-d'));
        $tasks = [];

        foreach ($sections as $sectionKey => $meta) {
            $dl = topbar_iec_latest_deadline($conn, (string)$sectionKey, $barangay);
            if (!$dl) {
                continue;
            }
            $deadlineDate = trim((string)($dl['deadline_date'] ?? ''));
            if ($deadlineDate === '') {
                continue;
            }
            $since = trim((string)($dl['created_at'] ?? ''));
            if ($since === '') {
                // If created_at is missing, fall back to midnight of deadline date.
                $since = $deadlineDate . ' 00:00:00';
            }

            // If user has submitted anything since the admin set the deadline, consider it done.
            if (topbar_iec_has_submission_since($conn, (string)$meta['table'], (string)$meta['date_col'], $barangay, $since)) {
                continue;
            }

            $dlObj = DateTime::createFromFormat('Y-m-d', $deadlineDate);
            if (!$dlObj) {
                $dlObj = new DateTime($deadlineDate);
            }
            $diffDays = (int)$today->diff($dlObj)->format('%r%a');

            $urgency = 'Deadline';
            if ($diffDays < 0) {
                $urgency = 'Overdue';
            } elseif ($diffDays <= 7) {
                $urgency = 'Due Soon';
            }

            $notes = trim((string)($dl['notes'] ?? ''));
            $desc = 'Submit before ' . date('F j, Y', strtotime($deadlineDate)) . '.';
            if ($notes !== '') {
                $desc .= ' ' . $notes;
            }

            $tasks[] = [
                'action' => 'IEC: ' . (string)$meta['label'] . ' (' . $urgency . ')',
                'description' => $desc,
                // Render the "date" column as the due date.
                'created_at' => $deadlineDate . ' 00:00:00',
                '_sort' => $diffDays,
            ];
        }

        usort($tasks, function ($a, $b) {
            $aSort = (int)($a['_sort'] ?? 0);
            $bSort = (int)($b['_sort'] ?? 0);
            if ($aSort === $bSort) {
                return strcmp((string)($a['created_at'] ?? ''), (string)($b['created_at'] ?? ''));
            }
            // Overdue first, then due soon, then later deadlines.
            return $aSort <=> $bSort;
        });

        $count = count($tasks);
        $items = array_slice($tasks, 0, $limit);
        foreach ($items as &$it) {
            unset($it['_sort']);
        }
        unset($it);

        return ['count' => $count, 'items' => $items];
    }
}

if (!function_exists('topbar_build_notification_summary')) {
    function topbar_build_notification_summary(mysqli $conn, int $uid, string $roleKey, string $barangay): array
    {
        $cached = topbar_notif_cache_get($uid, $roleKey, $barangay);
        if (is_array($cached)) {
            return $cached;
        }

        $limit = 5;
        $summary = ['count' => 0, 'items' => []];

        if ($roleKey === 'head_admin') {
            $summary = topbar_notif_for_head_admin($conn, $limit);
        } elseif ($roleKey === 'iec_secretary') {
            $summary = topbar_notif_for_iec_secretary($conn, $barangay, $limit);
            if ((int)($summary['count'] ?? 0) <= 0) {
                // Fallback: show the user's own audit activity when no IEC tasks are pending.
                $summary = topbar_notif_for_user_audit($conn, $uid, $limit);
            }
        } else {
            $summary = topbar_notif_for_user_audit($conn, $uid, $limit);
        }

        if (!is_array($summary['items'] ?? null)) {
            $summary['items'] = [];
        }
        $summary['count'] = max(0, (int)($summary['count'] ?? count($summary['items'])));

        topbar_notif_cache_set($uid, $roleKey, $barangay, $summary);
        return $summary;
    }
}

if ($isHeadAdminSidebar && $uid > 0 && isset($conn) && $conn instanceof mysqli) {
    $summary = topbar_build_notification_summary($conn, $uid, $sidebarRoleKey, $sessionBarangay);
    $notifications = (array)($summary['items'] ?? []);
    $notifCount = (int)($summary['count'] ?? 0);
}

$notifSeeAllUrl = url_with_base('settings.php?tab=activity');
if ($sidebarRoleKey === 'head_admin') {
    $notifSeeAllUrl = (function_exists('can') && can('monitoring.view'))
        ? url_with_base('modules/monitoring/audit_center.php')
        : url_with_base('settings.php?tab=activity');
} elseif ($sidebarRoleKey === 'iec_secretary') {
    $notifSeeAllUrl = url_with_base('modules/iec/index.php');
}

$requestPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
$segments = array_values(array_filter(explode('/', trim($requestPath, '/'))));
if (!empty($segments)) {
    $baseSegment = strtolower(trim((string)BASE_URL, '/'));
    if ($baseSegment !== '' && strtolower((string)$segments[0]) === $baseSegment) {
        array_shift($segments);
    }
}
if (!empty($segments) && strtolower((string)$segments[0]) === 'city_enro') {
    array_shift($segments);
}

$titleSeed = '';
if (!empty($segments)) {
    $last = (string)end($segments);
    $last = preg_replace('/\.php$/i', '', $last);
    if ($last === '' || strtolower($last) === 'index') {
        $prev = prev($segments);
        $titleSeed = is_string($prev) ? $prev : 'dashboard';
    } else {
        $titleSeed = $last;
    }
}
if ($titleSeed === '') {
    $titleSeed = 'dashboard';
}
$topbarTitle = ucwords(str_replace(['_', '-'], ' ', (string)$titleSeed));
$topbarSubtitle = 'City Environment and Natural Resources Office';

$breadcrumbParts = ['Home'];
foreach ($segments as $seg) {
    $clean = preg_replace('/\.php$/i', '', (string)$seg);
    if ($clean === '' || strtolower($clean) === strtolower(trim((string)BASE_URL, '/')) || strtolower($clean) === 'city_enro' || strtolower($clean) === 'modules') {
        continue;
    }
    $breadcrumbParts[] = ucwords(str_replace(['_', '-'], ' ', $clean));
}

if (count($breadcrumbParts) > 1 && strtolower((string)end($breadcrumbParts)) === 'index') {
    array_pop($breadcrumbParts);
}
?>
<!-- Customizer Panel -->
<aside class="customizer">
    <a href="javascript:void(0)" class="service-panel-toggle">
        <i class="fa fa-spin fa-cog"></i>
    </a>
    <div class="customizer-body">
        <ul class="nav customizer-tab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="pills-home-tab" data-toggle="pill" href="#pills-home" role="tab" aria-controls="pills-home" aria-selected="true">
                    <i class="mdi mdi-wrench font-20"></i>
                </a>
            </li>
        </ul>
        <div class="tab-content" id="pills-tabContent">
            <div class="tab-pane fade show active" id="pills-home" role="tabpanel" aria-labelledby="pills-home-tab">
                <div class="p-15 border-bottom">
                    <h5 class="font-medium m-b-10 m-t-10">Layout Settings</h5>
                    <div class="custom-control custom-checkbox m-t-10">
                        <input type="checkbox" class="custom-control-input" name="theme-view" id="theme-view">
                        <label class="custom-control-label" for="theme-view">Dark Theme</label>
                    </div>
                    <div class="custom-control custom-checkbox m-t-10">
                        <input type="checkbox" class="custom-control-input sidebartoggler" name="collapssidebar" id="collapssidebar">
                        <label class="custom-control-label" for="collapssidebar">Collapse Sidebar</label>
                    </div>
                    <div class="custom-control custom-checkbox m-t-10">
                        <input type="checkbox" class="custom-control-input" name="sidebar-position" id="sidebar-position">
                        <label class="custom-control-label" for="sidebar-position">Fixed Sidebar</label>
                    </div>
                    <div class="custom-control custom-checkbox m-t-10">
                        <input type="checkbox" class="custom-control-input" name="header-position" id="header-position">
                        <label class="custom-control-label" for="header-position">Fixed Header</label>
                    </div>
                    <div class="custom-control custom-checkbox m-t-10">
                        <input type="checkbox" class="custom-control-input" name="boxed-layout" id="boxed-layout">
                        <label class="custom-control-label" for="boxed-layout">Boxed Layout</label>
                    </div>
                </div>
                <div class="p-15 border-bottom">
                    <h5 class="font-medium m-b-10 m-t-10">Logo Backgrounds</h5>
                    <ul class="theme-color">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <li class="theme-item">
                                <a href="javascript:void(0)" class="theme-link" data-logobg="skin<?= $i ?>"></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </div>
                <div class="p-15 border-bottom">
                    <h5 class="font-medium m-b-10 m-t-10">Navbar Backgrounds</h5>
                    <ul class="theme-color">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <li class="theme-item">
                                <a href="javascript:void(0)" class="theme-link" data-navbarbg="skin<?= $i ?>"></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </div>
                <div class="p-15 border-bottom">
                    <h5 class="font-medium m-b-10 m-t-10">Sidebar Backgrounds</h5>
                    <ul class="theme-color">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <li class="theme-item">
                                <a href="javascript:void(0)" class="theme-link" data-sidebarbg="skin<?= $i ?>"></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</aside>

<!-- Topbar -->
<header class="topbar app-topbar">
    <nav class="navbar top-navbar navbar-light enro-top-navbar">
        <div class="enro-topbar-shell w-100 h-100 d-flex align-items-center">
            <div class="navbar-header enro-topbar-left d-flex align-items-center h-100">
                <a class="nav-toggler waves-effect waves-light d-block d-lg-none" href="javascript:void(0)" aria-label="Toggle sidebar">
                    <i class="ti-menu"></i>
                </a>

                <a href="<?= url_with_base('dashboard.php') ?>" class="navbar-brand logo enro-brand-group d-flex align-items-center mb-0">
                    <b class="logo-icon">
                        <img src="<?= url_with_base('assets/images/logo.png') ?>" alt="City ENRO" class="dark-logo">
                        <img src="<?= url_with_base('assets/images/logo.png') ?>" alt="City ENRO" class="light-logo">
                    </b>
                    <span class="logo-text fw-semibold">
                        <span class="dark-logo text-dark">CITY ENRO</span>
                        <span class="light-logo text-light">CITY ENRO</span>
                    </span>
                </a>

                <a class="sidebartoggler d-none d-lg-inline-flex align-items-center justify-content-center ms-auto" href="javascript:void(0)" data-sidebartype="mini-sidebar" aria-label="Collapse sidebar">
                    <i class="mdi mdi-menu font-20"></i>
                </a>
            </div>

            <div id="navbarSupportedContent" class="enro-topbar-main d-flex align-items-center flex-grow-1 min-w-0 h-100">
                <div class="enro-topbar-center flex-grow-1 min-w-0">
                    <div class="enro-topbar-title"><?= htmlspecialchars($topbarTitle, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php if (!empty($breadcrumbParts)): ?>
                        <ol class="enro-topbar-breadcrumb mb-0">
                            <?php foreach ($breadcrumbParts as $part): ?>
                                <li><?= htmlspecialchars((string)$part, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>

                <div class="enro-topbar-right ms-auto h-100 d-flex align-items-center">
                    <ul class="navbar-nav flex-row align-items-center enro-topbar-actions mb-0">
                        <?php if ($isHeadAdminSidebar): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link position-relative enro-topbar-icon" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                                <i class="mdi mdi-bell-outline fs-5"></i>
                                <?php if ($notifCount > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill app-notif-badge">
                                        <?= $notifCount > 9 ? '9+' : $notifCount ?>
                                        <span class="visually-hidden">new notifications</span>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end app-notification-menu">
                                <li class="dropdown-header fw-semibold">Notifications</li>
                                <li><hr class="dropdown-divider"></li>
                                <?php if (empty($notifications)): ?>
                                    <li><div class="dropdown-item-text text-muted small py-2">No notifications.</div></li>
                                <?php else: ?>
                                    <?php foreach ($notifications as $n): ?>
                                        <li>
                                            <div class="dropdown-item-text py-2">
                                                <div class="d-flex justify-content-between gap-2">
                                                    <div class="fw-semibold small"><?= htmlspecialchars($n['action'], ENT_QUOTES, 'UTF-8') ?></div>
                                                    <div class="text-muted small">
                                                        <?= $n['created_at'] ? htmlspecialchars(date('M j, g:i A', strtotime($n['created_at'])), ENT_QUOTES, 'UTF-8') : '' ?>
                                                    </div>
                                                </div>
                                                <?php if (!empty($n['description'])): ?>
                                                    <div class="text-muted small mt-1"><?= htmlspecialchars($n['description'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                        <li><hr class="dropdown-divider my-0"></li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider my-0"></li>
                                <li>
                                    <a class="dropdown-item text-center fw-semibold" href="<?= htmlspecialchars($notifSeeAllUrl, ENT_QUOTES, 'UTF-8') ?>">
                                        See all
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <?php endif; ?>

                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <img src="<?= htmlspecialchars($user_avatar, ENT_QUOTES, 'UTF-8') ?>" alt="user" class="rounded-circle" width="38" height="38">
                                <span class="fw-semibold d-none d-md-inline-block">
                                    <?= htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end app-user-menu">
                                <li>
                                    <div class="d-flex align-items-center p-3 bg-primary text-white rounded-3">
                                        <img src="<?= htmlspecialchars($user_avatar, ENT_QUOTES, 'UTF-8') ?>" alt="user" class="rounded-circle me-2" width="52" height="52">
                                        <div>
                                            <div class="fw-semibold"><?= htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') ?></div>
                                            <small><?= htmlspecialchars($user_email, ENT_QUOTES, 'UTF-8') ?></small>
                                        </div>
                                    </div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= url_with_base('settings.php') ?>"><i class="mdi mdi-settings me-2"></i>Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?= url_with_base('auth/logout.php') ?>"><i class="fa fa-power-off me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
</header>

<!-- Sidebar -->
<aside class="left-sidebar app-sidebar">
  <div class="scroll-sidebar">
    <nav class="sidebar-nav">
      <ul id="sidebarnav">

          <li class="sidebar-item">
            <a class="sidebar-link" href="<?= url_with_base('dashboard.php') ?>">
              <i class="mdi mdi-av-timer"></i>
              <span class="hide-menu">DASHBOARD</span>
            </a>
          </li>

        <!-- ===================== MRF ===================== -->
        <?php if (can('mrf.truck') || can('mrf.sorters') || can('mrf.other_waste') || can('mrf.waste_reduction')): ?>
          <li class="nav-small-cap">
            <i class="mdi mdi-dots-horizontal"></i>
            <span class="hide-menu nav-section-label">MRF</span>
          </li>

          <?php if (can('mrf.truck')): ?>
            <li class="sidebar-item">
              <a class="sidebar-link waves-effect waves-dark" href="<?= url_with_base('modules/mrf/truck_record/index.php') ?>">
                <i class="fa fa-clock"></i>
                <span class="hide-menu">TIME IN AND OUT</span>
              </a>
            </li>
          <?php endif; ?>

          <?php if (can('mrf.sorters')): ?>
            <li class="sidebar-item">
              <a class="sidebar-link waves-effect waves-dark sidebar-link" href="<?= url_with_base('modules/mrf/sorters/index.php') ?>">
                <i class="fa fa-sort"></i>
                <span class="hide-menu">SORTERS</span>
              </a>
            </li>
          <?php endif; ?>

          <?php if (can('mrf.other_waste')): ?>
            <li class="sidebar-item">
              <a class="sidebar-link waves-effect waves-dark sidebar-link" href="<?= url_with_base('modules/mrf/other_waste/index.php') ?>">
                <i class="fa fa-cubes"></i>
                <span class="hide-menu">
                  OTHER WASTE
                  <small class="nav-subtext">
                    (ECO-BRICKS / PAVEMENT BRICK /<br>CEMENT BRICKS / CHARCOAL<br>BRIQUETTE)
                  </small>
                </span>
              </a>
            </li>
          <?php endif; ?>

          <?php if (can('mrf.waste_reduction')): ?>
            <li class="sidebar-item">
              <a class="sidebar-link waves-effect waves-dark sidebar-link" href="<?= url_with_base('modules/mrf/waste_reduction/index.php') ?>">
                <i class="mdi mdi-recycle"></i>
                <span class="hide-menu">
                  WASTE REDUCTION
                  <small class="nav-subtext">
                    (OTHER WASTE)
                  </small>
                </span>
              </a>
            </li>
          <?php endif; ?>
        <?php endif; ?>

        <!-- ===================== ECO POLICE ===================== -->
        <?php if (can('eco.violators')): ?>
          <li class="nav-small-cap">
            <i class="mdi mdi-dots-horizontal"></i>
            <span class="hide-menu nav-section-label">ECO POLICE</span>
          </li>

          <li class="sidebar-item">
            <a class="sidebar-link waves-effect waves-dark sidebar-link" href="<?= url_with_base('modules/eco_police/index.php') ?>">
              <i class="mdi mdi-recycle"></i>
              <span class="hide-menu">Violators List</span>
            </a>
          </li>
          <li class="sidebar-item">
            <a class="sidebar-link waves-effect waves-dark sidebar-link" href="<?= url_with_base('modules/eco_police/attendance.php') ?>">
              <i class="mdi mdi-calendar-check"></i>
              <span class="hide-menu">Attendance</span>
            </a>
          </li>
        <?php endif; ?>

        <!-- ===================== IEC ===================== -->
        <?php if (can('iec.manage')): ?>
          <li class="nav-small-cap">
            <i class="mdi mdi-dots-horizontal"></i>
            <span class="hide-menu nav-section-label">IEC</span>
          </li>

          <!-- Barangay Files -->
          <li class="sidebar-item">
            <a class="sidebar-link waves-effect waves-dark sidebar-link" href="<?= url_with_base('modules/iec/index.php') ?>">
              <i class="mdi mdi-folder-multiple-outline"></i>
              <span class="hide-menu">Barangay Files</span>
            </a>
          </li>
        <?php endif; ?>


        <!-- ===================== PALIT BASURA ===================== -->
        <?php if (can('palitbasura.view')): ?>
          <li class="nav-small-cap">
            <i class="mdi mdi-dots-horizontal"></i>
            <span class="hide-menu nav-section-label">Palit Basura</span>
          </li>

          <li class="sidebar-item">
            <a class="sidebar-link waves-effect waves-dark sidebar-link" href="<?= url_with_base('modules/palitbasura/index.php') ?>">
              <i class="mdi mdi-delete"></i>
              <span class="hide-menu">Palit Basura</span>
            </a>
          </li>

          <li class="sidebar-item">
            <a class="sidebar-link waves-effect waves-dark sidebar-link" href="<?= url_with_base('modules/palitbasura/barangay.php') ?>">
              <i class="mdi mdi-file-document"></i>
              <span class="hide-menu">Aling Tindera (Barangay)</span>
            </a>
          </li>

          <li class="sidebar-item">
            <a class="sidebar-link waves-effect waves-dark sidebar-link" href="<?= url_with_base('modules/palitbasura/central_mrf.php') ?>">
              <i class="mdi mdi-file-outline"></i>
              <span class="hide-menu">Aling Tindera (Central MRF)</span>
            </a>
          </li>
        <?php endif; ?>

        <!-- ===================== PARKS & MONUMENT ===================== -->
        <?php if (can('parks.view')): ?>
          <li class="nav-small-cap">
            <i class="mdi mdi-dots-horizontal"></i>
            <span class="hide-menu nav-section-label">Parks and Monument</span>
          </li>

          <li class="sidebar-item">
            <a class="sidebar-link waves-effect waves-dark sidebar-link" href="<?= url_with_base('modules/parks/files.php') ?>">
              <i class="mdi mdi-recycle"></i>
              <span class="hide-menu">Parks and Monument</span>
            </a>
          </li>
        <?php endif; ?>

        <!-- ===================== MBCURP ===================== -->
        <?php if (can('mbcurp.view')): ?>
          <li class="nav-small-cap">
            <i class="mdi mdi-dots-horizontal"></i>
            <span class="hide-menu nav-section-label">MBCURP</span>
          </li>

          <li class="sidebar-item">
            <a class="sidebar-link waves-effect waves-dark sidebar-link" href="<?= url_with_base('modules/mbcurp/index.php') ?>">
              <i class="mdi mdi-recycle"></i>
              <span class="hide-menu">Mbcurp Files</span>
            </a>
          </li>
        <?php endif; ?>

        <?php if (can('monitoring.view')): ?>
          <li class="nav-small-cap">
            <i class="mdi mdi-dots-horizontal"></i>
            <span class="hide-menu nav-section-label">Monitoring Folder</span>
          </li>

          <li class="sidebar-item">
            <a class="sidebar-link waves-effect waves-dark sidebar-link" href="<?= url_with_base('modules/monitoring/files.php') ?>">
              <i class="mdi mdi-folder-multiple-outline"></i>
              <span class="hide-menu">Monitoring Files</span>
            </a>
          </li>
        <?php endif; ?>

        <?php if ($isHeadAdminSidebar): ?>
          <li class="nav-small-cap">
            <i class="mdi mdi-dots-horizontal"></i>
            <span class="hide-menu nav-section-label">System Maintenance</span>
          </li>

          <li class="sidebar-item">
            <a class="sidebar-link waves-effect waves-dark sidebar-link" href="<?= url_with_base('modules/monitoring/index.php') ?>">
              <i class="mdi mdi-monitor-dashboard"></i>
              <span class="hide-menu">System Monitoring</span>
            </a>
          </li>

          <li class="sidebar-item">
            <a class="sidebar-link waves-effect waves-dark sidebar-link" href="<?= url_with_base('modules/monitoring/audit_center.php') ?>">
              <i class="mdi mdi-shield-account"></i>
              <span class="hide-menu">Session & Audit Center</span>
            </a>
          </li>
        <?php endif; ?>

      </ul>
    </nav>
  </div>
</aside>

