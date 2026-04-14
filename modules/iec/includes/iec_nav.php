<?php
// Simple IEC nav for sectioned pages
$current = $section_key ?? '';
$items = [
  'general_files' => 'General Files',
  'board_docs' => 'Board Docs',
  'minutes_meeting' => 'Minutes',
  'cleanup_drive' => 'Clean-up Drive',
];
?>
<div class="d-flex flex-wrap gap-2 mb-3">
  <?php foreach ($items as $key => $label): ?>
    <a class="btn <?php echo $current === $key ? 'btn-primary' : 'btn-outline-dark'; ?> btn-sm"
       href="<?= htmlspecialchars(url_with_base('modules/iec/index.php?section=' . rawurlencode((string) $key)), ENT_QUOTES, 'UTF-8') ?>">
      <?php echo htmlspecialchars($label); ?>
    </a>
  <?php endforeach; ?>
</div>

