<div class="modal fade modal-ux" id="editGeneralFileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form action="<?= url_with_base('modules/iec/general_files_update.php') ?>" method="POST" enctype="multipart/form-data">
      <div class="modal-content">

        <div class="modal-header">
          <div class="w-100 d-flex align-items-start justify-content-between">
            <div>
              <h5 class="modal-title"><i class="fa fa-pen"></i> Update Record</h5>
              <div class="modal-subtitle">Update the file details below.</div>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
        </div>

        <div class="modal-body">
          <input type="hidden" name="id" id="editGenId">

          <div class="row g-3">
            <?php if ($isHeadAdmin || $isIecAdmin): ?>
              <div class="col-md-6">
                <label class="ux-label"><i class="fa fa-location-dot"></i> Barangay</label>
                <div class="input-icon">
                  <i class="fa fa-location-dot fi"></i>
                  <input type="text" class="form-control" name="barangay" id="editGenBarangay" required>
                </div>
              </div>
            <?php endif; ?>

            <div class="col-md-6">
              <label class="ux-label"><i class="fa fa-folder-open"></i> Category</label>
              <div class="input-icon">
                <i class="fa fa-folder fi"></i>
                <select class="form-select" name="category" id="editGenCategory" required>
                  <option value="Reports">Reports</option>
                  <option value="Letters">Letters</option>
                  <option value="Plans">Plans</option>
                  <option value="Others">Others</option>
                </select>
              </div>
            </div>

            <div class="col-12">
              <label class="ux-label"><i class="fa fa-heading"></i> File Title</label>
              <div class="input-icon">
                <i class="fa fa-pen-to-square fi"></i>
                <input type="text" class="form-control" name="file_title" id="editGenTitle" required>
              </div>
            </div>

            <?php if ($isHeadAdmin || $isIecAdmin): ?>
              <div class="col-md-6">
                <label class="ux-label"><i class="fa fa-tag"></i> Status Tag</label>
                <div class="input-icon">
                  <i class="fa fa-flag fi"></i>
                  <select class="form-select" name="status_tag" id="editGenStatus" required>
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
                <input type="text" class="form-control" name="remarks" id="editGenRemarks">
              </div>
            </div>

            <div class="col-12">
              <label class="ux-label"><i class="fa fa-paperclip"></i> Replace File (optional)</label>
              <input type="file" class="form-control" name="doc_file" accept=".pdf,.doc,.docx">
            </div>

          </div>
        </div>

        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-ux-secondary" data-bs-dismiss="modal">
            <i class="fa fa-xmark me-2"></i> Close
          </button>
          <button class="btn btn-ux-primary">
            <i class="fa fa-floppy-disk me-2"></i> Update
          </button>
        </div>

      </div>
    </form>
  </div>
</div>

