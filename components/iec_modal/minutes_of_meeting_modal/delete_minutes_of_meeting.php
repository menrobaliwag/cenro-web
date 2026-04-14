<div class="modal fade" id="deleteMinutesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form action="<?= url_with_base('modules/iec/minutes_delete.php') ?>" method="POST">
      <div class="modal-content rounded-4 shadow">
        <div class="modal-header text-white iec-danger-header">
          <div>
            <h5 class="modal-title mb-0">
              <i class="fa fa-triangle-exclamation me-2"></i> Delete Minutes
            </h5>
            <small class="opacity-75">This will remove the minutes from active lists (soft delete).</small>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="id" id="delMinId">

          <div class="p-3 rounded-3 iec-danger-box">
            <div class="text-muted small mb-1">You are about to delete:</div>
            <div class="fw-semibold" id="delMinTitle">—</div>
          </div>

          <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" id="confirmDeleteMinutes" required>
            <label class="form-check-label" for="confirmDeleteMinutes">
              Yes, I understand this action.
            </label>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-danger">
            <i class="fa fa-trash me-2"></i> Delete
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

