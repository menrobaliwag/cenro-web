<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/security.php';
secure_session_start();

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);

// Expected shape (all optional):
// ['title' => string, 'message' => string, 'variant' => 'success|danger|warning|info|primary|secondary', 'delay' => int]
?>

<div class="toast-container position-fixed top-0 end-0 p-3" id="app-toast-container" style="z-index: 1080;"></div>
<script>
  window.__FLASH_TOAST__ = <?= json_encode($toast, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>

