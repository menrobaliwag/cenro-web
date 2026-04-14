<?php
require_once dirname(__DIR__, 2) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

requirePermission('eco.violators');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo "<div class='alert alert-danger m-4 p-3 rounded'>Invalid request.</div>";
    exit;
}

$stmt = $conn->prepare('SELECT * FROM violations WHERE id = ? LIMIT 1');
if (!$stmt) {
    echo "<div class='alert alert-danger m-4 p-3 rounded'>Database error.</div>";
    exit;
}

$stmt->bind_param('i', $id);
$stmt->execute();
$qry = $stmt->get_result();
$row = $qry ? $qry->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    echo "<div class='alert alert-warning m-4 p-3 rounded'>Record not found.</div>";
    exit;
}

$dateFormatted = date('F d, Y', strtotime((string)$row['date_created']));

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

echo detailItem('fa-calendar-alt', 'Date of Violation', $dateFormatted);
echo detailItem('fa-id-card', 'ID Number', (string)$row['id_number']);
echo detailItem('fa-user', 'Full Name', (string)$row['full_name']);
echo detailItem('fa-home', 'Address', (string)$row['address']);
echo detailItem('fa-phone', 'Contact Number', (string)$row['contact_number']);
echo detailItem('fa-address-card', 'ID Type', (string)$row['id_type']);
echo detailItem('fa-map-marker-alt', 'Place of Violation', (string)$row['place_of_violation']);
echo detailItem('fa-balance-scale', 'Penalty Type', (string)$row['penalty_type']);

echo '</div>';

if (!empty($row['id_image'])) {
    echo '
    <hr class="my-4">
    <h6 class="mb-3"><i class="fa fa-image me-2 text-primary"></i>Uploaded ID Image</h6>
    <div class="text-center">
      <img src="../../uploads/ids/' . htmlspecialchars((string)$row['id_image'], ENT_QUOTES, 'UTF-8') . '"
           class="img-fluid rounded shadow border"
           style="max-height:300px;" alt="Uploaded ID">
    </div>';
}

echo '</div></div></div>';
?>
