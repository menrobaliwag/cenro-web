<?php
declare(strict_types=1);

// CLI-only regression test for includes/permissions.php can().
// This test does not hit the database; it stubs require_login() and injects session permissions.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

// Many includes resolve paths via dirname(__DIR__) . '/...'.
// In CLI, DOCUMENT_ROOT is usually missing; synthesize it from the repo location.
$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(2);
}
$docRoot = dirname($projectRoot);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;

// Prevent require_login() from redirecting/exiting during CLI assertions.
if (!function_exists('require_login')) {
    function require_login(): void
    {
        return;
    }
}

require_once dirname(__DIR__) . '/includes/permissions.php';

function assertSameBool(bool $expected, bool $actual, string $label): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$label} expected=" . ($expected ? 'true' : 'false') . " actual=" . ($actual ? 'true' : 'false') . "\n");
        exit(1);
    }
    fwrite(STDOUT, "PASS: {$label}\n");
}

// Role must satisfy prefix-role mapping (includes/rbac.php) for "mrf.*" permissions.
$_SESSION = [];
$_SESSION['user_id'] = 123;
$_SESSION['role_name'] = 'mrf_staff';
$_SESSION['role'] = 'mrf_staff';
$_SESSION['user_role'] = 'mrf_staff';

// 1) Un-granted permission should be denied (deny-by-default).
$_SESSION['permissions'] = ['mrf.truck']; // non-empty -> skip DB hydration
assertSameBool(false, can('mrf.delete'), "deny ungranted permission (mrf.delete)");

// 2) Granted permission should be allowed.
$_SESSION['permissions'] = ['mrf.delete'];
assertSameBool(true, can('mrf.delete'), "allow granted permission (mrf.delete)");

// 3) Wildcard permission should allow matching permissions.
$_SESSION['permissions'] = ['mrf.*'];
assertSameBool(true, can('mrf.delete'), "allow wildcard permission (mrf.* -> mrf.delete)");

fwrite(STDOUT, "OK\n");

