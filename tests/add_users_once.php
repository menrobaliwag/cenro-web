<?php
require_once dirname(__DIR__) . '/includes/security.php';
secure_session_start();
include('../config/db.php');
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/permissions.php';

requirePermission('admin.full');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}
require_csrf();

/**
 * Default role for all barangay secretaries
 */
$defaultRoleName = 'iec_secretary';

/**
 * Default password for all accounts
 */
$defaultPassword = 'Admin123!';

/**
 * 27 Barangay Accounts for Baliuag
 * Format: [email, full_name, barangay]
 */
$users = [
  ['brgy01@baliuag.gov.ph', 'Barangay Bagong Nayon Secretary', 'Bagong Nayon'],
  ['brgy02@baliuag.gov.ph', 'Barangay Calantipay Secretary', 'Calantipay'],
  ['brgy03@baliuag.gov.ph', 'Barangay Catulinan Secretary', 'Catulinan'],
  ['brgy04@baliuag.gov.ph', 'Barangay Concepcion Secretary', 'Concepcion'],
  ['brgy05@baliuag.gov.ph', 'Barangay Hinukay Secretary', 'Hinukay'],
  ['brgy06@baliuag.gov.ph', 'Barangay Makinabang Secretary', 'Makinabang'],
  ['brgy07@baliuag.gov.ph', 'Barangay Matangtubig Secretary', 'Matangtubig'],
  ['brgy08@baliuag.gov.ph', 'Barangay Pagala Secretary', 'Pagala'],
  ['brgy09@baliuag.gov.ph', 'Barangay Paitan Secretary', 'Paitan'],
  ['brgy10@baliuag.gov.ph', 'Barangay Piel Secretary', 'Piel'],
  ['brgy11@baliuag.gov.ph', 'Barangay Poblacion Secretary', 'Poblacion'],
  ['brgy12@baliuag.gov.ph', 'Barangay Sabang Secretary', 'Sabang'],
  ['brgy13@baliuag.gov.ph', 'Barangay San Jose Secretary', 'San Jose'],
  ['brgy14@baliuag.gov.ph', 'Barangay San Roque Secretary', 'San Roque'],
  ['brgy15@baliuag.gov.ph', 'Barangay Santa Barbara Secretary', 'Santa Barbara'],
  ['brgy16@baliuag.gov.ph', 'Barangay Santo Cristo Secretary', 'Santo Cristo'],
  ['brgy17@baliuag.gov.ph', 'Barangay Sulivan Secretary', 'Sulivan'],
  ['brgy18@baliuag.gov.ph', 'Barangay Tangos Secretary', 'Tangos'],
  ['brgy19@baliuag.gov.ph', 'Barangay Tarcan Secretary', 'Tarcan'],
  ['brgy20@baliuag.gov.ph', 'Barangay Tiaong Secretary', 'Tiaong'],
  ['brgy21@baliuag.gov.ph', 'Barangay Tibag Secretary', 'Tibag'],
  ['brgy22@baliuag.gov.ph', 'Barangay Tilapayong Secretary', 'Tilapayong'],
  ['brgy23@baliuag.gov.ph', 'Barangay Virgen Delas Flores Secretary', 'Virgen Delas Flores'],
  ['brgy24@baliuag.gov.ph', 'Barangay Biak-na-Bato Secretary', 'Biak-na-Bato'],
  ['brgy25@baliuag.gov.ph', 'Barangay Baliuag Norte Secretary', 'Baliuag Norte'],
  ['brgy26@baliuag.gov.ph', 'Barangay Baliuag Sur Secretary', 'Baliuag Sur'],
  ['brgy27@baliuag.gov.ph', 'Barangay Subic Secretary', 'Subic'],
];

/**
 * Get role ID
 */
$stmtRole = $conn->prepare("SELECT id FROM roles WHERE role_name=? LIMIT 1");
$stmtRole->bind_param("s", $defaultRoleName);
$stmtRole->execute();
$role = $stmtRole->get_result()->fetch_assoc();

if (!$role) {
  die("❌ Missing role: {$defaultRoleName}. Create it first.");
}
$role_id = (int)$role['id'];

/**
 * Prepared insert for user_form table
 */
$stmtInsert = $conn->prepare("
  INSERT INTO user_form (name, email, password, role_id, barangay, status, created_at)
  VALUES (?, ?, ?, ?, ?, 'active', NOW())
");

foreach ($users as [$email, $fullName, $barangay]) {

  // Prevent duplicates
  $check = $conn->prepare("SELECT id FROM user_form WHERE email=? LIMIT 1");
  $check->bind_param("s", $email);
  $check->execute();
  $exists = $check->get_result()->fetch_assoc();
  $check->close();

  if ($exists) {
    echo "⏭️ Skipped (exists): $email<br>";
    continue;
  }

  $hash = password_hash($defaultPassword, PASSWORD_DEFAULT);
  $stmtInsert->bind_param("sssis", $fullName, $email, $hash, $role_id, $barangay);

  if ($stmtInsert->execute()) {
    echo "✅ Added: $email | $barangay | role=iec_secretary<br>";
  } else {
    echo "⚠️ Failed: $email - " . htmlspecialchars($stmtInsert->error) . "<br>";
  }
}

$stmtInsert->close();
echo "<hr><b>DONE.</b> DELETE THIS FILE NOW for security.";
