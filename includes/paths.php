<?php
require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/role_utils.php';
secure_session_start();

function basePath(): string {
    $role = normalize_role_key((string)($_SESSION['role'] ?? 'guest'));
    $root = BASE_URL;

    return match ($role) {
        'admin', 'head_admin', 'administrator', 'super_admin' => $root !== '' ? $root : '/',
        'mrf_staff' => url_with_base('modules/mrf'),
        'eco_police' => url_with_base('modules/eco_police'),
        'iec_admin', 'iec_secretary' => url_with_base('modules/iec'),
        'monitoring' => url_with_base('modules/monitoring'),
        'palitbasura' => url_with_base('modules/palitbasura'),
        'parks' => url_with_base('modules/parks'),
        'mbcurp' => url_with_base('modules/mbcurp'),
        default       => $root !== '' ? $root : '/'
    };
}
