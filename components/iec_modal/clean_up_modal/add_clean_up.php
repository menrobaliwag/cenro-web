<div class="modal fade modal-ux-compact" id="addCleanupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <form action="<?= url_with_base('modules/iec/cleanup_add.php') ?>" method="POST" enctype="multipart/form-data"
          class="needs-validation" novalidate>
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

            <!-- Barangay -->
            <div class="col-md-6">
              <label class="form-label">Barangay</label>
              <?php if (!empty($_SESSION['barangay'])): ?>
                <input type="text" class="form-control" value="<?= htmlspecialchars($_SESSION['barangay']) ?>" disabled>
                <input type="hidden" name="barangay" value="<?= htmlspecialchars($_SESSION['barangay']) ?>">
              <?php else: ?>
                <select class="form-select" name="barangay" required>
                  <option value="" selected disabled>— Select barangay —</option>
                  <option value="Barangay 1">Barangay 1</option>
                  <option value="Barangay 2">Barangay 2</option>
                  <option value="Barangay 3">Barangay 3</option>
                </select>
                <div class="invalid-feedback">Barangay is required.</div>
              <?php endif; ?>
            </div>

            <!-- Title -->
            <div class="col-md-6">
              <label class="form-label">Activity Title</label>
              <input type="text" class="form-control" name="activity_title" required
                     placeholder="e.g., Clean-up Drive at Riverside">
              <div class="invalid-feedback">Activity title is required.</div>
            </div>

            <!-- Date -->
            <div class="col-md-6">
              <label class="form-label">Activity Date</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                <input type="date" class="form-control" name="activity_date" required>
              </div>
              <div class="invalid-feedback">Activity date is required.</div>
            </div>

            <!-- Attendance -->
            <div class="col-md-6">
              <label class="form-label">Attendance File <span class="text-muted fw-normal">(JPG/PNG/PDF/DOC/DOCX)</span></label>
              <input type="file" class="form-control" name="attendance_file" id="attendanceFile"
                     accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
              <div class="form-text">Optional, but recommended.</div>
              <div class="mt-2 d-none" id="attendancePreviewWrap">
                <div class="ux-card">
                  <div class="k">Selected:</div>
                  <div class="v d-flex align-items-center gap-2">
                    <i class="fa fa-paperclip"></i>
                    <span id="attendancePreviewName"></span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Time -->
            <div class="col-md-4">
              <label class="form-label">Activity Time</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fa fa-clock"></i></span>
                <input type="time" class="form-control" name="activity_time" required>
              </div>
              <div class="invalid-feedback">Activity time is required.</div>
            </div>

            <!-- Participants -->
            <div class="col-md-4">
              <label class="form-label">Participants</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fa fa-users"></i></span>
                <input type="number" class="form-control" name="participants" min="0" value="0" required>
              </div>
              <div class="form-text">0 allowed.</div>
            </div>

            <!-- Status -->
            <div class="col-md-4">
              <label class="form-label">Status</label>
              <?php $isBarangayUser = !empty($_SESSION['barangay']); ?>
              <?php if ($isBarangayUser): ?>
                <input type="text" class="form-control" value="Submitted" disabled>
                <input type="hidden" name="status" value="Submitted">
              <?php else: ?>
                <select class="form-select" name="status">
                  <option value="Draft">Draft</option>
                  <option value="Submitted" selected>Submitted</option>
                  <option value="Approved">Approved</option>
                  <option value="For Revision">For Revision</option>
                </select>
              <?php endif; ?>
            </div>

            <!-- Venue -->
            <div class="col-12">
              <label class="form-label">Venue</label>
              <input type="text" class="form-control" name="venue" required
                     placeholder="e.g., Barangay Hall / Riverside / Main Road">
              <div class="invalid-feedback">Venue is required.</div>
            </div>

            <!-- Description -->
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea class="form-control" name="description" rows="3"
                        placeholder="Short details about the activity..."></textarea>
            </div>

            <!-- Photos -->
            <div class="col-12">
              <label class="form-label">Activity Photos <span class="text-muted fw-normal">(multiple JPG/PNG)</span></label>
              <input type="file" class="form-control" name="photos[]" id="photosInput"
                     accept=".jpg,.jpeg,.png" multiple>
              <div class="form-text">PNG/JPG only.</div>
              <div class="row g-2 mt-1" id="photoThumbs"></div>
            </div>

          </div>
        </div>

        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-ux-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-ux-save">
            <i class="fa fa-floppy-disk me-2"></i> Save
          </button>
        </div>

      </div>
    </form>
  </div>
</div>

