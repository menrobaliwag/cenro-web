<?php
require_once dirname(__DIR__, 3) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';

requirePermission('mrf.truck');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_csrf();
    $date_created = $_POST['date_created'];
    $time_in = $_POST['time_in'];
    $time_out = $_POST['time_out'];
    $truck = $_POST['truck'];
    $name = $_POST['name'];
    $area = $_POST['area'];
    $dumping = $_POST['dumping'];

    // Calculate total hours
    $in = strtotime($time_in);
    $out = strtotime($time_out);
    $total_hours = round(($out - $in) / 3600, 2); // convert to hours

    $stmt = $conn->prepare("INSERT INTO files (date_created, time_in, time_out, total_hours, truck, name, area, dumping) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssdsssi", $date_created, $time_in, $time_out, $total_hours, $truck, $name, $area, $dumping);

    if ($stmt->execute()) {
        header("Location: truck_record_table.php"); // redirect to the main page
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
