<div class="modal fade modal-ux-compact" id="viewCleanupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <div class="w-100 d-flex align-items-start justify-content-between">
          <div>
            <h5 class="modal-title"><i class="fa fa-eye"></i> View Record</h5>
            <div class="modal-subtitle">Cleanup drive details + gallery.</div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
      </div>

      <div class="modal-body">
        <div id="cleanupLoading" class="text-center py-5 text-muted">
          <div class="spinner-border" role="status"></div>
          <div class="mt-2">Loading...</div>
        </div>

        <div id="cleanupContent" hidden>
          <div class="row g-3 mb-2">
            <div class="col-md-4"><div class="ux-card"><div class="k">Barangay</div><div class="v" id="vBarangay">—</div></div></div>
            <div class="col-md-8"><div class="ux-card"><div class="k">Activity Title</div><div class="v" id="vTitle">—</div></div></div>

            <div class="col-md-3"><div class="ux-card"><div class="k">Date</div><div class="v" id="vDate">—</div></div></div>
            <div class="col-md-3"><div class="ux-card"><div class="k">Time</div><div class="v" id="vTime">—</div></div></div>
            <div class="col-md-3"><div class="ux-card"><div class="k">Participants</div><div class="v" id="vParticipants">—</div></div></div>
            <div class="col-md-3"><div class="ux-card"><div class="k">Status</div><div class="v" id="vStatus">—</div></div></div>

            <div class="col-md-6"><div class="ux-card"><div class="k">Venue</div><div class="v" id="vVenue">—</div></div></div>
            <div class="col-md-6"><div class="ux-card"><div class="k">Attendance Proof</div><div class="v" id="vAttendance">—</div></div></div>

            <div class="col-12"><div class="ux-card"><div class="k">Description</div><div class="v iec-prewrap" id="vDesc">—</div></div></div>
          </div>

          <div class="d-flex align-items-center justify-content-between mt-3 mb-2">
            <div class="fw-bold">Photos</div>
            <span class="text-muted small" id="vPhotoCount">0 file(s)</span>
          </div>

          <div class="row g-2" id="vGallery"></div>
          <div id="vNoPhotos" class="text-muted small mt-2" hidden>No photos uploaded.</div>
        </div>
      </div>

      <div class="modal-footer d-flex justify-content-end">
        <button class="btn btn-ux-cancel" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
