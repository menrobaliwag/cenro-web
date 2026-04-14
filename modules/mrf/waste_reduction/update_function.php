<?php
require_once dirname(__DIR__, 3) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';

requirePermission('mrf.waste_reduction');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $id = intval($_POST['edit_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $date = $_POST['date'] ?? '';
    $other_waste = trim($_POST['other_waste'] ?? '');
    $kgs_before = isset($_POST['kgs_before']) ? floatval($_POST['kgs_before']) : 0;
    $kgs_after = isset($_POST['kgs_after']) ? floatval($_POST['kgs_after']) : 0;

    $stmt = $conn->prepare("UPDATE waste_reduction SET date = ?, name = ?, other_waste = ?, kgs_before = ?, kgs_after = ? WHERE id = ?");
    $stmt->bind_param("sssddi", $date, $name, $other_waste, $kgs_before, $kgs_after, $id);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "Update failed: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>

