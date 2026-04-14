<?php
require_once dirname(__DIR__, 3) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';

requirePermission('mrf.sorters');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    require_csrf();

    $name       = trim($_POST['name']);
    $date       = $_POST['date'];
    $mrf        = $_POST['mrf'];
    $type       = 'Sorters Waste';
    $puti       = $_POST['puti'] ?? 0;
    $assorted   = $_POST['assorted'] ?? 0;
    $karton     = $_POST['karton'] ?? 0;
    $pet        = $_POST['pet'] ?? 0;
    $sibak      = $_POST['sibak'] ?? 0;
    $lata       = $_POST['lata'] ?? 0;
    $bakal      = $_POST['bakal'] ?? 0;
    $aluminum   = $_POST['aluminum'] ?? 0;
    $yero       = $_POST['yero'] ?? 0;
    $glass      = $_POST['glass'] ?? 0;

    $stmt = $conn->prepare("INSERT INTO scavenger 
        (`name`, `date`, `mrf`, `type`, `puti`, `assorted`, `karton`, `pet`, `sibak`, `lata`, `bakal`, `aluminum`, `yero`, `glass`) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    if ($stmt) {
        $stmt->bind_param(
            "ssssdddddddddd",
            $name, $date, $mrf, $type,
            $puti, $assorted, $karton, $pet, $sibak,
            $lata, $bakal, $aluminum, $yero, $glass
        );

        if ($stmt->execute()) {
            $_SESSION['success'] = "Record successfully added!";
        } else {
            $_SESSION['error'] = "Failed to add record: " . $stmt->error;
        }

        $stmt->close();
    } else {
        $_SESSION['error'] = "Database error: " . $conn->error;
    }

    header("Location: index.php");
    exit();
} else {
    header("Location: index.php");
    exit();
}
