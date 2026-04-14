<div class="modal fade" id="editRecordModal" tabindex="-1" aria-labelledby="editRecordLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <form action="sorter_record_update_data.php" method="POST">
      <div class="modal-content rounded-4 shadow">

        <!-- Modal Header -->
        <div class="modal-header bg-purple text-white rounded-top-4">
          <div>
            <h5 class="modal-title fw-normal fs-5" id="editRecordLabel">
              <i class="fa fa-edit me-2"></i>Waste Record
            </h5>
            <small class="text-light opacity-75">Update the sorter and waste details below.</small>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <!-- Modal Body -->
        <div class="modal-body py-4">
          <input type="hidden" name="edit_id" id="edit_id">

          <h6 class="text-muted fw-normal mb-3">🧑‍ Sorting Details</h6>
          <div class="row g-4 align-items-end">

            <div class="col-md-4">
              <label for="edit_name" class="form-label">🙍‍♂️ Name of Sorter</label>
              <input type="text" name="name" id="edit_name" class="form-control rounded-2 shadow-sm" required>
            </div>

            <div class="col-md-4">
              <label for="edit_mrf" class="form-label">🧾 Type of Sorter</label>
              <select name="mrf" id="edit_mrf" class="form-select rounded-2 shadow-sm" required>
                <option value="" disabled>Select sorter type</option>
                <option value="Volunteer Sorter">Volunteer Sorter</option>
                <option value="MRF Sorter">MRF Sorter</option>
              </select>
            </div>

            <div class="col-md-4">
              <label for="edit_date" class="form-label">📅 Date</label>
              <input type="text" name="date" id="edit_date" class="form-control rounded-2 shadow-sm" readonly required>
            </div>

          </div>

          <hr class="my-4">

          <h6 class="text-muted fw-normal mb-3">♻️ Waste Categories</h6>
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
                <label for="edit_<?= $key ?>" class="form-label"><?= $label ?></label>
                <input type="text" step="0.01" min="0" name="<?= $key ?>" id="edit_<?= $key ?>" class="form-control money-input shadow-sm" autocomplete="off">
              </div>
            <?php } ?>
          </div>
        </div>

        <!-- Modal Footer -->
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
