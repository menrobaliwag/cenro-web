<?php
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';

requirePermission('iec.manage');

include dirname(__DIR__, 2) . '/includes/header.php';
include dirname(__DIR__, 2) . '/includes/topbar_sidebar.php';

function formatReadableDate($raw) {
  return !empty($raw) ? date('F j, Y', strtotime($raw)) : '';
}

function safePrepare($conn, $sql) {
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    die("Prepare failed: " . $conn->error . "<br><pre>$sql</pre>");
  }
  return $stmt;
}

/* =========================================================
   ✅ ROLE CONTEXT (HEAD ADMIN / IEC ADMIN / IEC SECRETARY)
========================================================= */
$roleKey = rbac_current_role();
$isHeadAdmin = is_super_role($roleKey); // head admin / admin / super roles
$isIecAdmin = ($roleKey === 'iec_admin');
$isIecSecretary = ($roleKey === 'iec_secretary');

$myBarangay = trim((string)($_SESSION['barangay'] ?? ''));

// ✅ barangay required ONLY for IEC secretary accounts
if (!$isHeadAdmin && !$isIecAdmin && $myBarangay === '') {
  die('No barangay assigned to this account.');
}

/* =========================================================
   ✅ date filter (optional)
========================================================= */
$raw_start = $_GET['date_start'] ?? '';
$raw_end   = $_GET['date_end'] ?? '';
$date_start = !empty($raw_start) ? date('Y-m-d', strtotime($raw_start)) : date('Y-m-d');
$date_end   = !empty($raw_end)   ? date('Y-m-d', strtotime($raw_end))   : date('Y-m-d');

/* =========================================================
   ✅ SECTION SELECT via URL param
========================================================= */
$allowedSections = [
  'iec_activities'  => 'IEC Activities',
  'board_docs'      => 'Board Docs',
  'general_files'   => 'General Files',
  'minutes_meeting' => 'Minutes of Meeting',
  'cleanup_drive'   => 'Clean-Up Drive'
];

$section_key = $_GET['section'] ?? 'iec_activities';
if (!array_key_exists($section_key, $allowedSections)) {
  $section_key = 'iec_activities';
}

/* =========================================================
   ✅ DEADLINE FETCH based on selected section
   - Head Admin / IEC Admin => global deadline
   - Barangay user => prefer barangay-specific else global
========================================================= */
$deadlineRow = null;

