<?php
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';

requirePermission('mrf.sorters');
?>

<!-- NAV TABS -->
<ul class="nav nav-tabs mb-3" id="recordTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#activeRecords" type="button" role="tab">Active Waste</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="archived-tab" data-bs-toggle="tab" data-bs-target="#archivedRecords" type="button" role="tab">Archived Waste</button>
  </li>
</ul>

<!-- TAB PANES -->
<div class="tab-content">
  <!-- ACTIVE WASTE TAB -->
  <div class="tab-pane fade show active" id="activeRecords" role="tabpanel">
    <?php
    $i = 1;
    $qry = $conn->query("SELECT * FROM scavenger WHERE archived = 0 ORDER BY date DESC");
    ?>
    <div class="table-modern-wrap">
      <table class="table table-modern table-bordered table-hover align-middle text-center">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Date</th>
            <th>Name</th>
            <th>Type</th>
            <th>Puti</th>
            <th>Assorted</th>
            <th>Karton</th>
            <th>PET</th>
            <th>Sibak</th>
            <th>Lata</th>
            <th>Aluminum</th>
            <th>Bakal</th>
            <th>Yero</th>
            <th>Glass</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $qry->fetch_assoc()): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= date('F j, Y', strtotime($row['date'])) ?></td>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['mrf']) ?></td>
            <td><?= $row['puti'] ?></td>
            <td><?= $row['assorted'] ?></td>
            <td><?= $row['karton'] ?></td>
            <td><?= $row['pet'] ?></td>
            <td><?= $row['sibak'] ?></td>
            <td><?= $row['lata'] ?></td>
            <td><?= $row['aluminum'] ?></td>
            <td><?= $row['bakal'] ?></td>
            <td><?= $row['yero'] ?></td>
            <td><?= $row['glass'] ?></td>
            <td>
              <button class="btn btn-sm btn-warning archive_data" data-id="<?= $row['id'] ?>">
                <i class="fa fa-archive"></i> Archive
              </button>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ARCHIVED WASTE TAB -->
  <div class="tab-pane fade" id="archivedRecords" role="tabpanel">
    <?php
    $data = [];
    $qry = $conn->query("SELECT * FROM `scavenger` WHERE archived = 1 ORDER BY date DESC");
    while ($row = $qry->fetch_assoc()) {
      $data[] = [
        'id'        => $row['id'],
        'date'      => date('F j, Y', strtotime($row['date'])),
        'name'      => htmlspecialchars($row['name']),
        'mrf'       => htmlspecialchars($row['mrf']),
        'puti'      => $row['puti'],
        'assorted'  => $row['assorted'],
        'karton'    => $row['karton'],
        'pet'       => $row['pet'],
        'sibak'     => $row['sibak'],
        'lata'      => $row['lata'],
        'aluminum'  => $row['aluminum'],
        'bakal'     => $row['bakal'],
        'yero'      => $row['yero'],
        'glass'     => $row['glass']
      ];
    }
    ?>
    <div class="table-modern-wrap">
      <table class="table table-modern table-bordered table-hover text-center align-middle" id="archivedWasteTable">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Date</th>
            <th>Name</th>
            <th>Type</th>
            <th>Puti</th>
            <th>Assorted</th>
            <th>Karton</th>
            <th>PET</th>
            <th>Sibak</th>
            <th>Lata</th>
            <th>Aluminum</th>
            <th>Bakal</th>
            <th>Yero</th>
            <th>Glass</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; foreach ($data as $row): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= $row['date'] ?></td>
            <td><?= $row['name'] ?></td>
            <td><?= $row['mrf'] ?></td>
            <td><?= $row['puti'] ?></td>
            <td><?= $row['assorted'] ?></td>
            <td><?= $row['karton'] ?></td>
            <td><?= $row['pet'] ?></td>
            <td><?= $row['sibak'] ?></td>
            <td><?= $row['lata'] ?></td>
            <td><?= $row['aluminum'] ?></td>
            <td><?= $row['bakal'] ?></td>
            <td><?= $row['yero'] ?></td>
            <td><?= $row['glass'] ?></td>
            <td>
              <button class="btn btn-sm btn-success unarchive_data" data-id="<?= $row['id'] ?>">
                <i class="fa fa-undo-alt me-1"></i> .oygliuh
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
