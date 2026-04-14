<?php
require_once dirname(__DIR__, 2) . '/includes/security.php';
secure_session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

requirePermission('eco.violators');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    // REQUIRED: record id
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        die("<div class='alert alert-danger'>Invalid record ID.</div>");
    }

    // Sanitize inputs
    $date_created       = $_POST['date_created'] ?? '';
    $full_name          = trim($_POST['full_name'] ?? '');
    $address            = trim($_POST['address'] ?? '');
    $contact_number     = trim($_POST['contact_number'] ?? '');
    $id_type            = $_POST['id_type'] ?? '';
    $other_id           = trim($_POST['other_id'] ?? '');
    $id_number          = trim($_POST['id_number'] ?? '');
    $place_of_violation = trim($_POST['place_of_violation'] ?? '');
    $penalty_type       = trim($_POST['penalty_type'] ?? '');

    // If "Other" ID Type, override
    if ($id_type === 'Other' && !empty($other_id)) {
        $id_type = $other_id;
    }

    // ---------- CONTACT NUMBER VALIDATION ----------
    if (!preg_match('/^\+639\d{9}$/', $contact_number)) {
        die("<div class='alert alert-danger'>Invalid contact number. Must start with +639 followed by 9 digits.</div>");
    }

    // ---------- GET CURRENT IMAGE (for replace/delete) ----------
    $stmtCur = $conn->prepare("SELECT id_image FROM violations WHERE id = ? LIMIT 1");
    $stmtCur->bind_param("i", $id);
    $stmtCur->execute();
    $resCur = $stmtCur->get_result();
    $current = $resCur->fetch_assoc();
    $stmtCur->close();

    if (!$current) {
        die("<div class='alert alert-danger'>Record not found.</div>");
    }

    $currentImage = $current['id_image'] ?? null;

    // ---------- DUPLICATE ID NUMBER CHECK (exclude current record) ----------
    $stmtCheck = $conn->prepare("SELECT id FROM violations WHERE id_number = ? AND id <> ? LIMIT 1");
    $stmtCheck->bind_param("si", $id_number, $id);
    $stmtCheck->execute();
    $stmtCheck->store_result();

    if ($stmtCheck->num_rows > 0) {
        $stmtCheck->close();
        die("<div class='alert alert-danger'>This ID Number is already registered!</div>");
    }
    $stmtCheck->close();

    // ---------- IMAGE UPLOAD (optional) ----------
    $id_image = $currentImage; // default keep old

    if (!empty($_FILES['id_image']['name'])) {

        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . url_with_base('uploads/ids/');

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            die("<div class='alert alert-danger'>Upload directory error.</div>");
        }

        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
        $check = validate_upload(
            $_FILES['id_image'],
            $allowedExt,
            $allowedMime,
            3 * 1024 * 1024
        );
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file((string)$_FILES['id_image']['tmp_name']) ?: '';
        if (!$check['ok'] || $check['size'] > 3 * 1024 * 1024 || !in_array($mime, $allowedMime, true)) {
            die("<div class='alert alert-danger'>Invalid file.</div>");
        }

        $rawOriginal = basename((string)($_FILES['id_image']['name'] ?? 'id'));
        $safeOriginal = preg_replace('/[^A-Za-z0-9\\._-]+/', '_', $rawOriginal);
        $safeOriginal = ltrim($safeOriginal, '.');
        if (strpos($safeOriginal, '..') !== false || $safeOriginal === '' || $safeOriginal[0] === '/') {
            $safeOriginal = 'id.' . (string)$check['ext'];
        }

        $ext = strtolower(pathinfo($safeOriginal, PATHINFO_EXTENSION));
        $newFileName = 'id_' . bin2hex(random_bytes(8)) . '.' . $ext;

        if (!move_uploaded_file($_FILES['id_image']['tmp_name'], $uploadDir . $newFileName)) {
            die("<div class='alert alert-danger'>Failed to upload image.</div>");
        }

        // If may lumang image, optional delete (para di dumami sa uploads)
        if (!empty($currentImage) && file_exists($uploadDir . $currentImage)) {
            @unlink($uploadDir . $currentImage);
        }

        $id_image = $newFileName;
    }

    // ---------- UPDATE ----------
    $stmt = $conn->prepare("
        UPDATE violations
        SET date_created = ?,
            full_name = ?,
            address = ?,
            contact_number = ?,
            id_type = ?,
            id_number = ?,
            place_of_violation = ?,
            penalty_type = ?,
            id_image = ?
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "sssssssssi",
        $date_created,
        $full_name,
        $address,
        $contact_number,
        $id_type,
        $id_number,
        $place_of_violation,
        $penalty_type,
        $id_image,
        $id
    );

    if ($stmt->execute()) {
        header("Location: index.php?updated=1");
        exit;
    } else {
        echo "Database error: " . $stmt->error;
    }
}
?>
