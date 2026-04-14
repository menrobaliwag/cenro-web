<?php
require_once dirname(__DIR__, 3) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';

requirePermission('mrf.waste_reduction');
require_rate_limit('waste_reduction:view:' . (int)($_SESSION['user_id'] ?? 0), 60, 60);

if (!isset($_GET['id'])) {
    echo "<div class='alert alert-danger m-4 p-3 rounded'>Invalid request.</div>";
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo "<div class='alert alert-danger m-4 p-3 rounded'>Invalid request.</div>";
    exit;
}

$row = null;
$stmt = $conn->prepare('SELECT * FROM `waste_reduction` WHERE id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}

if ($row) {
    $date_formatted = date('F d, Y', strtotime((string)($row['date'] ?? '')));

    function detailItem($label, $value)
    {
        return '
        <div class="col-md-6 col-lg-4">
          <div class="d-flex align-items-start gap-3 border rounded-4 p-3 shadow-sm bg-white hover-shadow">
            <div class="flex-grow-1">
              <div class="text-muted small fw-normal text-uppercase">' . htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8') . '</div>
              <div class="fs-6 text-dark">' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '</div>
            </div>
          </div>
        </div>';
    }

    echo '<div class="container py-4">';
    echo '<div class="card border-0 shadow rounded-4">';
    echo '<div class="card-body bg-light rounded-4">';
    echo '<div class="row g-4">';

    echo detailItem('Date', $date_formatted);
    echo detailItem('Name', $row['name']);
    echo detailItem('Other Wastes', $row['other_waste']);
    echo detailItem('Kgs Before', $row['kgs_before']);
    echo detailItem('Kgs After', $row['kgs_after']);

    echo '</div></div></div></div>';
} else {
    echo "<div class='alert alert-warning m-4 p-3 rounded'>Record not found.</div>";
}
?>
