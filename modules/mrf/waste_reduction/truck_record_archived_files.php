<?php
require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/permissions.php';

requirePermission('mrf.waste_reduction');
?>

<ul class="nav nav-tabs mb-3" id="recordTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#activeRecords" type="button" role="tab">Active</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="archived-tab" data-bs-toggle="tab" data-bs-target="#archivedRecords" type="button" role="tab">Archived</button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="activeRecords" role="tabpanel">
    <!-- Your current table (Active records) here -->
  </div>

  <div class="tab-pane fade" id="archivedRecords" role="tabpanel">
    <!-- Archived records table will go here -->
    <?php 
      $i = 1;
      $qry = $conn->query("SELECT * FROM `files` WHERE archived = 1 ORDER BY date_created DESC");
    ?>
    <div class="table-modern-wrap">
      <table class="table table-modern table-bordered table-hover align-middle text-center">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Date</th>
            <th>Time In</th>
            <th>Time Out</th>
            <th>Total Hours</th>
            <th>Truck No.</th>
            <th>Driver</th>
            <th>Area</th>
            <th>Dumping</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $qry->fetch_assoc()):
            $time_in = strtotime($row['date_created'] . ' ' . $row['time_in']);
            $date_out = $row['date_out'] ?: $row['date_created'];
            $time_out = strtotime($date_out . ' ' . $row['time_out']);
            if ($time_out < $time_in) $time_out += 86400;
            $duration = floor(($time_out - $time_in) / 3600) . ' hr';
          ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= date('F j, Y', strtotime($row['date_created'])) ?></td>
            <td><small><?= date('h:i A', $time_in) ?></small></td>
            <td><small><?= date('h:i A', $time_out) ?></small></td>
            <td><?= $duration ?></td>
            <td><?= htmlspecialchars($row['truck']) ?></td>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['area']) ?></td>
            <td><?= htmlspecialchars($row['dumping']) ?></td>
            <td>
              <button class="btn btn-sm btn-success unarchive_data" data-id="<?= $row['id'] ?>">
                <i class="fa fa-undo"></i> Unarchive
              </button>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
