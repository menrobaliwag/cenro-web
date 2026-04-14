<?php
require_once dirname(__DIR__) . '/includes/security.php';
secure_session_start();

function normalize_role_key(string $role): string
{
    $normalized = strtolower(trim($role));
    $normalized = str_replace([' ', '-'], '_', $normalized);
    $normalized = preg_replace('/_+/', '_', $normalized) ?? $normalized;
    return $normalized;
}

function is_super_role(string $role): bool
{
    $key = normalize_role_key($role);
    $superRoles = [
        'admin',
        'head_admin',
        'administrator',
        'super_admin',
    ];

    return in_array($key, $superRoles, true);
}

function role_landing_map(): array
{
    return [
        'admin' => url_with_base('dashboard.php'),
        'head_admin' => url_with_base('dashboard.php'),
        'administrator' => url_with_base('dashboard.php'),
        'super_admin' => url_with_base('dashboard.php'),
        'mrf_staff' => url_with_base('modules/mrf/truck_record/index.php'),
        'eco_police' => url_with_base('modules/eco_police/index.php'),
        'iec_admin' => url_with_base('modules/iec/index.php'),
        'iec_secretary' => url_with_base('modules/iec/index.php'),
        'monitoring' => url_with_base('modules/monitoring/index.php'),
        'palitbasura' => url_with_base('modules/palitbasura/index.php'),
        'parks' => url_with_base('modules/parks/index.php'),
        'mbcurp' => url_with_base('modules/mbcurp/index.php'),
    ];
}
