<?php
require_once dirname(__DIR__, 3) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';

requirePermission('iec.manage');

$userId = (int)($_SESSION['user_id'] ?? 0);
$myBarangay = trim($_SESSION['barangay'] ?? '');
$isAdmin = (($_SESSION['role'] ?? '') === 'admin');
