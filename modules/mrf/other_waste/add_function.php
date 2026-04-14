<?php
require_once dirname(__DIR__, 3) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';

requirePermission('mrf.other_waste');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    // Sanitize and fetch values
    $name     = trim($_POST['name']);
    $date     = $_POST['date'];
    $eco      = isset($_POST['eco']) ? floatval($_POST['eco']) : 0;
    $pavement = isset($_POST['pavement']) ? floatval($_POST['pavement']) : 0;
    $cement   = isset($_POST['cement']) ? floatval($_POST['cement']) : 0;
    $coal     = isset($_POST['coal']) ? floatval($_POST['coal']) : 0;
    $hollow   = isset($_POST['hollow']) ? floatval($_POST['hollow']) : 0;

    // Prepare SQL insert
    $stmt = $conn->prepare("INSERT INTO waste (name, date, eco, pavement, cement, coal, hollow) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssddddd", $name, $date, $eco, $pavement, $cement, $coal, $hollow);

    // Execute query
    if ($stmt->execute()) {
        header("Location: index.php?success=1");
        exit;
    } else {
        echo "❌ Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
