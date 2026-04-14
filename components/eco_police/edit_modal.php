<!-- Edit Record Modal -->
<div class="modal fade" id="editViolatorModal" tabindex="-1" aria-labelledby="editViolatorModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">

    <form action="update_function.php" method="POST" enctype="multipart/form-data" id="editViolatorForm">
      <input type="hidden" name="id" id="edit_id">

      <div class="modal-content rounded-4 shadow">

        <!-- Modal Header -->
        <div class="modal-header bg-purple text-white rounded-top-4">
          <div>
            <h5 class="modal-title fw-normal text-white" id="editViolatorModalLabel">
              <i class="fa fa-edit me-2 text-white"></i> Edit Record
            </h5>
            <small class="text-light opacity-75">Update the required fields below.</small>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <!-- Modal Body -->
        <div class="modal-body py-4">
          <div class="row g-4">

            <!-- ID Number -->
            <div class="col-md-6">
              <label for="edit_id_number" class="form-label">🆔 ID Number</label>
              <input type="text" name="id_number" id="edit_id_number" class="form-control" placeholder="Enter ID Number" required>
              <small id="edit_id_feedback" class="text-danger"></small>
            </div>

            <!-- Full Name -->
            <div class="col-md-6">
              <label class="form-label">👤 Full Name</label>
              <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
            </div>

            <!-- Date -->
            <div class="col-md-6">
              <label class="form-label">📅 Date</label>
              <input type="text" id="edit_date_created" name="date_created" class="form-control rounded-2 shadow-sm" readonly required>
            </div>

            <!-- Address -->
            <div class="col-md-6">
              <label class="form-label">🏠 Address</label>
              <input type="text" name="address" id="edit_address" class="form-control" required>
            </div>

            <!-- Contact -->
            <div class="col-md-6">
              <label class="form-label">📞 Contact Number</label>
              <input type="text"
                    name="contact_number"
                    id="edit_contact_number"
                    class="form-control"
                    placeholder="Enter mobile number"
                    required
                    maxlength="13"
                    pattern="^\+639\d{9}$"
                    title="Enter a valid mobile number starting with +639 followed by 9 digits">
            </div>

            <!-- ID Type -->
            <div class="col-md-6">
              <label class="form-label">🪪 ID Type</label>
              <select name="id_type" id="edit_id_type_select" class="form-select" required>
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

            <!-- Other ID -->
            <div class="col-md-6" id="edit_other_id_wrapper" style="display:none;">
              <label for="edit_other_id" class="form-label">Specify ID Type</label>
              <input type="text" name="other_id" id="edit_other_id" class="form-control" placeholder="Type your ID here">
            </div>

            <!-- Upload Image (hidden initially) -->
            <div class="col-md-6 d-none" id="edit_id_upload_wrapper">
              <label class="form-label">🖼 Upload NEW ID Image</label>
              <input type="file" name="id_image" id="edit_id_image" class="form-control" accept="image/*">
              <small class="text-muted">Leave blank to keep current image.</small>
            </div>

            <!-- Current Image Preview -->
            <div class="col-md-6 d-none" id="edit_current_image_wrapper">
              <label class="form-label">🖼 Current ID Image</label>
              <div class="border rounded-3 p-2 bg-light">
                <img id="edit_id_image_preview" src="" alt="ID Preview"
                     style="width:100%; max-height:180px; object-fit:contain; display:none;">
                <div id="edit_no_image" class="text-muted small">No image uploaded.</div>
              </div>
            </div>

            <!-- Place -->
            <div class="col-md-6">
              <label class="form-label">📍 Place of Violation</label>
              <input type="text" name="place_of_violation" id="edit_place_of_violation" class="form-control" required>
            </div>

            <!-- Penalty -->
            <div class="col-md-6">
              <label class="form-label">⚠️ Violation Type</label>
              <select name="penalty_type" id="edit_penalty_type_select" class="form-select" required>
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
          <button type="submit" name="update" class="btn bg-purple text-white px-4">💾 Update</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Cancel</button>
        </div>

      </div>
    </form>
  </div>
</div>

<!-- flatpickr -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
  // EDIT date picker
  const editPicker = flatpickr("#edit_date_created", {
    altInput: true,
    altFormat: "F j, Y",
    dateFormat: "Y-m-d",
    allowInput: true
  });

  // Toggle Other ID + Upload wrapper on Edit
  function toggleEditIdFields() {
    const val = document.getElementById('edit_id_type_select').value;

    // Other ID
    const otherWrap = document.getElementById('edit_other_id_wrapper');
    if (val === 'Other') otherWrap.style.display = 'block';
    else {
      otherWrap.style.display = 'none';
      document.getElementById('edit_other_id').value = '';
    }

    // Show upload & current preview once may id_type selected
    const uploadWrap = document.getElementById('edit_id_upload_wrapper');
    const currentWrap = document.getElementById('edit_current_image_wrapper');

    if (val) {
      uploadWrap.classList.remove('d-none');
      currentWrap.classList.remove('d-none');
    } else {
      uploadWrap.classList.add('d-none');
      currentWrap.classList.add('d-none');
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('edit_id_type_select').addEventListener('change', toggleEditIdFields);
    toggleEditIdFields();

    // Preview if user selects a NEW file
    document.getElementById('edit_id_image').addEventListener('change', function () {
      const img = document.getElementById('edit_id_image_preview');
      const noImg = document.getElementById('edit_no_image');

      if (this.files && this.files[0]) {
        const url = URL.createObjectURL(this.files[0]);
        img.src = url;
        img.style.display = 'block';
        noImg.style.display = 'none';
      }
    });
  });
</script>
