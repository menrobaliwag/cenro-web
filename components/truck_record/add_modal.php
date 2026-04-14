<!-- ============================================================== -->
<!-- ADD MODAL  -->
<!-- ============================================================== -->
<div class="modal fade" id="addRecordModal" tabindex="-1" aria-labelledby="addRecordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form action="truck_record_function.php" method="POST">
      <?= csrf_input(); ?>
      <div class="modal-content rounded-4 shadow">

        <!-- Header -->
        <div class="modal-header bg-purple text-white rounded-top-4">
          <div>
           <h5 class="modal-title fw-normal text-white" id="addRecordModalLabel">
              <i class="fa fa-plus me-2 text-white"></i> Add Record
            </h5>
            <small class="text-light opacity-75">📝 Fill in the required fields below.</small>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <!-- Body -->
        <div class="modal-body py-4">
          <div class="row g-4">

            <!-- Date -->
            <div class="col-md-6">
              <label for="date" class="form-label">📅 Date</label>
              <input type="text" id="date" name="date_created" class="form-control rounded-2 shadow-sm" readonly required>
            </div>

            <!-- Truck Number -->
            <div class="col-md-6">
              <label for="truck" class="form-label">🚚 Truck Number</label>
              <input type="number" class="form-control" id="truck" name="truck" required>
            </div>

            <!-- Time In -->
            <div class="col-md-6">
              <label for="timein" class="form-label">🕓 Time In</label>
              <input type="time" class="form-control" id="timein" name="time_in" required>
            </div>

            <!-- Time Out -->
            <div class="col-md-6">
              <label for="timeout" class="form-label">🕗 Time Out</label>
              <input type="time" class="form-control" id="timeout" name="time_out" required>
            </div>

            <!-- Driver Name -->
            <div class="col-md-6">
              <label for="name" class="form-label">👨‍✈️ Driver's Name</label>
              <input type="text" class="form-control" id="name" name="name" required>
            </div>

            <!-- Area -->
            <div class="col-md-6">
              <label for="area" class="form-label">📍 Area</label>
              <input type="text" class="form-control" id="area" name="area" required>
            </div>

            <!-- Dumping Count -->
            <div class="col-md-6">
              <label for="dumping" class="form-label">🗑️ Dumping Count</label>
              <input type="number" class="form-control" id="dumping" name="dumping" min="0" required>
            </div>

            <!-- Total Hours -->
            <div class="col-md-6">
              <label for="total_hours" class="form-label">⏱️ Total Hours</label>
              <input type="text" class="form-control" id="total_hours" name="total_hours" readonly>
            </div>

          </div>
        </div>

        <!-- Footer -->
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

<!-- ============================================================== -->
<!-- END ADD MODAL  -->
<!-- ============================================================== -->
