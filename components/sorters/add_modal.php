<div class="modal fade" id="addRecordModal" tabindex="-1" aria-labelledby="addRecordLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <form action="sorter_record_function.php" method="POST">
      <!-- ✅ Hidden input to signal insert -->
      <input type="hidden" name="add" value="1">

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
          <!-- Sorter Info -->
          <h6 class="text-muted fw-semibold mb-3">🧑‍ Sorting Details</h6>
          <div class="row g-4 align-items-end">
            <!-- Name of Sorter -->
            <div class="col-md-4">
              <label for="name" class="form-label">🙍‍♂️ Name of Sorter</label>
              <input type="text" name="name" id="name" class="form-control rounded-2 shadow-sm" placeholder="Enter name" required>
            </div>

            <!-- Type of Sorter -->
            <div class="col-md-4">
              <label for="mrf" class="form-label">🧾 Type of Sorter</label>
              <select name="mrf" id="mrf" class="form-select rounded-2 shadow-sm" required>
                <option value="" disabled selected>Select sorter type</option>
                <option value="Volunteer Sorter">Volunteer Sorter</option>
                <option value="MRF Sorter">MRF Sorter</option>
              </select>
            </div>

            <!-- Date -->
            <div class="col-md-4">
              <label for="date" class="form-label">📅 Date</label>
              <input type="text" id="date" name="date" class="form-control rounded-2 shadow-sm" readonly required>
            </div>
          </div>

          <hr class="my-4">

          <!-- Waste Inputs -->
          <h6 class="text-muted fw-semibold mb-3">♻️ Waste Categories</h6>
          <div class="row g-3">
            <?php
            $waste_fields = [
              'puti' => '📄 Papel Puti',
              'assorted' => '📄 Papel Assorted',
              'karton' => '📦 Karton',
              'pet' => '🥤 PET Bottle',
              'sibak' => '🛍️ Sibak (Plastic)',
              'lata' => '🥫 Lata',
              'aluminum' => '🔩 Aluminum',
              'bakal' => '⚙️ Bakal',
              'yero' => '🪨 Yero',
              'glass' => '🍾 Glass Bottles'
            ];
            foreach ($waste_fields as $key => $label) {
            ?>
              <div class="col-md-3">
                <label for="<?= $key ?>" class="form-label"><?= $label ?></label>
                <input type="text" step="0.01" min="0" name="<?= $key ?>" id="<?= $key ?>" class="form-control money-input shadow-sm" value="0.00" autocomplete="off">
              </div>
            <?php } ?>
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
