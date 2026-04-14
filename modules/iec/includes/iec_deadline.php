<?php
$label = $section_label ?? 'Section';
?>
<div class="card mb-3">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <div class="fw-semibold">Deadline</div>
      <div class="text-muted small"><?php echo htmlspecialchars($label); ?> deadlines are managed by admin.</div>
    </div>
    <?php if (!empty($isAdmin)): ?>
      <a class="btn btn-outline-dark btn-sm" href="<?= htmlspecialchars(url_with_base('modules/iec/index.php?section=' . rawurlencode((string) ($section_key ?? ''))), ENT_QUOTES, 'UTF-8') ?>">
        Manage
      </a>
    <?php endif; ?>
  </div>
</div>

