<?php
require_once dirname(__DIR__, 3) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';

requirePermission('mrf.truck');

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
$stmt = $conn->prepare('SELECT * FROM `files` WHERE id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}

if ($row) {

    $time_in = new DateTime($row['time_in']);
    $time_out = new DateTime($row['time_out']);

    if ($time_out <= $time_in) {
        $time_out->modify('+1 day');
    }

    $interval = $time_in->diff($time_out);
    $total_hours = $interval->h + ($interval->i / 60);
    $hours = floor($total_hours);
    $minutes = round(($total_hours - $hours) * 60);
    $duration = $hours . ' hr' . ($minutes > 0 ? ' ' . $minutes . ' min' : '');

    $date_created = date('F d, Y', strtotime($row['date_created']));

    // Simplified detail card without copy button
    function detailItem($icon, $label, $value) {
        return '
        <div class="col-md-6 col-lg-4">
          <div class="d-flex align-items-start gap-3 border rounded-3 p-3 shadow-sm bg-white hover-shadow">
            <i class="bi bi-' . $icon . ' text-primary fs-3"></i>
            <div class="flex-grow-1">
              <div class="text-muted small fw-semibold text-uppercase">' . htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8') . '</div>
              <div class="fw-bold fs-6 d-flex align-items-center">' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '</div>
            </div>
          </div>
        </div>';
    }

    echo '<div class="container py-6 animate__animated animate__fadeIn">';
    echo '<div class="card border-0 shadow-lg rounded-4">';
    echo '</div>';
    echo '<div class="card-body bg-light rounded-bottom-4">';
    echo '<div class="row g-4">';

    // Fields in updated order
    echo detailItem('calendar3', 'Date Created', $date_created);
    echo detailItem('truck', 'Truck No.', $row['truck']);
    echo detailItem('person', 'Driver\'s Name', $row['name']);
    echo detailItem('clock', 'Time In', $time_in->format('h:i A'));
    echo detailItem('clock-history', 'Time Out', $time_out->format('h:i A'));
    echo detailItem('hourglass-split', 'Total Hours', $duration);
    echo detailItem('geo-alt', 'Area', $row['area']);
    echo detailItem('trash2', 'Dumping Count', $row['dumping']);

    echo '</div></div></div></div>';
} else {
    echo "<div class='alert alert-warning m-4 p-3 rounded'>Record not found.</div>";
}
?>
