<!-- Add Waste Record Modal -->
<div class="modal fade" id="addRecordModal" tabindex="-1" aria-labelledby="addRecordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form action="add_function.php" method="POST">
      <?= csrf_input(); ?>
      <div class="modal-content rounded-4 shadow">

        <!-- Modal Header -->
        <div class="modal-header bg-purple text-white rounded-top-4">
          <div>
            <h5 class="modal-title fw-normal text-white" id="addRecordModalLabel">
              <i class="fa fa-plus me-2 text-white"></i> Add Record
            </h5>
            <small class="text-light opacity-75">Fill in the required fields below.</small>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <!-- Modal Body -->
        <div class="modal-body py-4">
          <div class="row g-4">

            <!-- Name -->
            <div class="col-md-6">
              <label for="name" class="form-label">👤 Name</label>
              <input type="text" name="name" id="name" class="form-control" required>
            </div>

            <!-- Date -->
            <div class="col-md-6">
              <label for="date" class="form-label">📅 Date</label>
              <input type="text" id="date" name="date" class="form-control rounded-2 shadow-sm" value="<?= date('Y-m-d') ?>" readonly required>
            </div>

            <!-- Eco Bricks -->
            <div class="col-md-4">
              <label for="eco" class="form-label">🧱 Eco Bricks (kg)</label>
              <input type="number" step="0.01" name="eco" id="eco" class="form-control" value="0.00" required>
            </div>

            <!-- Pavement Bricks -->
            <div class="col-md-4">
              <label for="pavement" class="form-label">🧱 Pavement Bricks (kg)</label>
              <input type="number" step="0.01" name="pavement" id="pavement" class="form-control" value="0.00" required>
            </div>

            <!-- Cement Bricks -->
            <div class="col-md-4">
              <label for="cement" class="form-label">🧱 Cement Bricks (kg)</label>
              <input type="number" step="0.01" name="cement" id="cement" class="form-control" value="0.00" required>
            </div>

            <!-- Charcoal Briquette -->
            <div class="col-md-4">
              <label for="coal" class="form-label">🔥 Charcoal Briquette (kg)</label>
              <input type="number" step="0.01" name="coal" id="coal" class="form-control" value="0.00" required>
            </div>

            <!-- Hollow Blocks -->
            <div class="col-md-4">
              <label for="hollow" class="form-label">🧱 Hollow Blocks (kg)</label>
              <input type="number" step="0.01" name="hollow" id="hollow" class="form-control" value="0.00" required>
            </div>

          </div>
        </div>

        <!-- Modal Footer -->
        <div class="modal-footer py-3">
          <button type="submit" class="btn bg-purple text-white px-4">
            💾 Save
          </button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            ❌ Cancel
          </button>
        </div>

      </div>
    </form>
  </div>
</div>
<!-- Flatpickr Setup -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
  flatpickr("#date", {
    altInput: true,
    altFormat: "F j, Y",
    dateFormat: "Y-m-d",
    defaultDate: "<?= date('Y-m-d') ?>",
    allowInput: true // ✅ ensure value is submitted
  });
</script>
