<?php
require_once dirname(__DIR__) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/permissions.php';

requirePermission('admin.full');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}
require_csrf();

echo password_hash("Admin123!", PASSWORD_DEFAULT);
