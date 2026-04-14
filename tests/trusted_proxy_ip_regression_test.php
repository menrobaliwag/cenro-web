<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';

function assert_same(string $expected, string $actual, string $label): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$label}\nExpected: {$expected}\nActual:   {$actual}\n");
        exit(1);
    }
}

// We need a REMOTE_ADDR that is NOT allowlisted as a trusted proxy.
$remoteCandidates = [
    '198.51.100.10', // TEST-NET-2
    '203.0.113.10',  // TEST-NET-3
    '192.0.2.10',    // TEST-NET-1
    '2001:db8::1',   // Documentation IPv6
];

$remoteAddr = null;
foreach ($remoteCandidates as $candidate) {
    if (!security_is_trusted_proxy($candidate)) {
        $remoteAddr = $candidate;
        break;
    }
}

if ($remoteAddr === null) {
    fwrite(STDERR, "FAIL: Could not find a non-trusted REMOTE_ADDR for this test. Check config/trusted_proxies.php.\n");
    exit(1);
}

$_SERVER['REMOTE_ADDR'] = $remoteAddr;
$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.5, 10.0.0.1';
$_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.6';

// Default behavior: ignore forwarded headers unless REMOTE_ADDR is a trusted proxy.
assert_same($remoteAddr, security_client_ip(), 'Forwarded IP headers ignored when REMOTE_ADDR is not trusted');

echo "OK: trusted-proxy IP regression test passed.\n";

