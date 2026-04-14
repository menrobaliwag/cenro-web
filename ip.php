<?php
require_once __DIR__ . '/includes/security.php';

echo "REMOTE_ADDR: " . ($_SERVER['REMOTE_ADDR'] ?? 'none') . "<br>";
echo "security_client_ip(): " . security_client_ip() . "<br>";
echo "HOSTNAME: " . gethostname() . "<br>";
echo "gethostbyname(hostname): " . gethostbyname(gethostname()) . "<br>";
