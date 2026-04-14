<!-- ============================================================== -->
<!-- EDIT MODAL -->
<!-- ============================================================== -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form id="editForm">
      <div class="modal-content rounded-4 shadow border-0">
        
        <!-- Modal Header -->
        <div class="modal-header bg-purple text-white rounded-top-4">
          <div>
            <h5 class="modal-title fw-normal fs-5" id="editModalLabel">
              <i class="fa fa-pen me-2"></i>Time in/Time out Record
            </h5>
            <small class="text-light opacity-75">Update the Truck record Time in/Time out details below.</small>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <!-- Modal Body -->
        <div class="modal-body py-4 px-4">
          <input type="hidden" name="id" id="edit_id">

          <div class="row g-4">
            <div class="col-md-6">
              <label for="edit_date" class="form-label fw-normal">📅 Date</label>
              <input type="date" name="date" id="edit_date" class="form-control shadow-sm rounded-3" required>
            </div>
            <div class="col-md-6">
              <label for="edit_name" class="form-label fw-normal">🚛 Driver</label>
              <input type="text" name="name" id="edit_name" class="form-control shadow-sm rounded-3">
            </div>
            <div class="col-md-6">
              <label for="edit_timein" class="form-label fw-normal">⏱ Time In</label>
              <input type="time" name="time_in" id="edit_timein" class="form-control shadow-sm rounded-3" required>
            </div>
            <div class="col-md-6">
              <label for="edit_timeout" class="form-label fw-normal">⏱ Time Out</label>
              <input type="time" name="time_out" id="edit_timeout" class="form-control shadow-sm rounded-3" required>
            </div>
            <div class="col-md-6">
              <label for="edit_truck" class="form-label fw-normal">🚚 Truck No.</label>
              <input type="text" name="truck" id="edit_truck" class="form-control shadow-sm rounded-3">
            </div>
            <div class="col-md-6">
              <label for="edit_area" class="form-label fw-normal">📍 Area</label>
              <input type="text" name="area" id="edit_area" class="form-control shadow-sm rounded-3">
            </div>
            <div class="col-md-6">
              <label for="edit_dumping" class="form-label fw-normal">🏞 Dumping Site</label>
              <input type="text" name="dumping" id="edit_dumping" class="form-control shadow-sm rounded-3">
            </div>
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
<!-- ============================================================== -->
<!-- END EDIT MODAL -->
<!-- ============================================================== -->
