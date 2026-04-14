<!-- Theme Customizer Modal -->
<div class="modal fade" id="themeCustomizerModal" tabindex="-1" aria-labelledby="themeCustomizerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="themeCustomizerModalLabel">
          <i class="mdi mdi-wrench"></i> Theme Customizer
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="themeCustomizerForm">
        <div class="modal-body">
          <div class="mb-3">
            <label for="system_title" class="form-label">System Title</label>
            <input type="text" class="form-control" name="system_title" id="system_title" value="<?= htmlspecialchars($_SESSION['system_title'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Topbar Color</label>
            <select name="topbar_skin" class="form-select">
              <option value="dark" <?= ($_SESSION['topbar_skin'] ?? '') == 'dark' ? 'selected' : '' ?>>Dark</option>
              <option value="skin1" <?= ($_SESSION['topbar_skin'] ?? '') == 'skin1' ? 'selected' : '' ?>>Blue</option>
              <option value="skin2" <?= ($_SESSION['topbar_skin'] ?? '') == 'skin2' ? 'selected' : '' ?>>Light</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Sidebar Color</label>
            <select name="sidebar_skin" class="form-select">
              <option value="dark" <?= ($_SESSION['sidebar_skin'] ?? '') == 'dark' ? 'selected' : '' ?>>Dark</option>
              <option value="skin1" <?= ($_SESSION['sidebar_skin'] ?? '') == 'skin1' ? 'selected' : '' ?>>Blue</option>
              <option value="skin2" <?= ($_SESSION['sidebar_skin'] ?? '') == 'skin2' ? 'selected' : '' ?>>Light</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <span id="customizerMsg" class="text-success me-auto"></span>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
$(document).ready(function() {
  $('#themeCustomizerForm').on('submit', function(e) {
    e.preventDefault();
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    $.ajax({
      url: '../auth/save_theme.php',
      type: 'POST',
      headers: { 'X-CSRF-Token': csrfToken },
      data: $(this).serialize(),
      success: function(res) {
        $('#customizerMsg').text('Theme saved! Reloading...');
        setTimeout(() => location.reload(), 1000);
      },
      error: function() {
        $('#customizerMsg').text('Failed to save.').addClass('text-danger');
      }
    });
  });
});
</script>
