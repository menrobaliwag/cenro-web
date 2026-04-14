<?php
require_once dirname(__DIR__) . '/includes/security_bootstrap.php';
secure_session_start();
require_once dirname(__DIR__) . '/config/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

require_csrf();

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    exit('Not logged in');
}

function ensureUserSettingsTable(mysqli $conn): bool
{
    $createSql = "
        CREATE TABLE IF NOT EXISTS user_settings (
            user_id INT NOT NULL,
            ui_tone VARCHAR(20) NOT NULL DEFAULT 'green',
            topbar_tone VARCHAR(20) NOT NULL DEFAULT 'green',
            sidebar_tone VARCHAR(20) NOT NULL DEFAULT 'green',
            avatar_path VARCHAR(255) NULL DEFAULT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    if (!$conn->query($createSql)) {
        return false;
    }

    $requiredCols = [
        'ui_tone' => "VARCHAR(20) NOT NULL DEFAULT 'green'",
        'topbar_tone' => "VARCHAR(20) NOT NULL DEFAULT 'green'",
        'sidebar_tone' => "VARCHAR(20) NOT NULL DEFAULT 'green'",
        'avatar_path' => "VARCHAR(255) NULL DEFAULT NULL",
    ];
    foreach ($requiredCols as $colName => $colSql) {
        $col = $conn->query("SHOW COLUMNS FROM user_settings LIKE '" . $conn->real_escape_string($colName) . "'");
        if (!$col || $col->num_rows === 0) {
            if (!$conn->query("ALTER TABLE user_settings ADD COLUMN " . $colName . " " . $colSql)) {
                return false;
            }
        }
    }

    return true;
}

$allowedUiTones = [
    'green', 'navy', 'teal', 'forest',
    'emerald', 'royal', 'indigo', 'slate', 'charcoal',
    'maroon', 'sunset', 'plum', 'aqua', 'olive'
];
// Supports both new payload and legacy payload.
$topbarTone = (string)($_POST['topbar_tone'] ?? $_POST['ui_tone'] ?? '');
$sidebarTone = (string)($_POST['sidebar_tone'] ?? $_POST['ui_tone'] ?? '');

if (!in_array($topbarTone, $allowedUiTones, true) || !in_array($sidebarTone, $allowedUiTones, true)) {
    $legacyTopbarSkin = (string)($_POST['topbar_skin'] ?? 'skin6');
    $legacySidebarSkin = (string)($_POST['sidebar_skin'] ?? $legacyTopbarSkin);
    $legacyMap = [
        'skin5' => 'navy',
        'dark' => 'navy',
        'skin3' => 'teal',
        'skin4' => 'forest',
    ];
    if (!in_array($topbarTone, $allowedUiTones, true)) $topbarTone = $legacyMap[$legacyTopbarSkin] ?? 'green';
    if (!in_array($sidebarTone, $allowedUiTones, true)) $sidebarTone = $legacyMap[$legacySidebarSkin] ?? 'green';
}

if (!ensureUserSettingsTable($conn)) {
    http_response_code(500);
    exit('DB init error');
}

$stmt = $conn->prepare(
    "INSERT INTO user_settings (user_id, ui_tone, topbar_tone, sidebar_tone)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        ui_tone = VALUES(ui_tone),
        topbar_tone = VALUES(topbar_tone),
        sidebar_tone = VALUES(sidebar_tone)"
);
$stmt->bind_param('isss', $userId, $topbarTone, $topbarTone, $sidebarTone);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    http_response_code(500);
    exit('DB error');
}

$_SESSION['ui_tone'] = $topbarTone;
$_SESSION['topbar_tone'] = $topbarTone;
$_SESSION['sidebar_tone'] = $sidebarTone;

echo 'success';

