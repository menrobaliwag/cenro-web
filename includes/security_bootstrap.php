<?php
declare(strict_types=1);

// Dedicated bootstrap entrypoint for app-wide security controls.
// Keeps backward compatibility because includes/security.php still
// contains the actual implementation and bootstrap call.
require_once __DIR__ . '/security.php';

