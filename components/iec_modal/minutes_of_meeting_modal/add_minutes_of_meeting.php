<div class="modal fade modal-ux" id="addMinutesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form action="<?= url_with_base('modules/iec/minutes_add.php') ?>" method="POST" enctype="multipart/form-data">
      <div class="modal-content">

        <div class="modal-header">
          <div class="w-100 d-flex align-items-start justify-content-between">
            <div>
              <h5 class="modal-title"><i class="fa fa-plus"></i> Add Record</h5>
              <div class="modal-subtitle">Fill in the required fields below.</div>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
        </div>

        <div class="modal-body">
          <div class="row g-3">

            <?php if ($isHeadAdmin || $isIecAdmin): ?>
              <div class="col-md-6">
                <label class="ux-label"><i class="fa fa-location-dot"></i> Barangay</label>
                <div class="input-icon">
                  <i class="fa fa-location-dot fi"></i>
                  <input type="text" class="form-control" name="barangay" required>
                </div>
              </div>
            <?php else: ?>
              <input type="hidden" name="barangay" value="<?= htmlspecialchars($myBarangay) ?>">
            <?php endif; ?>

            <div class="col-md-3">
              <label class="ux-label"><i class="fa fa-layer-group"></i> Quarter</label>
              <div class="input-icon">
                <i class="fa fa-layer-group fi"></i>
                <select class="form-select" name="quarter" required>
                  <option value="Q1">Q1</option><option value="Q2">Q2</option>
                  <option value="Q3">Q3</option><option value="Q4">Q4</option>
                </select>
              </div>
            </div>

            <div class="col-md-3">
              <label class="ux-label"><i class="fa fa-hashtag"></i> Year</label>
              <div class="input-icon">
                <i class="fa fa-hashtag fi"></i>
                <input type="number" class="form-control" name="year" value="<?= (int)date('Y') ?>" required>
              </div>
            </div>

            <div class="col-md-6">
              <label class="ux-label"><i class="fa fa-calendar"></i> Meeting Date</label>
              <div class="input-icon">
                <i class="fa fa-calendar-days fi"></i>
                <input type="date" class="form-control" name="meeting_date" required>
              </div>
            </div>

            <div class="col-md-6">
              <label class="ux-label"><i class="fa fa-user"></i> Prepared By</label>
              <div class="input-icon">
                <i class="fa fa-user fi"></i>
                <input type="text" class="form-control" name="prepared_by" required>
              </div>
            </div>

            <div class="col-md-6">
              <label class="ux-label"><i class="fa fa-flag"></i> Status</label>
              <div class="input-icon">
                <i class="fa fa-flag fi"></i>
                <select class="form-select" name="submission_status" required>
                  <option value="Submitted" selected>Submitted</option>
                  <option value="Pending">Pending</option>
                  <option value="Late Submission">Late Submission</option>
                  <option value="Approved">Approved</option>
                  <option value="For Revision">For Revision</option>
                </select>
              </div>
            </div>

            <div class="col-12">
              <label class="ux-label"><i class="fa fa-file-pdf"></i> Upload Minutes (PDF only)</label>
              <input type="file" class="form-control" name="minutes_pdf" accept=".pdf" required>
            </div>

          </div>
        </div>

        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-ux-secondary" data-bs-dismiss="modal">
            <i class="fa fa-xmark me-2"></i> Cancel
          </button>
          <button class="btn btn-ux-primary">
            <i class="fa fa-floppy-disk me-2"></i> Submit
          </button>
        </div>

      </div>
    </form>
  </div>
</div>

