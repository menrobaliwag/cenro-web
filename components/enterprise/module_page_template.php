<?php
declare(strict_types=1);

/**
 * Enterprise module page template.
 *
 * Expected variables (set in the including file):
 * - $page_title (string)
 * - $page_subtitle (string|null)
 * - $breadcrumbs (array<array{label:string, href?:string, active?:bool}>)
 * - $kpis (array<array{label:string, value:string, icon?:string}>)
 * - $filters_html (string)
 * - $table_html (string)
 * - $fab (array{target:string, label?:string, icon?:string}|null)
 * - $modals_html (string|null)
 */

$page_title = isset($page_title) ? (string)$page_title : '';
$page_subtitle = isset($page_subtitle) ? (string)$page_subtitle : '';
$breadcrumbs = isset($breadcrumbs) && is_array($breadcrumbs) ? $breadcrumbs : [];
$kpis = isset($kpis) && is_array($kpis) ? $kpis : [];
$filters_html = isset($filters_html) ? (string)$filters_html : '';
$table_html = isset($table_html) ? (string)$table_html : '';
$fab = isset($fab) && is_array($fab) ? $fab : null;
$modals_html = isset($modals_html) ? (string)$modals_html : '';

function ent_h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>

<div class="page-wrapper">
  <div class="page-breadcrumb">
    <div class="container-fluid">
      <div class="page-header">
        <div>
          <h4 class="page-title fw-semibold text-dark"><?= ent_h($page_title) ?></h4>
          <?php if ($page_subtitle !== ''): ?>
            <div class="page-subtitle"><?= ent_h($page_subtitle) ?></div>
          <?php endif; ?>
        </div>
        <?php if (!empty($breadcrumbs)): ?>
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
              <?php foreach ($breadcrumbs as $b): ?>
                <?php
                  $label = (string)($b['label'] ?? '');
                  $href = (string)($b['href'] ?? '');
                  $active = (bool)($b['active'] ?? false);
                ?>
                <li class="breadcrumb-item<?= $active ? ' active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                  <?php if (!$active && $href !== ''): ?>
                    <a href="<?= ent_h($href) ?>"><?= ent_h($label) ?></a>
                  <?php else: ?>
                    <?= ent_h($label) ?>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ol>
          </nav>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="container-fluid">
    <?php if (!empty($kpis)): ?>
      <div class="row g-3 mb-4">
        <?php foreach ($kpis as $k): ?>
          <?php
            $label = (string)($k['label'] ?? '');
            $value = (string)($k['value'] ?? '');
            $icon = (string)($k['icon'] ?? 'mdi mdi-chart-box-outline');
          ?>
          <div class="col-12 col-sm-6 col-lg-3">
            <div class="card kpi-card h-100">
              <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="<?= ent_h($icon) ?>"></i></div>
                <div>
                  <div class="kpi-label"><?= ent_h($label) ?></div>
                  <div class="kpi-value"><?= ent_h($value) ?></div>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?= $filters_html ?>

    <div class="card fade-in">
      <div class="card-body">
        <?= $table_html ?>
      </div>
    </div>
  </div>
</div>

<?php if ($fab && !empty($fab['target'])): ?>
  <button type="button" class="fab" data-bs-toggle="modal" data-bs-target="<?= ent_h((string)$fab['target']) ?>" aria-label="<?= ent_h((string)($fab['label'] ?? 'Add')) ?>">
    <i class="<?= ent_h((string)($fab['icon'] ?? 'fa fa-plus')) ?>"></i>
  </button>
<?php endif; ?>

<?= $modals_html ?>

