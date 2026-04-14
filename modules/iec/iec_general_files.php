<?php
include dirname(__DIR__, 2) . '/modules/iec/includes/iec_bootstrap.php';
requirePermission('iec.manage');

include dirname(__DIR__, 2) . '/includes/header.php';
include dirname(__DIR__, 2) . '/includes/topbar_sidebar.php';

// ✅ identify section for deadline
$section_key = 'general_files';
$section_label = 'General Files';

// ✅ fetch general files
$generalFiles = [];
if ($isAdmin) {
  $stmt = $conn->prepare("
    SELECT g.*, u.id AS uploader_id, u.name AS uploader_name, u.name_enc AS uploader_name_enc
    FROM iec_general_files g
    LEFT JOIN user_form u ON u.id = g.uploaded_by
    WHERE g.is_deleted = 0
    ORDER BY g.created_at DESC
  ");
} else {
  $stmt = $conn->prepare("
    SELECT g.*, u.id AS uploader_id, u.name AS uploader_name, u.name_enc AS uploader_name_enc
    FROM iec_general_files g
    LEFT JOIN user_form u ON u.id = g.uploaded_by
    WHERE g.is_deleted = 0
    AND g.barangay = ?
    ORDER BY g.created_at DESC
  ");
  $stmt->bind_param("s", $myBarangay);
}
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
  $encName = trim((string)($row['uploader_name_enc'] ?? ''));
  if ($encName !== '') {
    $uploaderId = (int)($row['uploader_id'] ?? 0);
    $decryptedName = data_decrypt_text($encName, $uploaderId, 'name');
    if ($decryptedName !== '') {
      $row['uploader_name'] = $decryptedName;
    }
  }
  $generalFiles[] = $row;
}
$stmt->close();
?>

<div class="page-wrapper">
  <div class="page-breadcrumb">
    <div class="row">
      <div class="col-12 d-flex justify-content-between align-items-center">
        <h3 class="mb-0">IEC DATA</h3>
      </div>
    </div>
  </div>

  <div class="container-fluid">
    <?php include dirname(__DIR__, 2) . '/modules/iec/includes/iec_nav.php'; ?>

    <?php include dirname(__DIR__, 2) . '/modules/iec/includes/iec_deadline.php'; ?>

    <div class="card">
      <div class="card-body">

        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h5 class="mb-0">General File Management</h5>
            <div class="text-muted small">PDF / DOC / DOCX — Reports, Letters, Plans, Others</div>
          </div>

          <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addGeneralFileModal">
            <i class="fa fa-upload me-1"></i> Upload File
          </button>
        </div>

        <div class="scrollable-table-wrapper border rounded">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <?php if ($isAdmin): ?><th>Barangay</th><?php endif; ?>
                <th>Title</th>
                <th>Category</th>
                <th>Status</th>
                <th>Date Uploaded</th>
                <th>Uploaded By</th>
                <th>File</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($generalFiles)): ?>
                <tr><td colspan="<?= $isAdmin ? 8 : 7 ?>" class="text-center text-muted py-4">No general files yet.</td></tr>
              <?php else: ?>
                <?php foreach ($generalFiles as $g): ?>
                  <?php
                    $status = $g['status_tag'] ?? 'Pending';
                    $badge = ($status === 'Reviewed') ? 'success' : (($status === 'For Revision') ? 'danger' : 'warning');
                  ?>
                  <tr>
                    <?php if ($isAdmin): ?><td><?= htmlspecialchars($g['barangay']) ?></td><?php endif; ?>
                    <td>
                      <div class="fw-semibold"><?= htmlspecialchars($g['file_title']) ?></div>
                      <?php if (!empty($g['remarks'])): ?><div class="text-muted small"><?= htmlspecialchars($g['remarks']) ?></div><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($g['category']) ?></td>
                    <td><span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($status) ?></span></td>
                    <td class="small"><?= htmlspecialchars(date('F j, Y g:i A', strtotime($g['created_at']))) ?></td>
                    <td class="small"><?= htmlspecialchars($g['uploader_name'] ?? '—') ?></td>
                    <td>
                      <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($g['file_path']) ?>" target="_blank">
                        <i class="fa fa-file me-1"></i><?= strtoupper(htmlspecialchars($g['file_ext'])) ?>
                      </a>
                    </td>
                    <td class="text-end">
                      <!-- actions buttons same as earlier (edit/delete modals) -->
                      <div class="btn-group">
                        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editGeneralFileModal"
                          data-id="<?= (int)$g['id'] ?>"
                          data-title="<?= htmlspecialchars($g['file_title'], ENT_QUOTES) ?>"
                          data-category="<?= htmlspecialchars($g['category'], ENT_QUOTES) ?>"
                          data-status="<?= htmlspecialchars($status, ENT_QUOTES) ?>"
                          data-remarks="<?= htmlspecialchars($g['remarks'] ?? '', ENT_QUOTES) ?>"
                          <?php if ($isAdmin): ?> data-barangay="<?= htmlspecialchars($g['barangay'], ENT_QUOTES) ?>"<?php endif; ?>
                        ><i class="fa fa-pen"></i></button>

                        <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteGeneralFileModal"
                          data-id="<?= (int)$g['id'] ?>"
                          data-title="<?= htmlspecialchars($g['file_title'], ENT_QUOTES) ?>"
                        ><i class="fa fa-trash"></i></button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>

  </div>
</div>

<?php
// ✅ Paste your GENERAL FILES modals here (add/edit/delete) exactly same as earlier
include dirname(__DIR__, 2) . '/includes/footer_scripts.php';
?>
</body>
</html>
