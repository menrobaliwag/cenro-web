<?php
require_once dirname(__DIR__, 3) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';

requirePermission('mrf.other_waste');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id       = intval($_POST['edit_id']);
    $date     = $_POST['date'];
    $name     = $_POST['name'];
    $eco      = floatval($_POST['eco']);
    $pavement = floatval($_POST['pavement']);
    $cement   = floatval($_POST['cement']);
    $coal     = floatval($_POST['coal']);
    $hollow   = floatval($_POST['hollow']);

    $stmt = $conn->prepare("UPDATE waste SET date = ?, name = ?, eco = ?, pavement = ?, cement = ?, coal = ?, hollow = ? WHERE id = ?");
    $stmt->bind_param("ssdddddi", $date, $name, $eco, $pavement, $cement, $coal, $hollow, $id);

    if ($stmt->execute()) {
        echo "success"; // <-- required by JS
    } else {
        echo "Update failed: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
