<?php
// /city_enro/includes/footer_scripts.php
$uri = $_SERVER['REQUEST_URI'] ?? '';
$allowedUiSkins = ['skin1', 'skin2', 'skin3', 'skin4', 'skin5', 'skin6'];
$sessionTopbarSkin = (string)($_SESSION['topbar_skin'] ?? 'skin6');
$sessionSidebarSkin = (string)($_SESSION['sidebar_skin'] ?? 'skin6');
if (!in_array($sessionTopbarSkin, $allowedUiSkins, true)) {
    $sessionTopbarSkin = 'skin6';
}
if (!in_array($sessionSidebarSkin, $allowedUiSkins, true)) {
    $sessionSidebarSkin = 'skin6';
}
?>

<script>
  window.BASE_URL = <?= json_encode((string)BASE_URL, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<!-- âœ… jQuery (load ONCE only) -->
<script src="<?= url_with_base('assets/libs/jquery/dist/jquery.min.js') ?>"></script>

<!-- âœ… Bootstrap 5 Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- âœ… DataTables Core + Bootstrap 5 -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- âœ… DataTables Export Buttons -->
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<!-- âœ… Export Dependencies -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<!-- âœ… Flatpickr -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<!-- âœ… Perfect Scrollbar BEFORE app.min.js -->
<script src="<?= url_with_base('assets/libs/perfect-scrollbar/dist/perfect-scrollbar.jquery.min.js') ?>"></script>
<script src="<?= url_with_base('assets/extra-libs/sparkline/sparkline.js') ?>"></script>

<!-- âœ… Admin Template Scripts (Only Once) -->
<script src="<?= url_with_base('dist/js/app.min.js') ?>"></script>
<script src="<?= url_with_base('dist/js/app-style-switcher.js') ?>"></script>
<script src="<?= url_with_base('dist/js/waves.js') ?>"></script>
<script src="<?= url_with_base('dist/js/sidebarmenu.js') ?>"></script>
<script src="<?= url_with_base('dist/js/custom.js') ?>"></script>
<script src="<?= url_with_base('dist/js/enterprise.js') ?>"></script>
<script src="<?= url_with_base('assets/js/enro-ui.js') ?>"></script>

<script>
  $(function () {
    "use strict";
    const SESSION_UI = {
      LogoBg: <?= json_encode($sessionTopbarSkin, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      NavbarBg: <?= json_encode($sessionTopbarSkin, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      SidebarColor: <?= json_encode($sessionSidebarSkin, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
    };

    const DEFAULTS = {
      Theme: false,
      Layout: "vertical",
      LogoBg: SESSION_UI.LogoBg,
      NavbarBg: SESSION_UI.NavbarBg,
      SidebarType: "full",
      SidebarColor: SESSION_UI.SidebarColor,
      SidebarPosition: true,
      HeaderPosition: true,
      BoxedLayout: false
    };

    if ($("#main-wrapper").length && typeof $("#main-wrapper").AdminSettings === "function") {
      const isAppShell = $("body").hasClass("app-shell");
      const savedSettings = {
        Theme: localStorage.getItem("Theme") === "true",
        Layout: localStorage.getItem("Layout") || DEFAULTS.Layout,
        LogoBg: localStorage.getItem("LogoBg") || DEFAULTS.LogoBg,
        NavbarBg: localStorage.getItem("NavbarBg") || DEFAULTS.NavbarBg,
        SidebarType: localStorage.getItem("SidebarType") || DEFAULTS.SidebarType,
        SidebarColor: localStorage.getItem("SidebarColor") || DEFAULTS.SidebarColor,
        SidebarPosition: localStorage.getItem("SidebarPosition") === "true" || DEFAULTS.SidebarPosition,
        HeaderPosition: localStorage.getItem("HeaderPosition") === "true" || DEFAULTS.HeaderPosition,
        BoxedLayout: localStorage.getItem("BoxedLayout") === "true"
      };

      if (isAppShell) {
        savedSettings.Theme = false;
        savedSettings.Layout = "vertical";
        savedSettings.LogoBg = SESSION_UI.LogoBg;
        savedSettings.NavbarBg = SESSION_UI.NavbarBg;
        // Force desktop-safe sidebar mode; avoid hidden overlay state from stale localStorage.
        savedSettings.SidebarType = "full";
        savedSettings.SidebarColor = SESSION_UI.SidebarColor;
        savedSettings.SidebarPosition = true;
        savedSettings.HeaderPosition = true;
        savedSettings.BoxedLayout = false;
        localStorage.setItem("LogoBg", savedSettings.LogoBg);
        localStorage.setItem("NavbarBg", savedSettings.NavbarBg);
        localStorage.setItem("SidebarColor", savedSettings.SidebarColor);
        localStorage.setItem("SidebarType", "full");
      }

      $("#main-wrapper").AdminSettings(savedSettings);
    }
    // ============================
    // Safe DataTable Init
    // ============================
    function countColumns($cells) {
      let count = 0;
      $cells.each(function () {
        const span = parseInt($(this).attr('colspan') || '1', 10);
        count += Number.isFinite(span) && span > 0 ? span : 1;
      });
      return count;
    }

    function normalizeTableColumnCount($table) {
      const $headRow = $table.find('thead tr').first();
      if (!$headRow.length) return 0;

      const expected = countColumns($headRow.children('th,td'));
      if (!expected) return 0;

      function normalizeSectionRows($rows) {
        $rows.each(function () {
          const $row = $(this);
          const $cells = $row.children('th,td');
          if (!$cells.length) return;

          const current = countColumns($cells);
          if (current === expected) return;

          if (current < expected) {
            const $messageCell = $cells.filter(function () {
              return $(this).text().trim().length > 0;
            }).first();

            if ($cells.length === 1) {
              $cells.attr('colspan', expected);
              return;
            }

            if ($messageCell.length) {
              const span = parseInt($messageCell.attr('colspan') || '1', 10);
              $messageCell.attr('colspan', span + (expected - current));
              return;
            }

            const missing = expected - current;
            for (let i = 0; i < missing; i += 1) {
              $row.append('<td></td>');
            }
            return;
          }

          let overflow = current - expected;
          const $reversed = $($cells.get().reverse());
          $reversed.each(function () {
            if (overflow <= 0) return false;

            const $cell = $(this);
            const span = parseInt($cell.attr('colspan') || '1', 10);
            if (span <= overflow) {
              overflow -= span;
              $cell.remove();
            } else {
              $cell.attr('colspan', span - overflow);
              overflow = 0;
            }
            return undefined;
          });
        });
      }

      normalizeSectionRows($table.find('tbody tr'));
      normalizeSectionRows($table.find('tfoot tr'));
      return expected;
    }

    function extractAndRemoveColspanPlaceholderRow($table, expectedCols) {
      // DataTables does not support colspan/rowspan in tbody rows. Pages sometimes render a single
      // "No records..." or "Loading..." row with colspan=N, which triggers tn/18 warnings.
      let message = '';
      const $tbody = $table.find('tbody').first();
      if (!$tbody.length || expectedCols <= 0) return message;

      $tbody.find('tr').each(function () {
        const $row = $(this);
        const $cells = $row.children('th,td');
        if ($cells.length !== 1) return;

        const $cell = $cells.eq(0);
        const rawSpan = String($cell.attr('colspan') || '1');
        const span = parseInt(rawSpan, 10);
        if (!Number.isFinite(span) || span < expectedCols) return;

        const text = String($cell.text() || '').trim();
        if (text && !message) message = text;
        $row.remove();
      });

      return message;
    }

    function toPlainText(raw) {
      return $('<div>').html(raw == null ? '' : String(raw)).text().trim();
    }

    function isNumericValue(raw) {
      const text = toPlainText(raw);
      if (!text) return false;
      const compact = text
        .replace(/,/g, '')
        .replace(/\s+/g, '')
        .replace(/[()]/g, '')
        .replace(/(kls|kgs|kg|php|₱|%)/gi, '');
      return /^[-+]?\d*\.?\d+$/.test(compact);
    }

    function applyNumericAlignment(dt) {
      const colCount = dt.columns().count();
      for (let colIdx = 0; colIdx < colCount; colIdx += 1) {
        const headerCell = dt.column(colIdx).header();
        const headerText = toPlainText(headerCell ? $(headerCell).text() : '').toLowerCase();
        if (/(action|actions|restore|status|name|address|barangay)/.test(headerText)) continue;

        let total = 0;
        let numeric = 0;
        dt.column(colIdx).data().each(function (val) {
          const plain = toPlainText(val);
          if (!plain) return;
          total += 1;
          if (isNumericValue(val)) numeric += 1;
        });

        if (total > 0 && (numeric / total) >= 0.7) {
          if (headerCell) $(headerCell).addClass('enro-dt-num');
          $(dt.column(colIdx).nodes()).addClass('enro-dt-num');
          const footerCell = dt.column(colIdx).footer();
          if (footerCell) $(footerCell).addClass('enro-dt-num');
        }
      }
    }

    function bindRowSelection($table) {
      if ($table.data('enroRowSelectBound')) return;
      $table.data('enroRowSelectBound', true);

      $table.on('click', 'tbody tr', function (event) {
        if ($(event.target).closest('a,button,input,select,textarea,label,form,.dropdown-menu,.dropdown-toggle').length) {
          return;
        }
        $(this).addClass('enro-dt-row-selected').siblings().removeClass('enro-dt-row-selected');
      });
    }

    function bindDropdownLayerFix($table) {
      if (!$table || !$table.length || $table.data('enroDropdownLayerBound')) return;
      $table.data('enroDropdownLayerBound', true);

      $table.on('show.bs.dropdown', 'tbody td .dropdown', function () {
        const $row = $(this).closest('tr');
        $row.addClass('enro-row-menu-open').siblings('.enro-row-menu-open').removeClass('enro-row-menu-open');
      });

      $table.on('hidden.bs.dropdown', 'tbody td .dropdown', function () {
        $(this).closest('tr').removeClass('enro-row-menu-open');
      });
    }

    function applyHeaderTitles($table) {
      if (!$table || !$table.length) return;
      $table.find('thead th').each(function () {
        const text = $(this).text().replace(/\s+/g, ' ').trim();
        if (text) {
          $(this).attr('title', text);
        }
      });
    }

    function normalizeDataTableShell($table) {
      if (!$table || !$table.length) return;

      const $hostWrap = $table.closest('.table-modern-wrap, .scrollable-table-wrapper');
      if ($hostWrap.length) {
        $hostWrap
          .addClass('dt-host enro-dt-shell enro-table-shell')
          .css({
            'max-height': 'none',
            'height': 'auto',
            'overflow-y': 'visible'
          });
      }

      const $card = $table.closest('.card');
      if ($card.length) {
        $card.addClass('enro-dt-card');
      }

      const $cardBody = $table.closest('.card-body');
      if ($cardBody.length) {
        $cardBody.addClass('enro-dt-card-body');
      }

      const $parent = $table.parent();
      if (!$parent.hasClass('table-responsive')) {
        $table.wrap('<div class=\"table-responsive enro-dt-responsive\"></div>');
      } else {
        $parent.addClass('enro-dt-responsive');
      }

      $table.closest('.table-responsive').css({
        'max-height': 'none',
        'height': 'auto',
        'overflow-y': 'visible'
      });
    }

    function normalizeDataTableToolbar(dt) {
      const $container = $(dt.table().container());
      let $toolbar = $container.children('.enro-dt-toolbar');
      if (!$toolbar.length) {
        $toolbar = $('<div class=\"enro-dt-toolbar\"></div>');
        $container.prepend($toolbar);
      }

      const $buttons = $container.children('.dt-buttons');
      const $filter = $container.children('.dataTables_filter');

      if ($buttons.length) {
        $toolbar.append($buttons);
      }
      if ($filter.length) {
        $toolbar.append($filter);
      }
    }

    function normalizeDataTableFooter(dt) {
      const $container = $(dt.table().container());
      let $footer = $container.children('.enro-dt-footer');
      if (!$footer.length) {
        $footer = $('<div class=\"enro-dt-footer\"></div>');
        $container.append($footer);
      }

      const $info = $container.children('.dataTables_info');
      const $paginate = $container.children('.dataTables_paginate');

      if ($info.length) {
        $footer.append($info);
      }
      if ($paginate.length) {
        $footer.append($paginate);
      }
    }

    function stabilizeDataTableLayout(dt, opts) {
      const options = opts || {};
      const adjustColumns = options.adjustColumns !== false;
      const $container = $(dt.table().container());
      const $table = $(dt.table().node());
      const $hostWrap = $table.closest('.table-modern-wrap, .scrollable-table-wrapper');
      const $card = $table.closest('.card');
      const $cardBody = $table.closest('.card-body');

      if ($hostWrap.length) {
        $hostWrap
          .addClass('enro-table-shell')
          .css({
            'max-height': 'none',
            'height': 'auto',
            'overflow-y': 'visible'
          });
      }
      if ($card.length) {
        $card.addClass('enro-dt-card');
      }
      if ($cardBody.length) {
        $cardBody
          .addClass('enro-dt-card-body')
          .css({
            'max-height': 'none',
            'height': 'auto',
            'overflow-y': 'visible'
          });
      }

      $container.find('.dataTables_scrollBody').css({
        'max-height': 'none',
        'height': 'auto',
        'overflow-y': 'visible'
      });
      $container.find('.dataTables_scroll, .dataTables_scrollHead').css({
        'max-height': 'none',
        'height': 'auto',
        'overflow': 'visible'
      });
      $container.find('.dropdown-menu').css('z-index', '1085');
      if (adjustColumns) {
        dt.columns.adjust();
      }
    }

    function bindReflowEvents(dt, $table) {
      if ($table.data('enroDtReflowBound')) return;
      $table.data('enroDtReflowBound', true);

      $(window).on('resize.enroDtGrid', function () {
        stabilizeDataTableLayout(dt);
      });
    }

    function normalizeStaticTableShells() {
      $('.table-modern-wrap, .scrollable-table-wrapper').has('table').each(function () {
        const $shell = $(this);
        $shell
          .addClass('enro-table-shell')
          .css({
            'max-height': 'none',
            'height': 'auto',
            'overflow-y': 'visible'
          });

        const $card = $shell.closest('.card');
        if ($card.length) {
          $card.addClass('enro-dt-card');
        }

        const $cardBody = $shell.closest('.card-body');
        if ($cardBody.length) {
          $cardBody.addClass('enro-dt-card-body');
        }
      });
    }

    function isLargeDataTable($table) {
      if (!$table || !$table.length) return false;
      return $table.find('tbody tr').length >= 1500;
    }

    function bindDebouncedGlobalSearch(dt, delayMs) {
      const $container = $(dt.table().container());
      const $input = $container.find('div.dataTables_filter input');
      if (!$input.length || $input.data('enroDebounceBound')) return;

      let timer = null;
      $input.data('enroDebounceBound', true);
      $input.off('.DT');
      $input.on('input.enroDebounce', function () {
        const value = this.value;
        clearTimeout(timer);
        timer = setTimeout(function () {
          dt.search(value).draw();
        }, delayMs);
      });
    }

    $(document).on('init.dt.enroLayout', function (event, settings) {
      if (!settings || !settings.nTable || !$.fn.dataTable) return;
      const dt = new $.fn.dataTable.Api(settings);
      const $table = $(dt.table().node());
      const isLarge = isLargeDataTable($table);
      normalizeDataTableShell($table);
      bindRowSelection($table);
      bindDropdownLayerFix($table);
      normalizeDataTableToolbar(dt);
      normalizeDataTableFooter(dt);
      stabilizeDataTableLayout(dt, { adjustColumns: !isLarge });
    });

    normalizeStaticTableShells();

    if ($('#file_export').length) {
      const $fileExport = $('#file_export');
      const serverSourceUrl = String($fileExport.data('sourceUrl') || '').trim();
      const sourceDateStart = String($fileExport.data('dateStart') || '').trim();
      const sourceDateEnd = String($fileExport.data('dateEnd') || '').trim();
      const isServerMode = serverSourceUrl.length > 0;
      const isLargeDataset = !isServerMode && isLargeDataTable($fileExport);
      const isSortersReportTable = $fileExport.hasClass('sorters-no-scroll-table');
      const reportNumericTargets = [4, 5, 6, 7, 8, 9, 10, 11, 12, 13];
      const reportActionTarget = 14;

      const buttonConfigs = [
        { extend: 'excelHtml5', text: '<i class="fa fa-file-excel"></i> Excel', className: 'btn btn-outline-secondary btn-sm me-2' },
        { extend: 'pdfHtml5', text: '<i class="fa fa-file-pdf"></i> PDF', className: 'btn btn-outline-secondary btn-sm me-2' },
        {
          extend: 'print',
          text: '<i class="fa fa-print"></i> Print',
          className: 'btn btn-outline-secondary btn-sm',
          exportOptions: { modifier: { search: 'applied', order: 'applied', page: 'all' } }
        }
      ];

      if (isServerMode && isSortersReportTable) {
        const printButton = buttonConfigs.find(function (btn) { return btn.extend === 'print'; });
        if (printButton) {
          printButton.action = function (_e, dt) {
            const params = new URLSearchParams();
            if (sourceDateStart) params.set('date_start', sourceDateStart);
            if (sourceDateEnd) params.set('date_end', sourceDateEnd);

            const globalSearch = String(dt.search() || '').trim();
            if (globalSearch) params.set('search', globalSearch);

            const order = dt.order();
            if (order && order.length) {
              params.set('order_col', String(order[0][0] ?? '2'));
              params.set('order_dir', String(order[0][1] ?? 'desc'));
            }

            params.set('return', window.location.pathname + window.location.search);

            const printUrl = (window.BASE_URL || '') + '/modules/mrf/sorters/sorter_record_print.php?' + params.toString();
            window.open(printUrl, '_blank', 'noopener,noreferrer');
          };
        }
      }

      normalizeDataTableShell($fileExport);
      if (isSortersReportTable) applyHeaderTitles($fileExport);
      const expectedCols = normalizeTableColumnCount($fileExport);
      const extractedEmptyMessage = extractAndRemoveColspanPlaceholderRow($fileExport, expectedCols);

      if (expectedCols > 0 && !$.fn.DataTable.isDataTable($fileExport[0])) {
        const emptyMessage = (!isServerMode && extractedEmptyMessage) ? extractedEmptyMessage : 'No records found.';
        const dtOptions = {
          paging: true,
          searching: true,
          ordering: true,
          pageLength: 10,
          lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
          order: isSortersReportTable ? [[2, 'desc']] : [],
          processing: isServerMode,
          serverSide: isServerMode,
          deferRender: true,
          searchDelay: (isServerMode || isLargeDataset) ? 350 : 0,
          orderClasses: !isLargeDataset,
          search: {
            smart: !isLargeDataset,
            regex: false,
            caseInsensitive: true
          },
          autoWidth: false,
          scrollX: false,
          scrollCollapse: false,
          columnDefs: isSortersReportTable
            ? [
                { targets: '_all', className: 'align-middle' },
                { targets: reportNumericTargets, className: 'align-middle text-center dt-report-num' },
                { targets: [0], className: 'align-middle text-center', orderable: false, searchable: false },
                { targets: [2], className: 'align-middle text-center' },
                { targets: [reportActionTarget], className: 'align-middle text-center dt-report-action', width: '100px', orderable: false, searchable: false }
              ]
            : [{ targets: '_all', className: 'align-middle' }],
          dom: 'Bfrtip',
          buttons: buttonConfigs,
          language: {
            search: '_INPUT_',
            searchPlaceholder: 'Search records...',
            emptyTable: emptyMessage,
            zeroRecords: emptyMessage,
            processing: isServerMode ? 'Loading...' : undefined
          },
          initComplete: function () {
            const api = this.api();
            if (isLargeDataset) {
              bindDebouncedGlobalSearch(api, 350);
              return;
            }
            api.columns.adjust();
          }
        };

        if (isServerMode) {
          dtOptions.ajax = {
            url: serverSourceUrl,
            type: 'GET',
            data: function (d) {
              if (sourceDateStart) d.date_start = sourceDateStart;
              if (sourceDateEnd) d.date_end = sourceDateEnd;
            }
          };
        }

        const dt = $fileExport.DataTable(dtOptions);

        if (!isSortersReportTable && !isLargeDataset && !isServerMode) applyNumericAlignment(dt);
        bindRowSelection($fileExport);
        bindDropdownLayerFix($fileExport);
        normalizeDataTableToolbar(dt);
        normalizeDataTableFooter(dt);
        stabilizeDataTableLayout(dt, { adjustColumns: !isLargeDataset || isServerMode });
        bindReflowEvents(dt, $fileExport);

        dt.on('draw.enroGrid', function () {
          if (!isSortersReportTable && !isLargeDataset && !isServerMode) applyNumericAlignment(dt);
          if (isSortersReportTable) applyHeaderTitles($fileExport);
          normalizeDataTableToolbar(dt);
          normalizeDataTableFooter(dt);
          stabilizeDataTableLayout(dt, { adjustColumns: !isLargeDataset || isServerMode });
        });
      }
    }

    // ============================
    // Flatpickr Safe Init (optional)
    // ============================
    if (typeof flatpickr === "function") {
      // If you use class="datepick"
      if ($(".datepick").length) {
        flatpickr(".datepick", { dateFormat: "Y-m-d" });
      }
    }

    // ============================
    // âœ… CSRF auto-injection + AJAX header
    // ============================
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    if (csrfToken) {
      document.querySelectorAll('form[method="post"], form[method="POST"]').forEach((form) => {
        if (!form.querySelector('input[name="csrf_token"]')) {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'csrf_token';
          input.value = csrfToken;
          form.appendChild(input);
        }
      });

      if (window.jQuery) {
        $.ajaxSetup({
          headers: { 'X-CSRF-Token': csrfToken }
        });
      }
    }

    // ============================
    // Global Alert Auto-dismiss
    // ============================
    document.querySelectorAll('.alert').forEach(function (alertEl) {
      const mode = String(alertEl.getAttribute('data-autohide') || 'on').toLowerCase();
      if (mode === 'off' || mode === 'false' || alertEl.classList.contains('alert-permanent')) {
        return;
      }

      const rawMs = parseInt(String(alertEl.getAttribute('data-autohide-ms') || ''), 10);
      const delayMs = Number.isFinite(rawMs) && rawMs >= 800 ? rawMs : 2200;

      window.setTimeout(function () {
        if (!alertEl.parentNode) return;
        alertEl.style.transition = 'opacity .35s ease, transform .35s ease';
        alertEl.style.opacity = '0';
        alertEl.style.transform = 'translateY(-4px)';
        window.setTimeout(function () {
          if (alertEl.parentNode) {
            alertEl.parentNode.removeChild(alertEl);
          }
        }, 380);
      }, delayMs);
    });
  });
</script>

<?php include dirname(__DIR__) . '/components/ui/toast.php'; ?>

<!-- âœ… Page-specific scripts (supports BOTH /modules and /admin) -->
<?php if (strpos($uri, '/modules/mrf/truck_record/index.php') !== false || strpos($uri, '/modules/truck_record/index.php') !== false): ?>
  <script src="<?= url_with_base('dist/js/truck_record/time_function.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/truck_record/time_archivedtable_function.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/truck_record/time_archive_function.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/truck_record/time_view_function.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/truck_record/time_edit_function.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/truck_record/flatpickr.js') ?>"></script>

<?php elseif (strpos($uri, '/modules/mrf/sorters/index.php') !== false || strpos($uri, '/modules/sorters/index.php') !== false): ?>
  <script src="<?= url_with_base('dist/js/sorters/sorters_unarchive_function.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/sorters/sorters_archive_function.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/sorters/sorters_view_function.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/sorters/sorters_edit_function.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/sorters/sorters_archive_pagination.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/truck_record/flatpickr.js') ?>"></script>

<?php elseif (strpos($uri, '/modules/mrf/other_waste/index.php') !== false || strpos($uri, '/modules/other_waste/index.php') !== false): ?>
  <script src="<?= url_with_base('dist/js/other_waste/edit_function.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/other_waste/archive_function.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/other_waste/view_function.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/other_waste/unarchive_function.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/truck_record/flatpickr.js') ?>"></script>

<?php elseif (strpos($uri, '/modules/mrf/waste_reduction/index.php') !== false || strpos($uri, '/modules/waste_reduction/index.php') !== false): ?>
  <script src="<?= url_with_base('dist/js/waste_reduction/edit_function.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/waste_reduction/archive_function.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/waste_reduction/view_function.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/waste_reduction/archivedtable_function.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/waste_reduction/unarchive_function.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/truck_record/flatpickr.js') ?>"></script>

<?php elseif (strpos($uri, '/modules/mrf/eco_police/index.php') !== false || strpos($uri, '/modules/eco_police/index.php') !== false): ?>
  <script src="<?= url_with_base('dist/js/eco_police/edit_function.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/eco_police/archive_function.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/eco_police/view_function.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/eco_police/unarchive_function.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/eco_police/flatpickr.js') ?>"></script>
  <script src="<?= url_with_base('dist/js/eco_police/function.js') ?>"></script>

<?php elseif (strpos($uri, '/modules/iec/index.php') !== false): ?>
  <?php include dirname(__DIR__) . '/components/iec_modal/deadline_modal.php'; ?>

  <?php include dirname(__DIR__) . '/components/iec_modal/board_docs_modal/add_board_docs.php'; ?>
  <?php include dirname(__DIR__) . '/components/iec_modal/board_docs_modal/edit_board_docs.php'; ?>
  <?php include dirname(__DIR__) . '/components/iec_modal/board_docs_modal/delete_board_docs.php'; ?>

  <?php include dirname(__DIR__) . '/components/iec_modal/general_files_modal/add_genaral_files.php'; ?>
  <?php include dirname(__DIR__) . '/components/iec_modal/general_files_modal/edit_genaral_files.php'; ?>
  <?php include dirname(__DIR__) . '/components/iec_modal/general_files_modal/delete_genaral_files.php'; ?>

  <?php include dirname(__DIR__) . '/components/iec_modal/minutes_of_meeting_modal/add_minutes_of_meeting.php'; ?>
  <?php include dirname(__DIR__) . '/components/iec_modal/minutes_of_meeting_modal/edit_minutes_of_meeting.php'; ?>
  <?php include dirname(__DIR__) . '/components/iec_modal/minutes_of_meeting_modal/delete_minutes_of_meeting.php'; ?>

  <?php include dirname(__DIR__) . '/components/iec_modal/clean_up_modal/add_clean_up.php'; ?>
  <?php include dirname(__DIR__) . '/components/iec_modal/clean_up_modal/view_clean_up.php'; ?>
  <?php include dirname(__DIR__) . '/components/iec_modal/clean_up_modal/photo_preview_modal.php'; ?>
  <?php include dirname(__DIR__) . '/components/iec_modal/clean_up_modal/edit_clean_up.php'; ?>
  <?php include dirname(__DIR__) . '/components/iec_modal/clean_up_modal/delete_clean_up.php'; ?>

  <script src="<?= url_with_base('dist/js/iec/index.js') ?>"></script>

<?php endif; ?>

<!-- âœ… Optional helpers (safe) -->
<script>
  function togglePassword() {
    const input = document.getElementById("passwordInput");
    if (!input) return;
    input.type = input.type === "password" ? "text" : "password";
  }

  function showRecoverForm() {
    const form = document.querySelector('form');
    const recoverForm = document.getElementById('recoverForm');
    if (form) form.style.display = 'none';
    if (recoverForm) recoverForm.style.display = 'block';
  }
</script>

