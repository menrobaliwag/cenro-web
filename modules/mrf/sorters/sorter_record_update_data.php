<?php
require_once dirname(__DIR__, 3) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';

requirePermission('mrf.sorters');

// Optional: enable error reporting for debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (isset($_POST['update'])) {
    require_csrf();
    // Get and sanitize POST data
    $id = intval($_POST['edit_id']);
    $name = trim($_POST['name']);
    $mrf = trim($_POST['mrf']);
    $date = $_POST['date'];

    $puti = floatval($_POST['puti'] ?? 0);
    $assorted = floatval($_POST['assorted'] ?? 0);
    $karton = floatval($_POST['karton'] ?? 0);
    $pet = floatval($_POST['pet'] ?? 0);
    $sibak = floatval($_POST['sibak'] ?? 0);
    $lata = floatval($_POST['lata'] ?? 0);
    $aluminum = floatval($_POST['aluminum'] ?? 0);
    $bakal = floatval($_POST['bakal'] ?? 0);
    $yero = floatval($_POST['yero'] ?? 0);
    $glass = floatval($_POST['glass'] ?? 0);

    $stmt = $conn->prepare("
        UPDATE scavenger SET
          name = ?, mrf = ?, date = ?, 
          puti = ?, assorted = ?, karton = ?, pet = ?, sibak = ?, lata = ?, 
          aluminum = ?, bakal = ?, yero = ?, glass = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "sssddddddddddi",
        $name, $mrf, $date,
        $puti, $assorted, $karton, $pet, $sibak, $lata,
        $aluminum, $bakal, $yero, $glass,
        $id
    );

    if ($stmt->execute()) {
        header("Location: index.php?update=success");
        exit();
    } else {
        die("Update failed: " . $stmt->error);
    }

    $stmt->close();
}
?>
