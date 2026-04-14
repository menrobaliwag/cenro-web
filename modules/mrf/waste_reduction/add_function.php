<?php
require_once dirname(__DIR__, 3) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';

requirePermission('mrf.waste_reduction');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name = trim($_POST['name'] ?? '');
    $date = $_POST['date'] ?? '';
    $other_waste = trim($_POST['other_waste'] ?? '');
    $kgs_before = isset($_POST['kgs_before']) ? floatval($_POST['kgs_before']) : 0;
    $kgs_after = isset($_POST['kgs_after']) ? floatval($_POST['kgs_after']) : 0;

    $stmt = $conn->prepare("INSERT INTO waste_reduction (name, date, other_waste, kgs_before, kgs_after) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssdd", $name, $date, $other_waste, $kgs_before, $kgs_after);

    if ($stmt->execute()) {
        header("Location: index.php?success=1");
        exit;
    }

    echo "Error: " . $stmt->error;

    $stmt->close();
    $conn->close();
}
?>

