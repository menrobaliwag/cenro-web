<?php
require_once dirname(__DIR__, 3) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';

requirePermission('mrf.other_waste');
require_rate_limit('other_waste:view:' . (int)($_SESSION['user_id'] ?? 0), 60, 60);

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo "<div class='alert alert-danger m-4 p-3 rounded'>Invalid request.</div>";
    exit;
}

$stmt = $conn->prepare('SELECT * FROM waste WHERE id = ? LIMIT 1');
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

$dateFormatted = date('F d, Y', strtotime((string)$row['date']));

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
echo '<div class="card border-0 shadow rounded-4">';
echo '<div class="card-body bg-light rounded-4">';
echo '<div class="row g-4">';

echo detailItem('fa-calendar-alt', 'Date', $dateFormatted);
echo detailItem('fa-user', 'Scavenger Name', (string)$row['name']);
echo detailItem('fa-cubes', 'Eco Bricks (kg)', (string)$row['eco']);
echo detailItem('fa-road', 'Pavement Bricks (kg)', (string)$row['pavement']);
echo detailItem('fa-building', 'Cement Bricks (kg)', (string)$row['cement']);
echo detailItem('fa-fire', 'Charcoal Briquette (kg)', (string)$row['coal']);
echo detailItem('fa-box', 'Hollow Blocks (kg)', (string)$row['hollow']);

echo '</div></div></div></div>';
?>
