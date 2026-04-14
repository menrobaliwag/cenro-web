<div class="modal fade modal-ux-compact" id="editCleanupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <form action="<?= url_with_base('modules/iec/cleanup_update.php') ?>" method="POST">
      <div class="modal-content">

        <div class="modal-header">
          <div class="w-100 d-flex align-items-start justify-content-between">
            <div>
              <h5 class="modal-title"><i class="fa fa-pen"></i> Update Record</h5>
              <div class="modal-subtitle">Update the cleanup details below.</div>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
        </div>

        <div class="modal-body">
          <input type="hidden" name="id" id="editCleanupId">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Activity Title</label>
              <input type="text" class="form-control" name="activity_title" id="editTitle" required>
            </div>

            <div class="col-md-3">
              <label class="form-label">Date</label>
              <input type="date" class="form-control" name="activity_date" id="editDate" required>
            </div>

            <div class="col-md-3">
              <label class="form-label">Time</label>
              <input type="time" class="form-control" name="activity_time" id="editTime" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Venue</label>
              <input type="text" class="form-control" name="venue" id="editVenue" required>
            </div>

            <div class="col-md-3">
              <label class="form-label">Participants</label>
              <input type="number" class="form-control" name="participants" id="editParticipants" min="0">
            </div>

            <div class="col-md-3">
              <label class="form-label">Status</label>
              <select class="form-select" name="status" id="editStatus">
                <option value="Draft">Draft</option>
                <option value="Submitted">Submitted</option>
                <option value="Approved">Approved</option>
                <option value="For Revision">For Revision</option>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea class="form-control" rows="3" name="description" id="editDesc"></textarea>
            </div>
          </div>
        </div>

        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-ux-cancel" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-ux-save">
            <i class="fa fa-floppy-disk me-2"></i> Update
          </button>
        </div>

      </div>
    </form>
  </div>
</div>

