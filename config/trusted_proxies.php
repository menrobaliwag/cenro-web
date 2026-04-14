<?php
declare(strict_types=1);

/**
 * Trusted reverse proxies/load balancers allowed to supply forwarded client IP headers.
 *
 * Only when `REMOTE_ADDR` matches one of these rules will the app honor:
 * - `HTTP_CF_CONNECTING_IP`
 * - `HTTP_X_FORWARDED_FOR`
 *
 * Rules may be:
 * - Single IP: "203.0.113.10"
 * - CIDR:      "203.0.113.0/24" or "2001:db8::/32"
 *
 * Default is empty (deny by default) to prevent spoofing.
 */
$TRUSTED_PROXIES = [
    // '127.0.0.1',
    // '::1',
    // '203.0.113.0/24',
];

return $TRUSTED_PROXIES;

