<div class="modal fade" id="addRecordModal" tabindex="-1" aria-labelledby="addRecordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">

    <form action="add_function.php" method="POST" enctype="multipart/form-data">

      <div class="modal-content rounded-4 shadow">

        <!-- Modal Header -->
        <div class="modal-header bg-purple text-white rounded-top-4">
          <div>
            <h5 class="modal-title fw-normal text-white" id="addRecordModalLabel">
              <i class="fa fa-plus me-2 text-white"></i> Add Record
            </h5>
            <small class="text-light opacity-75">Fill in the required fields below.</small>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <!-- Modal Body -->
        <div class="modal-body py-4">
          <div class="row g-4">

            <div class="col-md-6">
              <label for="id_number" class="form-label">🆔 ID Number</label>
              <input type="text" name="id_number" id="id_number" class="form-control" placeholder="Enter ID Number" required>
              <small id="id_feedback" class="text-danger"></small>
            </div>


            <!-- Full Name -->
            <div class="col-md-6">
              <label class="form-label">👤 Full Name</label>
              <input type="text" name="full_name" class="form-control" required>
            </div>

            <!-- Date -->
            <div class="col-md-6">
              <label class="form-label">📅 Date</label>
              <input type="text" id="date_created" name="date_created" class="form-control rounded-2 shadow-sm" readonly required>
            </div>

            <!-- Address -->
            <div class="col-md-6">
              <label class="form-label">🏠 Address</label>
              <input type="text" name="address" class="form-control" required>
            </div>

            <!-- Contact -->
           <div class="col-md-6">
              <label class="form-label">📞 Contact Number</label>
              <input type="text" name="contact_number" id="contact_number" class="form-control" placeholder="Enter mobile number" required maxlength="13" pattern="^\+639\d{9}$"
              title="Enter a valid mobile number starting with +639 followed by 9 digits"></div>

            <!-- ID Type -->
            <div class="col-md-6">
              <label class="form-label">🪪 ID Type</label>
              <select name="id_type" id="id_type_select" class="form-select" required>
                <option value="">Select ID Type</option>
                <option value="Driver License">Driver License</option>
                <option value="Passport">Passport</option>
                <option value="National ID">National ID</option>
                <option value="Student ID">Student ID</option>
                <option value="Company ID">Company ID</option>
                <option value="PhilHealth ID">PhilHealth ID</option>
                <option value="Postal ID">Postal ID</option>
                <option value="Barangay ID">Barangay ID</option>
                <option value="Other">Other</option>
              </select>
            </div>
              <div class="col-md-6" id="other_id_wrapper" style="display:none;">
                <label for="other_id" class="form-label">Specify ID Type</label>
                <input type="text" name="other_id" id="other_id" class="form-control" placeholder="Type your ID here">
              </div>


            <!-- Upload Image (hidden initially) -->
            <div class="col-md-6 d-none" id="id_upload_wrapper">
              <label class="form-label">🖼 Upload ID Image</label>
              <input type="file" name="id_image" class="form-control" accept="image/*">
            </div>

            <!-- Place -->
            <div class="col-md-6">
              <label class="form-label">📍 Place of Violation</label>
              <input type="text" name="place_of_violation" class="form-control" required>
            </div>

            <!-- Penalty -->
            <div class="col-md-6">
              <label class="form-label">⚠️ Violation Type</label>
              <select name="penalty_type" id="penalty_type_select" class="form-select" required>
                <option value="">Select Violation</option>
                <option value="Warning">Warning</option>
                <option value="Styro Plastic">Styro Plastic</option>
                <option value="Illegal Dumping">Illegal Dumping</option>
                <option value="Community Service">Community Service</option>
                <option value="No Plastic Burning">No plastic burning</option>
                <option value="No Open Burning">No open burning</option>
                <option value="No Littering">No littering</option>
                <option value="No Smoking">No Smoking</option>
              </select>
            </div>


          </div>
        </div>

        <!-- Modal Footer -->
        <div class="modal-footer py-3">
          <button type="submit" class="btn bg-purple text-white px-4">💾 Save</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Cancel</button>
        </div>

      </div>
    </form>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
flatpickr("#date_created", {
  altInput: true,
  altFormat: "F j, Y",
  dateFormat: "Y-m-d",
  defaultDate: "<?= date('Y-m-d') ?>",
  allowInput: true
});
</script>
