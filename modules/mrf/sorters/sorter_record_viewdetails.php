<?php
require_once dirname(__DIR__, 3) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';

requirePermission('mrf.sorters');
require_rate_limit('sorters:view:' . (int)($_SESSION['user_id'] ?? 0), 60, 60);

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
$stmt = $conn->prepare('SELECT * FROM `scavenger` WHERE id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}

if ($row) {

    // Display formatting helper
    function detailItem($icon, $label, $value) {
        return '
        <div class="col-md-6 col-lg-4">
          <div class="d-flex align-items-start gap-3 border rounded-3 p-3 shadow-sm bg-white hover-shadow">
            <i class="bi bi-' . $icon . ' text-primary fs-3"></i>
            <div class="flex-grow-1">
              <div class="text-muted small fw-normal text-uppercase">' . htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8') . '</div>
              <div class="fw-normal fs-6 d-flex align-items-center">' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '</div>
            </div>
          </div>
        </div>';
    }

    // Waste categories
    $waste_fields = [
        'puti' => '📄 Papel Puti',
        'assorted' => '📄 Papel Assorted',
        'karton' => '📦 Karton',
        'pet' => '🥤 PET Bottle',
        'sibak' => '🛍️ Sibak (Plastic)',
        'lata' => '🥫 Lata',
        'aluminum' => '🔩 Aluminum',
        'bakal' => '⚙️ Bakal',
        'yero' => '🪨 Yero',
        'glass' => '🍾 Glass Bottles'
    ];

    $formatted_date = date('F d, Y', strtotime($row['date']));
    $sorter_name = $row['name'];
    $mrf_type = $row['mrf'];

    echo '<div class="container py-6 animate__animated animate__fadeIn">';
    echo '<div class="card border-0 shadow-lg rounded-4">';
    echo '</div>';
    echo '<div class="card-body bg-light rounded-bottom-4">';
    echo '<div class="row g-4">';

    // Sorter Info Boxes
    echo detailItem('person', 'Name of Sorter', $sorter_name);
    echo detailItem('clipboard2-data', 'Type of Sorter', $mrf_type);
    echo detailItem('calendar-date', 'Date', $formatted_date);

    // Waste Category Boxes
    foreach ($waste_fields as $key => $label) {
        $value = floatval($row[$key] ?? 0);
        echo detailItem('recycle', $label, $value . ' kg');
    }

    echo '</div>'; // row
    echo '</div>'; // card-body
    echo '</div>'; // card
    echo '</div>'; // container
} else {
    echo "<div class='alert alert-warning m-4 p-3 rounded'>Record not found.</div>";
}
?>
