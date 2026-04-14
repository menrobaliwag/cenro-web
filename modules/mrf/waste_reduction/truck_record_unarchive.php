<?php
require_once dirname(__DIR__, 3) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';
require_once dirname(__DIR__, 3) . '/includes/session_activity_audit.php';

requirePermission('mrf.truck');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    require_csrf();
    $id = (int)$_POST['id'];

    $result = restore_archived_record_with_audit(
        $conn,
        'MRF Waste Reduction Files',
        'files',
        $id,
        (int)($_SESSION['user_id'] ?? 0),
        (string)($_SESSION['user_email'] ?? '')
    );

    if ($result['ok']) {
        // Only redirect to internal paths to avoid open redirect via Referer.
        $redirect = url_with_base('modules/mrf/waste_reduction/truck_record_table.php');
        $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
        if ($ref !== '') {
            $parts = parse_url($ref);
            $path = is_array($parts) ? (string)($parts['path'] ?? '') : '';
            $query = is_array($parts) ? (string)($parts['query'] ?? '') : '';
            if ($path !== '') {
                $candidate = normalize_public_app_path($path) . ($query !== '' ? ('?' . $query) : '');
                $candidate = str_replace(["\r", "\n"], '', $candidate);
                if ($candidate !== '') {
                    $redirect = $candidate;
                }
            }
        }

        header('Location: ' . $redirect);
        exit;
    } else {
        echo htmlspecialchars((string)$result['message'], ENT_QUOTES, 'UTF-8');
    }
}
?>
