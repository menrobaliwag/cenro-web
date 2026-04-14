<?php if ($isHeadAdmin || $isIecAdmin): ?>
<div class="modal fade" id="addDeadlineModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form action="<?= url_with_base('modules/iec/deadline_add.php') ?>" method="POST">
      <?= csrf_input(); ?>
      <div class="modal-content rounded-4 shadow">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title"><i class="fa fa-clock me-2"></i> Set Submission Deadline</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Section</label>
              <select class="form-select" name="section_key" required>
                <?php foreach ($allowedSections as $k => $label): ?>
                  <option value="<?= htmlspecialchars($k) ?>" <?= $k === $section_key ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Scope</label>
              <select class="form-select" name="scope" id="scopeSelect" required>
                <option value="global">All Barangays</option>
                <option value="barangay">Specific Barangay</option>
              </select>
            </div>

            <div class="col-md-6" id="barangayBox" style="display:none;">
              <label class="form-label">Barangay</label>
              <input type="text" class="form-control" name="barangay" placeholder="e.g. Bagong Nayon">
              <div class="form-text">Exact spelling dapat match sa session barangay.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Deadline Date</label>
              <input type="date" class="form-control" name="deadline_date" required>
            </div>

            <div class="col-12">
              <label class="form-label">Notes</label>
              <input type="text" class="form-control" name="notes" placeholder="e.g. Submit on/before deadline">
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary">Save Deadline</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

