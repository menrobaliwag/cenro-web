<div class="modal fade modal-ux" id="addBoardDocModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form action="<?= url_with_base('modules/iec/board_docs_add.php') ?>" method="POST" enctype="multipart/form-data">
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
              <label class="ux-label"><i class="fa fa-tags"></i> Document Type</label>
              <div class="input-icon">
                <i class="fa fa-file-lines fi"></i>
                <select class="form-select" name="doc_type" required>
                  <option value="Resolution">Resolution</option>
                  <option value="EO">EO</option>
                  <option value="Ordinance">Ordinance</option>
                  <option value="Others" selected>Others</option>
                </select>
              </div>
            </div>

            <div class="col-12">
              <label class="ux-label"><i class="fa fa-heading"></i> Document Title</label>
              <div class="input-icon">
                <i class="fa fa-pen-to-square fi"></i>
                <input type="text" class="form-control" name="doc_title" required>
              </div>
            </div>

            <div class="col-md-6">
              <label class="ux-label"><i class="fa fa-calendar"></i> Date Issued</label>
              <div class="input-icon">
                <i class="fa fa-calendar-days fi"></i>
                <input type="date" class="form-control" name="date_issued">
              </div>
            </div>

            <div class="col-md-6">
              <label class="ux-label"><i class="fa fa-paperclip"></i> File Upload (PDF/DOC/DOCX)</label>
              <input type="file" class="form-control" name="doc_file" accept=".pdf,.doc,.docx" required>
            </div>

            <div class="col-12">
              <label class="ux-label"><i class="fa fa-message"></i> Description / Notes</label>
              <textarea class="form-control" rows="3" name="notes" placeholder="Optional notes..."></textarea>
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

