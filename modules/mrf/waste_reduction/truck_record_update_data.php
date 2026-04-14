<?php
require_once dirname(__DIR__, 3) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';

requirePermission('mrf.truck');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $id = $_POST['id'];
    $date = $_POST['date'];
    $time_in = $_POST['time_in'];
    $time_out = $_POST['time_out'];
    $truck = $_POST['truck'];
    $name = $_POST['name'];
    $area = $_POST['area'];
    $dumping = $_POST['dumping'];

    // Prepare statement with 8 variables → 7 strings + 1 integer
    $stmt = $conn->prepare("UPDATE files SET date_created = ?, time_in = ?, time_out = ?, truck = ?, name = ?, area = ?, dumping = ? WHERE id = ?");

    if (!$stmt) {
        echo "DB error: " . $conn->error;
        exit;
    }

    // Correct bind_param: 7 strings ('s') + 1 integer ('i')
    $stmt->bind_param("sssssssi", $date, $time_in, $time_out, $truck, $name, $area, $dumping, $id);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "DB error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
