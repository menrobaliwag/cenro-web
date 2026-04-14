<div class="modal fade modal-ux" id="addGeneralFileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form action="<?= url_with_base('modules/iec/general_files_add.php') ?>" method="POST" enctype="multipart/form-data">
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

            <div class="col-md-6">
              <label class="ux-label"><i class="fa fa-folder-open"></i> Category</label>
              <div class="input-icon">
                <i class="fa fa-folder fi"></i>
                <select class="form-select" name="category" required>
                  <option value="Reports">Reports</option>
                  <option value="Letters">Letters</option>
                  <option value="Plans">Plans</option>
                  <option value="Others" selected>Others</option>
                </select>
              </div>
            </div>

            <div class="col-12">
              <label class="ux-label"><i class="fa fa-heading"></i> File Title</label>
              <div class="input-icon">
                <i class="fa fa-pen-to-square fi"></i>
                <input type="text" class="form-control" name="file_title" required>
              </div>
            </div>

            <?php if ($isHeadAdmin || $isIecAdmin): ?>
              <div class="col-md-6">
                <label class="ux-label"><i class="fa fa-tag"></i> Status Tag</label>
                <div class="input-icon">
                  <i class="fa fa-flag fi"></i>
                  <select class="form-select" name="status_tag" required>
                    <option value="Pending">â³ Pending</option>
                    <option value="Reviewed">âœ” Reviewed</option>
                    <option value="For Revision">âŒ For Revision</option>
                  </select>
                </div>
              </div>
            <?php endif; ?>

            <div class="col-md-6">
              <label class="ux-label"><i class="fa fa-comment"></i> Remarks (optional)</label>
              <div class="input-icon">
                <i class="fa fa-comment-dots fi"></i>
                <input type="text" class="form-control" name="remarks">
              </div>
            </div>

            <div class="col-12">
              <label class="ux-label"><i class="fa fa-paperclip"></i> File Upload (PDF/DOC/DOCX)</label>
              <input type="file" class="form-control" name="doc_file" accept=".pdf,.doc,.docx" required>
            </div>

          </div>
        </div>

        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-ux-secondary" data-bs-dismiss="modal">
            <i class="fa fa-xmark me-2"></i> Cancel
          </button>
          <button class="btn btn-ux-primary">
            <i class="fa fa-floppy-disk me-2"></i> Save
          </button>
        </div>

      </div>
    </form>
  </div>
</div>

