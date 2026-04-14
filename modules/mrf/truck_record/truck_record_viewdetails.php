<?php
require_once dirname(__DIR__, 3) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';

requirePermission('mrf.truck');
require_rate_limit('truck:view:' . (int)($_SESSION['user_id'] ?? 0), 60, 60);

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo "<div class='alert alert-danger m-4 p-3 rounded'>Invalid request.</div>";
    exit;
}

$stmt = $conn->prepare('SELECT * FROM truck_record WHERE id = ? LIMIT 1');
if (!$stmt) {
    echo "<div class='alert alert-danger m-4 p-3 rounded'>Database error.</div>";
    exit;
}

$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    echo "<div class='alert alert-warning m-4 p-3 rounded'>Record not found.</div>";
    exit;
}

$timeIn = new DateTime((string)$row['time_in']);
$timeOut = new DateTime((string)$row['time_out']);
if ($timeOut <= $timeIn) {
    $timeOut->modify('+1 day');
}

$interval = $timeIn->diff($timeOut);
$totalHours = $interval->h + ($interval->i / 60);
$hours = floor($totalHours);
$minutes = round(($totalHours - $hours) * 60);
$duration = $hours . ' hr' . ($minutes > 0 ? ' ' . $minutes . ' min' : '');

$dateCreated = date('F d, Y', strtotime((string)$row['date_created']));

function detailItem(string $iconClass, string $label, string $value): string {
    return '
    <div class="col-md-6 col-lg-4">
      <div class="d-flex align-items-start gap-3 border rounded-4 p-3 shadow-sm bg-white">
        <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-light text-primary" style="width:44px;height:44px;">
          <i class="fa ' . $iconClass . '"></i>
        </div>
        <div class="flex-grow-1">
          <div class="text-muted small fw-semibold text-uppercase">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</div>
          <div class="fs-6 text-dark fw-semibold">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</div>
        </div>
      </div>
    </div>';
}

echo '<div class="container py-4 animate__animated animate__fadeIn">';
echo '<div class="card border-0 shadow-lg rounded-4">';
echo '<div class="card-body bg-light rounded-bottom-4">';
echo '<div class="row g-4">';

echo detailItem('fa-calendar-alt', 'Date Created', $dateCreated);
echo detailItem('fa-truck', 'Truck No.', (string)$row['truck']);
echo detailItem('fa-user', "Driver's Name", (string)$row['name']);
echo detailItem('fa-sign-in-alt', 'Time In', $timeIn->format('h:i A'));
echo detailItem('fa-sign-out-alt', 'Time Out', $timeOut->format('h:i A'));
echo detailItem('fa-clock', 'Total Hours', $duration);
echo detailItem('fa-map-marker-alt', 'Area', (string)$row['area']);
echo detailItem('fa-dumpster', 'Dumping Count', (string)$row['dumping']);

echo '</div></div></div></div>';
?>