if ($isHeadAdmin || $isIecAdmin) {
  $stmtDL = safePrepare($conn, "
    SELECT *
    FROM iec_deadlines
    WHERE section_key = ?
      AND scope = 'global'
    ORDER BY deadline_date DESC
    LIMIT 1
  ");
  $stmtDL->bind_param("s", $section_key);
} else {
  $stmtDL = safePrepare($conn, "
    SELECT d.*
    FROM iec_deadlines d
    LEFT JOIN user_form u ON u.id = d.created_by
    LEFT JOIN roles r ON r.id = u.role_id
    WHERE d.section_key = ?
      AND ( (d.scope='barangay' AND d.barangay=?) OR d.scope='global' )
      AND r.role_name = 'iec_admin'
    ORDER BY
      CASE WHEN d.scope='barangay' THEN 0 ELSE 1 END,
      d.deadline_date DESC,
      d.created_at DESC
    LIMIT 1
  ");
  $stmtDL->bind_param("ss", $section_key, $myBarangay);
}

$stmtDL->execute();
$deadlineRow = $stmtDL->get_result()->fetch_assoc();
$stmtDL->close();

$deadlineDate  = $deadlineRow['deadline_date'] ?? null;
$deadlineNotes = $deadlineRow['notes'] ?? '';

/* =========================================================
   ✅ SECTION 1: BOARD DOCS LIST
========================================================= */
$docs = [];
if ($section_key === 'board_docs') {

  // Head Admin / IEC Admin => all barangays
  if ($isHeadAdmin || $isIecAdmin) {
    $stmt = safePrepare($conn, "
      SELECT d.*, u.id AS uploader_id, u.name AS uploader_name, u.name_enc AS uploader_name_enc
      FROM iec_board_docs d
      LEFT JOIN user_form u ON u.id = d.uploaded_by
      WHERE d.is_deleted = 0
      ORDER BY d.date_issued DESC, d.created_at DESC
    ");
  } else {
    // Barangay secretary => own barangay only
    $stmt = safePrepare($conn, "
      SELECT d.*, u.id AS uploader_id, u.name AS uploader_name, u.name_enc AS uploader_name_enc
      FROM iec_board_docs d
      LEFT JOIN user_form u ON u.id = d.uploaded_by
      WHERE d.is_deleted = 0 AND d.barangay = ?
      ORDER BY d.date_issued DESC, d.created_at DESC
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
    $docs[] = $row;
  }
  $stmt->close();
}

/* =========================================================
   ✅ SECTION 2: GENERAL FILES LIST
========================================================= */
$generalFiles = [];
if ($section_key === 'general_files') {

  if ($isHeadAdmin || $isIecAdmin) {
    $stmt = safePrepare($conn, "
      SELECT g.*, u.id AS uploader_id, u.name AS uploader_name, u.name_enc AS uploader_name_enc
      FROM iec_general_files g
      LEFT JOIN user_form u ON u.id = g.uploaded_by
      WHERE g.file_path <> ''
      ORDER BY g.uploaded_at DESC
    ");
  } else {
    $stmt = safePrepare($conn, "
      SELECT g.*, u.id AS uploader_id, u.name AS uploader_name, u.name_enc AS uploader_name_enc
      FROM iec_general_files g
      LEFT JOIN user_form u ON u.id = g.uploaded_by
      WHERE g.file_path <> '' AND g.barangay = ?
      ORDER BY g.uploaded_at DESC
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
}

/* =========================================================
   ✅ SECTION 3: MINUTES OF MEETING LIST
========================================================= */
$minutesList = [];
if ($section_key === 'minutes_meeting') {

  if ($isHeadAdmin || $isIecAdmin) {
    $stmt = safePrepare($conn, "
      SELECT m.*, u.id AS uploader_id, u.name AS uploader_name, u.name_enc AS uploader_name_enc
      FROM iec_minutes_meeting m
      LEFT JOIN user_form u ON u.id = m.uploaded_by
      WHERE m.is_deleted = 0
      ORDER BY
        m.year DESC,
        FIELD(m.quarter,'Q4','Q3','Q2','Q1'),
        m.meeting_date DESC,
        m.created_at DESC
    ");
  } else {
    $stmt = safePrepare($conn, "
      SELECT m.*, u.id AS uploader_id, u.name AS uploader_name, u.name_enc AS uploader_name_enc
      FROM iec_minutes_meeting m
      LEFT JOIN user_form u ON u.id = m.uploaded_by
      WHERE m.is_deleted = 0 AND m.barangay = ?
      ORDER BY
        m.year DESC,
        FIELD(m.quarter,'Q4','Q3','Q2','Q1'),
        m.meeting_date DESC,
        m.created_at DESC
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
    $minutesList[] = $row;
  }
  $stmt->close();
}
/* =========================================================
   ✅ SECTION 4: CLEAN-UP DRIVE LIST
========================================================= */
$cleanupList = [];
if ($section_key === 'cleanup_drive') {

  // Optional date filter use $date_start/$date_end (already defined above)
  if ($isHeadAdmin || $isIecAdmin) {
    $stmt = safePrepare($conn, "
      SELECT c.*, u.id AS creator_id, u.name AS creator_name, u.name_enc AS creator_name_enc
      FROM iec_cleanup_drive c
      LEFT JOIN user_form u ON u.id = c.created_by
      WHERE c.is_deleted = 0
        AND c.activity_date BETWEEN ? AND ?
      ORDER BY c.activity_date DESC, c.created_at DESC
    ");
    $stmt->bind_param("ss", $date_start, $date_end);
  } else {
    $stmt = safePrepare($conn, "
      SELECT c.*, u.id AS creator_id, u.name AS creator_name, u.name_enc AS creator_name_enc
      FROM iec_cleanup_drive c
      LEFT JOIN user_form u ON u.id = c.created_by
      WHERE c.is_deleted = 0
        AND c.barangay = ?
        AND c.activity_date BETWEEN ? AND ?
      ORDER BY c.activity_date DESC, c.created_at DESC
    ");
    $stmt->bind_param("sss", $myBarangay, $date_start, $date_end);
  }

  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $encName = trim((string)($row['creator_name_enc'] ?? ''));
    if ($encName !== '') {
      $creatorId = (int)($row['creator_id'] ?? 0);
      $decryptedName = data_decrypt_text($encName, $creatorId, 'name');
      if ($decryptedName !== '') {
        $row['creator_name'] = $decryptedName;
      }
    }
    $cleanupList[] = $row;
  }
  $stmt->close();
}
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

    <!-- ✅ SECTION BUTTONS -->
    <div class="mb-3 d-flex flex-wrap gap-2">
      <?php foreach ($allowedSections as $key => $label): ?>
        <?php
          $qs = $_GET;
          $qs['section'] = $key;
          $href = '?' . http_build_query($qs);
          $btnClass = ($section_key === $key) ? 'btn-primary' : 'btn-outline-primary';
        ?>
        <a class="btn btn-sm <?= $btnClass ?>" href="<?= htmlspecialchars($href) ?>">
          <?= htmlspecialchars($label) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- ✅ DEADLINE ALERT -->
    <?php if (!empty($deadlineDate)): ?>
      <?php
        $today = new DateTime(date('Y-m-d'));
        $dl = new DateTime($deadlineDate);
        $diffDays = (int)$today->diff($dl)->format('%r%a');
        $badge = $diffDays < 0 ? 'danger' : ($diffDays <= 7 ? 'warning' : 'info');
      ?>
      <div class="alert alert-<?= $badge ?> alert-permanent d-flex justify-content-between align-items-center">
        <div>
          <div>
            <b><?= htmlspecialchars($allowedSections[$section_key]) ?> Deadline:</b>
            <?= htmlspecialchars(date('F j, Y', strtotime($deadlineDate))) ?>
          </div>

          <?php if ($deadlineNotes): ?>
            <div class="small mt-1"><?= htmlspecialchars($deadlineNotes) ?></div>
          <?php endif; ?>

          <div class="small mt-1">
            <?php if ($diffDays < 0): ?>
              <span class="badge bg-danger">PAST DUE (<?= abs($diffDays) ?> day/s ago)</span>
            <?php else: ?>
              <span class="badge bg-<?= $badge ?>">DUE IN <?= $diffDays ?> day/s</span>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($isHeadAdmin || $isIecAdmin): ?>
          <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addDeadlineModal">
            <i class="fa fa-clock me-1"></i> Set Deadline
          </button>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="alert alert-secondary alert-permanent d-flex justify-content-between align-items-center">
        <div><b>No deadline set</b> for: <b><?= htmlspecialchars($allowedSections[$section_key]) ?></b></div>
        <?php if ($isHeadAdmin || $isIecAdmin): ?>
          <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addDeadlineModal">
            <i class="fa fa-plus me-1"></i> Add Deadline
          </button>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="card fade-in">
      <div class="card-body">

        <!-- =========================================================
             ✅ SECTION 1: BOARD DOCS
        ========================================================= -->
        <?php if ($section_key === 'board_docs'): ?>

          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h5 class="mb-0">Barangay Solid Waste Management Board</h5>
              <div class="text-muted small">PDF / DOC / DOCX — Resolution, EO, Ordinance, Others</div>
            </div>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addBoardDocModal">
              <i class="fa fa-upload me-1"></i> Upload Document
            </button>
          </div>

          <div class="scrollable-table-wrapper border rounded">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <?php if ($isHeadAdmin || $isIecAdmin): ?><th>Barangay</th><?php endif; ?>
                  <th>Title</th>
                  <th>Type</th>
                  <th>Date Issued</th>
                  <?php if ($isHeadAdmin || $isIecAdmin): ?><th>Uploaded By</th><?php endif; ?>
                  <th>File</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($docs)): ?>
                <?php
                $colspan = ($isHeadAdmin || $isIecAdmin) ? 7 : 5;
                ?>
                <tr>
                  <td colspan="<?= $colspan ?>" class="text-center text-muted py-4">
                      No documents yet.
                  </td>
                </tr>
                <?php else: ?>
                  <?php foreach ($docs as $d): ?>
                    <tr>
                      <?php if ($isHeadAdmin || $isIecAdmin): ?><td><?= htmlspecialchars($d['barangay']) ?></td><?php endif; ?>
                      <td>
                        <div class="fw-semibold"><?= htmlspecialchars($d['doc_title']) ?></div>
                        <?php if (!empty($d['notes'])): ?><div class="text-muted small"><?= htmlspecialchars($d['notes']) ?></div><?php endif; ?>
                      </td>
                      <td><?= htmlspecialchars($d['doc_type']) ?></td>
                      <td><?= !empty($d['date_issued']) ? htmlspecialchars(date('F j, Y', strtotime($d['date_issued']))) : '-' ?></td>
                      <?php if ($isHeadAdmin || $isIecAdmin): ?><td class="small"><?= htmlspecialchars($d['uploader_name'] ?? '—') ?></td><?php endif; ?>
                      <td>
                          <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(download_signed_url($d['file_path'])) ?>" target="_blank">
                          <i class="fa fa-file me-1"></i><?= strtoupper(htmlspecialchars($d['file_ext'])) ?>
                        </a>
                      </td>
                     <td class="text-end">
                       <div class="dropdown dropup">
                         <button class="btn btn-outline-dark btn-sm dropdown-toggle rounded-pill px-2"
                                 type="button"
                                 data-bs-toggle="dropdown"
                                 aria-expanded="false">
                           <i class="fa fa-cog spinning-gear always-spin"></i>
                         </button>
                         <ul class="dropdown-menu dropdown-menu-end shadow rounded-3 p-1">
                           <li>
                             <button class="dropdown-item d-flex align-items-center gap-2 rounded-2"
                                     type="button"
                                     data-bs-toggle="modal"
                                     data-bs-target="#editBoardDocModal"
                                     data-id="<?= (int)$d['id'] ?>"
                                     data-title="<?= htmlspecialchars($d['doc_title'], ENT_QUOTES) ?>"
                                     data-type="<?= htmlspecialchars($d['doc_type'], ENT_QUOTES) ?>"
                                     data-date="<?= htmlspecialchars($d['date_issued'] ?? '', ENT_QUOTES) ?>"
                                     data-notes="<?= htmlspecialchars($d['notes'] ?? '', ENT_QUOTES) ?>"
                                     <?php if ($isHeadAdmin || $isIecAdmin): ?>
                                       data-barangay="<?= htmlspecialchars($d['barangay'], ENT_QUOTES) ?>"
                                     <?php endif; ?>>
                               <i class="fa fa-pen text-primary"></i>
                               <span>Edit</span>
                             </button>
                           </li>

                           <li><hr class="dropdown-divider my-1"></li>

                           <li>
                             <button class="dropdown-item d-flex align-items-center gap-2 rounded-2 text-danger"
                                     type="button"
                                     data-bs-toggle="modal"
                                     data-bs-target="#deleteBoardDocModal"
                                     data-id="<?= (int)$d['id'] ?>"
                                     data-title="<?= htmlspecialchars($d['doc_title'], ENT_QUOTES) ?>">
                               <i class="fa fa-trash"></i>
                               <span>Delete</span>
                             </button>
                           </li>
                         </ul>
                       </div>
                     </td>
                     </tr>
                   <?php endforeach; ?>
                 <?php endif; ?>
              </tbody>
            </table>
          </div>

        <!-- =========================================================
             ✅ SECTION 2: GENERAL FILES
        ========================================================= -->
        <?php elseif ($section_key === 'general_files'): ?>

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
                  <?php if ($isHeadAdmin || $isIecAdmin): ?><th>Barangay</th><?php endif; ?>
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
                <?php
                $colspan = ($isHeadAdmin || $isIecAdmin) ? 8 : 7;
                ?>
                <tr>
                  <td colspan="<?= $colspan ?>" class="text-center text-muted py-4">
                      No documents yet.
                  </td>
                </tr>
                <?php else: ?>
                  <?php foreach ($generalFiles as $g): ?>
                    <?php
                      $status = $g['status_tag'] ?? 'Pending';
                      $badge = ($status==='Reviewed') ? 'success' : (($status==='For Revision') ? 'danger' : 'warning');
                    ?>
                    <tr>
                      <?php if ($isHeadAdmin || $isIecAdmin): ?><td><?= htmlspecialchars($g['barangay']) ?></td><?php endif; ?>
                      <td>
                        <div class="fw-semibold"><?= htmlspecialchars($g['file_title']) ?></div>
                        <?php if (!empty($g['remarks'])): ?><div class="text-muted small"><?= htmlspecialchars($g['remarks']) ?></div><?php endif; ?>
                      </td>
                      <td><?= htmlspecialchars($g['category']) ?></td>
                      <td><span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($status) ?></span></td>
                      <td class="small"><?= htmlspecialchars(date('F j, Y g:i A', strtotime($g['uploaded_at'] ?? 'now'))) ?></td>
                      <td class="small"><?= htmlspecialchars($g['uploader_name'] ?? '—') ?></td>
                      <td>
                          <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(download_signed_url($g['file_path'])) ?>" target="_blank">
                          <i class="fa fa-file me-1"></i><?= strtoupper(htmlspecialchars($g['file_ext'])) ?>
                        </a>
                      </td>
                       <td class="text-end">
                         <div class="dropdown dropup">
                           <button class="btn btn-outline-dark btn-sm dropdown-toggle rounded-pill px-2"
                                   type="button"
                                   data-bs-toggle="dropdown"
                                   aria-expanded="false">
                             <i class="fa fa-cog spinning-gear always-spin"></i>
                           </button>
                           <ul class="dropdown-menu dropdown-menu-end shadow rounded-3 p-1">
                             <li>
                               <button class="dropdown-item d-flex align-items-center gap-2 rounded-2"
                                       type="button"
                                       data-bs-toggle="modal"
                                       data-bs-target="#editGeneralFileModal"
                                       data-id="<?= (int)$g['id'] ?>"
                                       data-title="<?= htmlspecialchars($g['file_title'], ENT_QUOTES) ?>"
                                       data-category="<?= htmlspecialchars($g['category'], ENT_QUOTES) ?>"
                                       data-status="<?= htmlspecialchars($status, ENT_QUOTES) ?>"
                                       data-remarks="<?= htmlspecialchars($g['remarks'] ?? '', ENT_QUOTES) ?>"
                                       <?php if ($isHeadAdmin || $isIecAdmin): ?>
                                         data-barangay="<?= htmlspecialchars($g['barangay'], ENT_QUOTES) ?>"
                                       <?php endif; ?>>
                                 <i class="fa fa-pen text-primary"></i>
                                 <span>Edit</span>
                               </button>
                             </li>
                             <li><hr class="dropdown-divider my-1"></li>
                             <li>
                               <button class="dropdown-item d-flex align-items-center gap-2 rounded-2 text-danger"
                                       type="button"
                                       data-bs-toggle="modal"
                                       data-bs-target="#deleteGeneralFileModal"
                                       data-id="<?= (int)$g['id'] ?>"
                                       data-title="<?= htmlspecialchars($g['file_title'], ENT_QUOTES) ?>">
                                 <i class="fa fa-trash"></i>
                                 <span>Delete</span>
                               </button>
                             </li>
                           </ul>
                         </div>
                       </td>
                     </tr>
                   <?php endforeach; ?>
                 <?php endif; ?>
               </tbody>
             </table>
           </div>
        <!-- =========================================================
             ✅ SECTION 3: MINUTES OF MEETING
         ========================================================= -->
        <?php elseif ($section_key === 'minutes_meeting'): ?>

          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h5 class="mb-0">Minutes of the Meeting</h5>
              <div class="text-muted small">PDF only — multiple submission per quarter</div>
            </div>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMinutesModal">
              <i class="fa fa-upload me-1"></i> Submit Minutes (PDF)
            </button>
          </div>

          <div class="scrollable-table-wrapper border rounded">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <?php if ($isHeadAdmin || $isIecAdmin): ?><th>Barangay</th><?php endif; ?>
                  <th>Quarter</th>
                  <th>Year</th>
                  <th>Meeting Date</th>
                  <th>Prepared By</th>
                  <th>Status</th>
                  <th>Submitted</th>
                  <th>File</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if(empty($minutesList)): ?>
                  <?php
                  $colspan = ($isHeadAdmin || $isIecAdmin) ? 9 : 8;
                  ?>
                  <tr>
                    <td colspan="<?= $colspan ?>" class="text-center text-muted py-4">
                      No documents yet.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach($minutesList as $m): ?>
                    <?php
                      $st = $m['submission_status'] ?? 'Submitted';
                      $badge = 'info';
                      if($st==='Approved') $badge='success';
                      elseif($st==='For Revision') $badge='danger';
                      elseif($st==='Late Submission') $badge='warning';
                      elseif($st==='Pending') $badge='secondary';
                    ?>
                    <tr>
                      <?php if ($isHeadAdmin || $isIecAdmin): ?><td><?= htmlspecialchars($m['barangay']) ?></td><?php endif; ?>
                      <td><?= htmlspecialchars($m['quarter']) ?></td>
                      <td><?= (int)$m['year'] ?></td>
                      <td><?= htmlspecialchars(date('F j, Y', strtotime($m['meeting_date']))) ?></td>
                      <td><?= htmlspecialchars($m['prepared_by']) ?></td>
                      <td><span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($st) ?></span></td>
                      <td class="small"><?= htmlspecialchars(date('F j, Y g:i A', strtotime($m['created_at']))) ?></td>
                      <td>
                          <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(download_signed_url($m['file_path'])) ?>" target="_blank">
                          <i class="fa fa-file-pdf me-1"></i>PDF
                        </a>
                      </td>
                      <td class="text-end">
                        <div class="dropdown dropup">
                          <button class="btn btn-outline-dark btn-sm dropdown-toggle rounded-pill px-2"
                                  type="button"
                                  data-bs-toggle="dropdown"
                                  aria-expanded="false">
                            <i class="fa fa-cog spinning-gear always-spin"></i>
                          </button>
                          <ul class="dropdown-menu dropdown-menu-end shadow rounded-3 p-1">
                            <li>
                              <button class="dropdown-item d-flex align-items-center gap-2 rounded-2"
                                      type="button"
                                      data-bs-toggle="modal"
                                      data-bs-target="#editMinutesModal"
                                      data-id="<?= (int)$m['id'] ?>"
                                      data-quarter="<?= htmlspecialchars($m['quarter'], ENT_QUOTES) ?>"
                                      data-year="<?= (int)$m['year'] ?>"
                                      data-meeting_date="<?= htmlspecialchars($m['meeting_date'], ENT_QUOTES) ?>"
                                      data-prepared_by="<?= htmlspecialchars($m['prepared_by'], ENT_QUOTES) ?>"
                                      data-status="<?= htmlspecialchars($st, ENT_QUOTES) ?>"
                                      <?php if ($isHeadAdmin || $isIecAdmin): ?>
                                        data-barangay="<?= htmlspecialchars($m['barangay'], ENT_QUOTES) ?>"
                                      <?php endif; ?>>
                                <i class="fa fa-pen text-primary"></i>
                                <span>Edit</span>
                              </button>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li>
                              <button class="dropdown-item d-flex align-items-center gap-2 rounded-2 text-danger"
                                      type="button"
                                      data-bs-toggle="modal"
                                      data-bs-target="#deleteMinutesModal"
                                      data-id="<?= (int)$m['id'] ?>"
                                      data-title="<?= htmlspecialchars($m['quarter'] . ' ' . $m['year'] . ' - ' . $m['meeting_date'], ENT_QUOTES) ?>">
                                <i class="fa fa-trash"></i>
                                <span>Delete</span>
                              </button>
                            </li>
                          </ul>
                        </div>
                      </td>
                    </tr>
                              <?php endforeach; ?>
                            <?php endif; ?>
                          </tbody>
                        </table>
                      </div>
                                                             

                  <!-- ========================================================
                              ✅ SECTION 4: CLEAN-UP DRIVE
                  ========================================================= -->
                  <?php elseif ($section_key === 'cleanup_drive'): ?>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <div>
                        <h5 class="mb-0">Weekly Clean-Up Drive</h5>
                        <div class="text-muted small">Multi-photo gallery + attendance proof (image/doc)</div>
                      </div>

                      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCleanupModal">
                        <i class="fa fa-plus me-1"></i> Add Activity
                      </button>
                    </div>

                    <div class="scrollable-table-wrapper border rounded">
                      <table class="table table-hover align-middle mb-0">
                        <thead>
                          <tr>
                            <?php if ($isHeadAdmin || $isIecAdmin): ?><th>Barangay</th><?php endif; ?>
                            <th>Title</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Venue</th>
                            <th>Participants</th>
                            <th>Status</th>
                            <th>Attendance</th>
                            <th class="text-end">Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php if (empty($cleanupList)): ?>
                            <?php $colspan = ($isHeadAdmin || $isIecAdmin) ? 9 : 8; ?>
                            <tr>
                              <td colspan="<?= $colspan ?>" class="text-center text-muted py-4">
                                No clean-up drive submissions yet.
                              </td>
                            </tr>
                          <?php else: ?>
                            <?php foreach ($cleanupList as $c): ?>
                              <?php
                                $st = $c['status'] ?? 'Submitted';
                                $badge = 'info';
                                if ($st === 'Approved') $badge = 'success';
                                elseif ($st === 'For Revision') $badge = 'danger';
                                elseif ($st === 'Draft') $badge = 'secondary';
                                elseif ($st === 'Submitted') $badge = 'warning';
                                $attType = $c['attendance_type'] ?? '';
                                $attPath = $c['attendance_path'] ?? '';
                              ?>
                              <tr>
                                <?php if ($isHeadAdmin || $isIecAdmin): ?><td><?= htmlspecialchars($c['barangay']) ?></td><?php endif; ?>
                                <td>
                                  <div class="fw-semibold"><?= htmlspecialchars($c['activity_title']) ?></div>
                                  <?php if (!empty($c['description'])): ?>
                                    <div class="text-muted small text-truncate" style="max-width:420px;">
                                      <?= htmlspecialchars($c['description']) ?>
                                    </div>
                                  <?php endif; ?>
                                </td>
                                <td class="small"><?= htmlspecialchars(date('F j, Y', strtotime($c['activity_date']))) ?></td>
                                <td class="small"><?= htmlspecialchars(date('g:i A', strtotime($c['activity_time']))) ?></td>
                                <td><?= htmlspecialchars($c['venue']) ?></td>
                                <td><?= (int)$c['participants'] ?></td>
                                <td><span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($st) ?></span></td>
                                <td>
                                  <?php if (!empty($attPath)): ?>
                                      <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars(download_signed_url($attPath)) ?>" target="_blank">
                                      <i class="fa fa-paperclip me-1"></i><?= htmlspecialchars(strtoupper($attType ?: 'FILE')) ?>
                                    </a>
                                  <?php else: ?>
                                    <span class="text-muted small">—</span>
                                  <?php endif; ?>
                                </td>
                              <td class="text-end">
                                <div class="dropdown dropup">
                                  <button class="btn btn-outline-dark btn-sm dropdown-toggle rounded-pill px-2"
                                          type="button"
                                          data-bs-toggle="dropdown"
                                          aria-expanded="false">
                                    <i class="fa fa-cog spinning-gear always-spin"></i>
                                  </button>
                                  <ul class="dropdown-menu dropdown-menu-end shadow rounded-3 p-1">
                                    <!-- VIEW -->
                                    <li>
                                      <button class="dropdown-item d-flex align-items-center gap-2 rounded-2"
                                              data-bs-toggle="modal"
                                              data-bs-target="#viewCleanupModal"
                                              data-id="<?= (int)$c['id'] ?>">
                                        <i class="fa fa-eye text-info"></i>
                                        <span>View</span>
                                      </button>
                                    </li>
                                    <li><hr class="dropdown-divider my-1"></li>
                                    <!-- EDIT -->
                                    <li>
                                      <button class="dropdown-item d-flex align-items-center gap-2 rounded-2"
                                              data-bs-toggle="modal"
                                              data-bs-target="#editCleanupModal"
                                              data-id="<?= (int)$c['id'] ?>">
                                        <i class="fa fa-pen text-primary"></i>
                                        <span>Edit</span>
                                      </button>
                                    </li>
                                    <li><hr class="dropdown-divider my-1"></li>
                                    <!-- DELETE -->
                                    <li>
                                      <button class="dropdown-item d-flex align-items-center gap-2 rounded-2 text-danger"
                                              type="button"
                                              data-bs-toggle="modal"
                                              data-bs-target="#deleteCleanupModal"
                                              data-id="<?= (int)$c['id'] ?>"
                                              data-title="<?= htmlspecialchars($c['activity_title'], ENT_QUOTES) ?>"
                                              data-date="<?= htmlspecialchars($c['activity_date'], ENT_QUOTES) ?>">
                                        <i class="fa fa-trash"></i>
                                        <span>Delete</span>
                                      </button>
                                    </li>
                                  </ul>
                                </div>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                  <?php else: ?>
                    <div class="alert alert-light border mb-0 alert-permanent">
                      <b><?= htmlspecialchars($allowedSections[$section_key]) ?></b> section content goes here.
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

        <?php if (false): ?>
        <!-- ✅ DEADLINE MODAL (ADMIN ONLY) -->
        <?php if ($isHeadAdmin || $isIecAdmin): ?>
        <div class="modal fade" id="addDeadlineModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <form action="<?= url_with_base('modules/iec/deadline_add.php') ?>" method="POST">
              <div class="modal-content rounded-4 shadow">
                <div class="modal-header bg-primary text-white">
                  <h5 class="modal-title"><i class="fa fa-clock me-2"></i> Set Submission Deadline</h5>
                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">Section</label>
                      <select class="form-select" name="section_key" required>
                        <?php foreach ($allowedSections as $k => $label): ?>
                          <option value="<?= htmlspecialchars($k) ?>" <?= $k === $section_key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div class="col-md-6">
                      <label class="form-label">Scope</label>
                      <select class="form-select" name="scope" id="scopeSelect" required>
                        <option value="global">All Barangays</option>
                        <option value="barangay">Specific Barangay</option>
                      </select>
                    </div>

                    <div class="col-md-6" id="barangayBox" style="display:none;">
                      <label class="form-label">Barangay</label>
                      <input type="text" class="form-control" name="barangay" placeholder="e.g. Bagong Nayon">
                      <div class="form-text">Exact spelling dapat match sa session barangay.</div>
                    </div>

                    <div class="col-md-6">
                      <label class="form-label">Deadline Date</label>
                      <input type="date" class="form-control" name="deadline_date" required>
                    </div>

                    <div class="col-12">
                      <label class="form-label">Notes</label>
                      <input type="text" class="form-control" name="notes" placeholder="e.g. Submit on/before deadline">
                    </div>
                  </div>
                </div>

                <div class="modal-footer">
                  <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                  <button class="btn btn-primary">Save Deadline</button>
                </div>
              </div>
            </form>
          </div>
        </div>

      <script>
      (() => {
        const scopeSelect = document.getElementById('scopeSelect');
        const barangayBox = document.getElementById('barangayBox');
        if (!scopeSelect || !barangayBox) return;
        scopeSelect.addEventListener('change', () => {
          barangayBox.style.display = (scopeSelect.value === 'barangay') ? 'block' : 'none';
        });
      })();
      </script>
    <?php endif; ?>


<style>
  .modal-ux .modal-content{
    border:0;
    border-radius: 18px;
    overflow:hidden;
    box-shadow: 0 18px 50px rgba(0,0,0,.25);
  }
  .modal-ux .modal-header{
    padding: 18px 22px;
    color:#fff;
    background: linear-gradient(135deg, #7c3aed, #8b5cf6);
    border-bottom: 0;
  }
  .modal-ux .modal-title{
    font-weight: 800;
    display:flex;
    align-items:center;
    gap:.6rem;
    margin:0;
  }
  .modal-ux .modal-subtitle{
    font-size: .875rem;
    opacity:.85;
    margin-top: 2px;
  }
  .modal-ux .modal-body{ padding: 22px; background:#fff; }
  .modal-ux .modal-footer{
    padding: 16px 22px;
    background: #f8fafc;
    border-top: 1px solid rgba(0,0,0,.06);
  }

  /* Labels like pic (icon + title) */
  .ux-label{
    font-weight: 700;
    color:#4b5563;
    display:flex;
    align-items:center;
    gap:.55rem;
    margin-bottom: .35rem;
  }
  .ux-label i{ color:#7c3aed; }

  /* Inputs */
  .modal-ux .form-control,
  .modal-ux .form-select,
  .modal-ux textarea.form-control{
    border-radius: 12px;
    padding: .78rem .95rem;
    border: 1px solid #d7dde7;
    box-shadow: 0 2px 0 rgba(0,0,0,.02);
  }
  .modal-ux .form-control:focus,
  .modal-ux .form-select:focus,
  .modal-ux textarea.form-control:focus{
    border-color: rgba(124,58,237,.55);
    box-shadow: 0 0 0 .25rem rgba(124,58,237,.15);
  }

  /* Input with left icon (like pic) */
  .input-icon{ position:relative; }
  .input-icon .fi{
    position:absolute; left:12px; top:50%;
    transform: translateY(-50%);
    color:#7c3aed; opacity:.9;
    pointer-events:none;
  }
  .input-icon .form-control,
  .input-icon .form-select{
    padding-left: 40px;
  }

  /* Footer buttons style */
  .btn-ux-primary{
    background:#7c3aed; border-color:#7c3aed;
    color:#fff;
    border-radius: 10px;
    padding: .62rem 1.25rem;
    font-weight: 700;
  }
  .btn-ux-primary:hover{ filter: brightness(.95); }
  .btn-ux-secondary{
    background:#6b7280; border-color:#6b7280;
    color:#fff;
    border-radius: 10px;
    padding: .62rem 1.25rem;
    font-weight: 700;
  }
  .btn-ux-secondary:hover{ filter: brightness(.95); }

  /* Optional: nicer help text */
  .modal-ux .form-text{ color:#6b7280; }
</style>

<style>
/* ===== SAME AS PIC #1: compact modal size + compact fields ===== */
.modal-ux-compact .modal-dialog{ max-width: 980px; } /* adjust 900-1050 */

.modal-ux-compact .modal-content{
  border:0;
  border-radius: 18px;
  overflow:hidden;
  box-shadow: 0 18px 50px rgba(0,0,0,.25);
}

.modal-ux-compact .modal-header{
  padding: 16px 20px;
  color:#fff;
  background: linear-gradient(135deg, #7c3aed, #8b5cf6); /* purple */
  border:0;
}

.modal-ux-compact .modal-title{
  font-weight: 800;
  display:flex;
  align-items:center;
  gap:.6rem;
  margin:0;
}
.modal-ux-compact .modal-subtitle{
  font-size: .85rem;
  opacity: .85;
  margin-top: 2px;
}

.modal-ux-compact .modal-body{ padding: 18px 20px; }
.modal-ux-compact .modal-footer{
  padding: 14px 20px;
  background:#f8fafc;
  border-top: 1px solid rgba(0,0,0,.06);
}

/* tighter grid */
.modal-ux-compact .row.g-3{ --bs-gutter-y: .75rem; }
.modal-ux-compact .row.g-3{ --bs-gutter-x: 1rem; }

.modal-ux-compact .form-label{
  font-weight: 700;
  color:#4b5563;
  margin-bottom:.35rem;
}

/* compact inputs */
.modal-ux-compact .form-control,
.modal-ux-compact .form-select,
.modal-ux-compact textarea.form-control{
  border-radius: 12px;
  padding: .55rem .75rem;
  font-size: .92rem;
  border: 1px solid #d7dde7;
  box-shadow: 0 2px 0 rgba(0,0,0,.02);
}
.modal-ux-compact .form-control:focus,
.modal-ux-compact .form-select:focus,
.modal-ux-compact textarea.form-control:focus{
  border-color: rgba(124,58,237,.55);
  box-shadow: 0 0 0 .25rem rgba(124,58,237,.15);
}
.modal-ux-compact textarea.form-control{ min-height: 90px; }

/* input-group compact */
.modal-ux-compact .input-group-text{
  border-radius: 12px 0 0 12px;
  padding: .5rem .65rem;
  background:#f3f4f6;
  border-color:#d7dde7;
}

/* footer buttons like pic #1 */
.btn-ux-cancel{
  background:#6b7280;
  border-color:#6b7280;
  color:#fff;
  border-radius: 10px;
  padding:.55rem 1.2rem;
  font-weight: 800;
}
.btn-ux-save{
  background:#7c3aed;
  border-color:#7c3aed;
  color:#fff;
  border-radius: 10px;
  padding:.55rem 1.2rem;
  font-weight: 800;
}
.btn-ux-save:hover, .btn-ux-cancel:hover{ filter: brightness(.95); }

/* View cards inside modal */
.ux-card{
  border:1px solid rgba(0,0,0,.08);
  border-radius: 14px;
  padding: 12px 14px;
  background:#fff;
}
.ux-card .k{ font-size:.78rem; color:#6b7280; }
.ux-card .v{ font-weight:800; color:#111827; }
</style>

<!-- =========================================================
     ✅ OPTIONAL: simple JS previews (attendance + photos thumbs)
     (remove if you already have your own)
========================================================= -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Attendance file preview name
  const attendance = document.getElementById('attendanceFile');
  const wrap = document.getElementById('attendancePreviewWrap');
  const nameEl = document.getElementById('attendancePreviewName');

  if (attendance && wrap && nameEl){
    attendance.addEventListener('change', () => {
      const f = attendance.files && attendance.files[0];
      if (!f){ wrap.classList.add('d-none'); nameEl.textContent = ''; return; }
      nameEl.textContent = f.name;
      wrap.classList.remove('d-none');
    });
  }

  // Photo thumbnails
  const photosInput = document.getElementById('photosInput');
  const thumbs = document.getElementById('photoThumbs');

  if (photosInput && thumbs){
    photosInput.addEventListener('change', () => {
      thumbs.innerHTML = '';
      const files = [...(photosInput.files || [])];
      files.slice(0, 12).forEach((f) => {
        const col = document.createElement('div');
        col.className = 'col-6 col-md-3';
        const card = document.createElement('div');
        card.className = 'ux-card p-2';
        const img = document.createElement('img');
        img.className = 'img-fluid rounded-3';
        img.alt = f.name;
        img.src = URL.createObjectURL(f);
        img.onload = () => URL.revokeObjectURL(img.src);
        card.appendChild(img);
        col.appendChild(card);
        thumbs.appendChild(col);
      });
    });
  }
});
</script>


<?php endif; ?>

<?php include dirname(__DIR__, 2) . '/includes/footer_scripts.php'; ?>
</body>
</html>

