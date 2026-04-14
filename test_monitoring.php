<?php
require_once __DIR__ . '/includes/security.php';
secure_session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

requirePermission('admin.full');

echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<hr>";

echo "User ID: " . ($_SESSION['user_id'] ?? 'NONE') . "<br>";
echo "Role ID: " . ($_SESSION['role_id'] ?? 'NONE') . "<br>";
echo "Role: " . ($_SESSION['role'] ?? 'NONE') . "<br>";

echo "<hr>";

if (can('monitoring.view')) {
  echo "<h2 style='color:green'>✅ Monitoring Access GRANTED</h2>";
} else {
  echo "<h2 style='color:red'>❌ Monitoring Access DENIED</h2>";
}
