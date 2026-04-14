<!-- Edit Waste Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editWasteLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <form id="editForm" method="POST" action="update_function.php">
      <input type="hidden" name="edit_id" id="edit_id">

      <div class="modal-content rounded-4 shadow">

        <!-- Header -->
        <div class="modal-header bg-purple text-white rounded-top-4">
          <div>
            <h5 class="modal-title fw-normal fs-5" id="editWasteLabel">
              <i class="fa fa-edit me-2"></i>Waste Record
            </h5>
            <small class="text-light opacity-75">Update the waste details below.</small>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <!-- Body -->
        <div class="modal-body py-4">
          <h6 class="text-muted fw-normal mb-3">🧾 Waste Information</h6>
          <div class="row g-4 align-items-end">
            <div class="col-md-6">
              <label for="edit_name" class="form-label">🙍 Name</label>
              <input type="text" class="form-control rounded-2 shadow-sm" name="name" id="edit_name" required>
            </div>
            <div class="col-md-6">
              <label for="edit_date" class="form-label">📅 Date</label>
              <input type="date" class="form-control rounded-2 shadow-sm" name="date" id="edit_date" required>
            </div>
          </div>

          <hr class="my-4">

          <h6 class="text-muted fw-normal mb-3">♻️ Waste Breakdown</h6>
          <div class="row g-3">
            <div class="col-md-4">
              <label for="edit_eco" class="form-label">🧱 Eco-Bricks</label>
              <input type="number" step="0.01" min="0" class="form-control shadow-sm" name="eco" id="edit_eco" required>
            </div>
            <div class="col-md-4">
              <label for="edit_pavement" class="form-label">🧱 Pavement Brick</label>
              <input type="number" step="0.01" min="0" class="form-control shadow-sm" name="pavement" id="edit_pavement" required>
            </div>
            <div class="col-md-4">
              <label for="edit_cement" class="form-label">🧱 Cement Bricks</label>
              <input type="number" step="0.01" min="0" class="form-control shadow-sm" name="cement" id="edit_cement" required>
            </div>
            <div class="col-md-4">
              <label for="edit_coal" class="form-label">🔥 Charcoal Briquette</label>
              <input type="number" step="0.01" min="0" class="form-control shadow-sm" name="coal" id="edit_coal" required>
            </div>
            <div class="col-md-4">
              <label for="edit_hollow" class="form-label">🧱 Hollow Blocks</label>
              <input type="number" step="0.01" min="0" class="form-control shadow-sm" name="hollow" id="edit_hollow" required>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="modal-footer py-3">
          <button type="submit" name="update" class="btn bg-purple text-white px-4">
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
