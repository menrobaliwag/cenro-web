<div class="modal fade modal-ux" id="editBoardDocModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form action="<?= url_with_base('modules/iec/board_docs_update.php') ?>" method="POST" enctype="multipart/form-data">
      <div class="modal-content">

        <div class="modal-header">
          <div class="w-100 d-flex align-items-start justify-content-between">
            <div>
              <h5 class="modal-title"><i class="fa fa-pen"></i> Update Record</h5>
              <div class="modal-subtitle">Update the document details below.</div>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
        </div>

        <div class="modal-body">
          <input type="hidden" name="id" id="editDocId">
          <div class="row g-3">

            <?php if ($isHeadAdmin || $isIecAdmin): ?>
              <div class="col-md-6">
                <label class="ux-label"><i class="fa fa-location-dot"></i> Barangay</label>
                <div class="input-icon">
                  <i class="fa fa-location-dot fi"></i>
                  <input type="text" class="form-control" name="barangay" id="editBarangay" required>
                </div>
              </div>
            <?php endif; ?>

            <div class="col-md-6">
              <label class="ux-label"><i class="fa fa-tags"></i> Document Type</label>
              <div class="input-icon">
                <i class="fa fa-file-lines fi"></i>
                <select class="form-select" name="doc_type" id="editDocType" required>
                  <option value="Resolution">Resolution</option>
                  <option value="EO">EO</option>
                  <option value="Ordinance">Ordinance</option>
                  <option value="Others">Others</option>
                </select>
              </div>
            </div>

            <div class="col-12">
              <label class="ux-label"><i class="fa fa-heading"></i> Document Title</label>
              <div class="input-icon">
                <i class="fa fa-pen-to-square fi"></i>
                <input type="text" class="form-control" name="doc_title" id="editDocTitle" required>
              </div>
            </div>

            <div class="col-md-6">
              <label class="ux-label"><i class="fa fa-calendar"></i> Date Issued</label>
              <div class="input-icon">
                <i class="fa fa-calendar-days fi"></i>
                <input type="date" class="form-control" name="date_issued" id="editDateIssued">
              </div>
            </div>

            <div class="col-md-6">
              <label class="ux-label"><i class="fa fa-paperclip"></i> Replace File (optional)</label>
              <input type="file" class="form-control" name="doc_file" accept=".pdf,.doc,.docx">
            </div>

            <div class="col-12">
              <label class="ux-label"><i class="fa fa-message"></i> Description / Notes</label>
              <textarea class="form-control" rows="3" name="notes" id="editNotes"></textarea>
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

