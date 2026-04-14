<!-- Edit Waste Reduction Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editWasteReductionLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form id="editForm" method="POST" action="update_function.php">
      <?= csrf_input() ?>
      <input type="hidden" name="edit_id" id="edit_id">

      <div class="modal-content rounded-4 shadow">
        <div class="modal-header bg-purple text-white rounded-top-4">
          <div>
            <h5 class="modal-title fw-normal fs-5" id="editWasteReductionLabel">
              <i class="fa fa-edit me-2"></i> Update Record
            </h5>
            <small class="text-light opacity-75">Update the details below.</small>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body py-4">
          <div class="row g-4">
            <div class="col-md-6">
              <label for="edit_name" class="form-label">Name</label>
              <input type="text" class="form-control rounded-2 shadow-sm" name="name" id="edit_name" required>
            </div>
            <div class="col-md-6">
              <label for="edit_date" class="form-label">Date</label>
              <input type="date" class="form-control rounded-2 shadow-sm" name="date" id="edit_date" required>
            </div>

            <div class="col-md-12">
              <label for="edit_other_waste" class="form-label">Other Wastes</label>
              <select name="other_waste" id="edit_other_waste" class="form-select" required>
                <option value="SHREDDED PLASTIC WASTE">SHREDDED PLASTIC</option>
                <option value="SHREDDED COCO HUSK">SHREDDED COCO HUSK</option>
                <option value="PULVERIZED GLASS">PULVERIZED GLASS</option>
                <option value="WOOD CHIPPED">WOOD CHIPPED</option>
              </select>
            </div>

            <div class="col-md-6">
              <label for="edit_kgs_before" class="form-label">Kgs Before</label>
              <input type="number" step="0.01" min="0" class="form-control shadow-sm" name="kgs_before" id="edit_kgs_before" required>
            </div>
            <div class="col-md-6">
              <label for="edit_kgs_after" class="form-label">Kgs After</label>
              <input type="number" step="0.01" min="0" class="form-control shadow-sm" name="kgs_after" id="edit_kgs_after" required>
            </div>
          </div>
        </div>

        <div class="modal-footer py-3">
          <button type="submit" class="btn bg-purple text-white px-4">
            <i class="fa fa-save me-1"></i> Update
          </button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fa fa-times me-1"></i> Close
          </button>
        </div>
      </div>
    </form>
  </div>
</div>
