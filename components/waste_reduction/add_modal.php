<!-- Add Waste Reduction Record Modal -->
<div class="modal fade" id="addRecordModal" tabindex="-1" aria-labelledby="addRecordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form action="add_function.php" method="POST">
      <?= csrf_input() ?>
      <div class="modal-content rounded-4 shadow">

        <div class="modal-header bg-purple text-white rounded-top-4">
          <div>
            <h5 class="modal-title fw-normal text-white" id="addRecordModalLabel">
              <i class="fa fa-plus me-2 text-white"></i> Add Record
            </h5>
            <small class="text-light opacity-75">Fill in the required fields below.</small>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body py-4">
          <div class="row g-4">
            <div class="col-md-6">
              <label for="wr_name" class="form-label">Name</label>
              <input type="text" name="name" id="wr_name" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label for="wr_date" class="form-label">Date</label>
              <input type="text" id="wr_date" name="date" class="form-control rounded-2 shadow-sm" readonly required>
            </div>

            <div class="col-md-12">
              <label for="wr_other_waste" class="form-label">Other Wastes</label>
              <select name="other_waste" id="wr_other_waste" class="form-select" required>
                <option value="" selected disabled>Select waste type</option>
                <option value="SHREDDED PLASTIC WASTE">SHREDDED PLASTIC</option>
                <option value="SHREDDED COCO HUSK">SHREDDED COCO HUSK</option>
                <option value="PULVERIZED GLASS">PULVERIZED GLASS</option>
                <option value="WOOD CHIPPED">WOOD CHIPPED</option>
              </select>
            </div>

            <div class="col-md-6">
              <label for="wr_kgs_before" class="form-label">Kgs Before</label>
              <input type="number" step="0.01" min="0" name="kgs_before" id="wr_kgs_before" class="form-control" value="0.00" required>
            </div>

            <div class="col-md-6">
              <label for="wr_kgs_after" class="form-label">Kgs After</label>
              <input type="number" step="0.01" min="0" name="kgs_after" id="wr_kgs_after" class="form-control" value="0.00" required>
            </div>
          </div>
        </div>

        <div class="modal-footer py-3">
          <button type="submit" class="btn bg-purple text-white px-4">
            <i class="fa fa-save me-1"></i> Save
          </button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fa fa-times me-1"></i> Cancel
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (typeof flatpickr !== 'function') return;
    flatpickr("#wr_date", {
      altInput: true,
      altFormat: "F j, Y",
      dateFormat: "Y-m-d",
      defaultDate: "<?= date('Y-m-d') ?>",
      allowInput: true
    });
  });
</script>
