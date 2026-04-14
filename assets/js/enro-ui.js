(function (window, document) {
  'use strict';

  var ENRO_UI = window.ENRO_UI || {};
  var GROUP_STATE_KEY = 'enro.sidebar.group.';
  var SIDEBAR_COLLAPSE_KEY = 'enro.sidebar.collapsed';
  var LEGACY_SIDEBAR_COLLAPSE_KEY = 'city_enro.sidebar.collapsed';

  try {
    localStorage.setItem(LEGACY_SIDEBAR_COLLAPSE_KEY, '0');
  } catch (err) {
    // Ignore storage failures (private mode / disabled storage).
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function stripHtml(value) {
    var temp = document.createElement('div');
    temp.innerHTML = String(value || '');
    return (temp.textContent || temp.innerText || '').trim();
  }

  function normalize(value) {
    return String(value || '')
      .toLowerCase()
      .replace(/\s+/g, ' ')
      .trim();
  }

  function firstValidIndex(indices) {
    for (var i = 0; i < indices.length; i += 1) {
      if (indices[i] >= 0) return indices[i];
    }
    return -1;
  }

  function findHeaderIndex(headers, keywords) {
    var i;
    for (i = 0; i < headers.length; i += 1) {
      var text = normalize(headers[i]);
      for (var k = 0; k < keywords.length; k += 1) {
        if (text.indexOf(keywords[k]) !== -1) return i;
      }
    }
    return -1;
  }

  function uniqueSorted(indices) {
    var seen = {};
    var out = [];
    indices.forEach(function (idx) {
      if (typeof idx === 'number' && idx >= 0 && !seen[idx]) {
        seen[idx] = true;
        out.push(idx);
      }
    });
    return out.sort(function (a, b) { return a - b; });
  }

  function classifyDetailGroup(label) {
    var key = normalize(label);
    if (/pet|sachet|plastic|galon|tarpaulin|styro|sando|panligo|soft/.test(key)) return 'Plastics';
    if (/paper|papel|carton|cardboard/.test(key)) return 'Paper';
    if (/glass|bote|bottle|longneck|mantika|litro/.test(key)) return 'Glass';
    return 'Others';
  }

  function ensureDetailPanel() {
    var panel = document.getElementById('enroRecordDetailModal');
    if (panel) return panel;

    var wrapper = document.createElement('div');
    wrapper.innerHTML = [
      '<div class="modal fade" id="enroRecordDetailModal" tabindex="-1" aria-labelledby="enroRecordDetailModalLabel" aria-hidden="true">',
      '  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">',
      '    <div class="modal-content enro-record-modal">',
      '      <div class="modal-header">',
      '        <div>',
      '          <h5 class="modal-title mb-0" id="enroRecordDetailModalLabel">Record Details</h5>',
      '          <small class="opacity-75">City ENRO Operational Viewer</small>',
      '        </div>',
      '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>',
      '      </div>',
      '      <div class="modal-body">',
      '        <div class="enro-record-summary">',
      '          <div class="enro-record-summary-label">Total</div>',
      '          <div class="enro-record-summary-value" id="enroRecordTotal">-</div>',
      '        </div>',
      '        <div class="enro-record-meta" id="enroRecordMeta"></div>',
      '        <div id="enroRecordSections"></div>',
      '      </div>',
      '    </div>',
      '  </div>',
      '</div>'
    ].join('');

    document.body.appendChild(wrapper.firstChild);
    return document.getElementById('enroRecordDetailModal');
  }

  function buildMetaHtml(metaPairs) {
    return metaPairs.map(function (pair) {
      return '<div class="enro-record-meta-row"><div class="enro-record-label">' + escapeHtml(pair.label) + '</div><div class="enro-record-value">' + escapeHtml(pair.value || '-') + '</div></div>';
    }).join('');
  }

  function buildSectionHtml(title, rows) {
    if (!rows.length) return '';
    var gridRows = rows.map(function (row) {
      return '<div class="enro-record-label">' + escapeHtml(row.label) + '</div><div class="enro-record-value">' + escapeHtml(row.value || '-') + '</div>';
    }).join('');
    return '<section class="enro-record-section"><h6 class="enro-record-section-title">' + escapeHtml(title) + '</h6><div class="enro-record-grid">' + gridRows + '</div></section>';
  }

  function extractRowData(dt, rowEl) {
    var rowData = dt.row(rowEl).data();
    if (Array.isArray(rowData)) return rowData;

    if (rowData && typeof rowData === 'object') {
      return Object.keys(rowData).map(function (key) { return rowData[key]; });
    }

    var cells = rowEl ? rowEl.querySelectorAll('td,th') : [];
    return Array.prototype.map.call(cells, function (cell) {
      return cell.innerHTML || cell.textContent || '';
    });
  }

  function addViewActionItems(tableEl) {
    var menus = tableEl.querySelectorAll('tbody tr td.enro-col-action .dropdown-menu');
    menus.forEach(function (menu) {
      if (menu.querySelector('[data-enro-view]')) return;
      var li = document.createElement('li');
      li.innerHTML = '<button type="button" class="dropdown-item d-flex align-items-center gap-2" data-enro-view="1"><i class="fa fa-eye"></i><span>View</span></button>';
      menu.insertBefore(li, menu.firstChild);
      var divider = menu.querySelector('hr.dropdown-divider');
      if (!divider) {
        var hrLi = document.createElement('li');
        hrLi.innerHTML = '<hr class="dropdown-divider my-1">';
        menu.insertBefore(hrLi, li.nextSibling);
      }
    });
  }

  function addQuickViewButtons(tableEl, actionVisibleIndex) {
    if (actionVisibleIndex < 0) return;

    tableEl.querySelectorAll('tbody tr').forEach(function (row) {
      var actionCell = row.children[actionVisibleIndex];
      if (!actionCell) return;
      if (actionCell.querySelector('.enro-quick-view-btn')) return;

      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-sm btn-outline-primary enro-quick-view-btn me-1';
      btn.setAttribute('data-enro-view-row', '1');
      btn.setAttribute('aria-label', 'View details');
      btn.innerHTML = '<i class="fa fa-eye"></i>';
      actionCell.insertBefore(btn, actionCell.firstChild);
    });
  }

  function applyStickyActionClass(tableEl, actionVisibleIndex) {
    if (actionVisibleIndex < 0) return;

    var headCells = tableEl.querySelectorAll('thead th');
    headCells.forEach(function (cell) {
      cell.classList.remove('enro-col-action');
    });
    if (headCells[actionVisibleIndex]) {
      headCells[actionVisibleIndex].classList.add('enro-col-action');
    }

    tableEl.querySelectorAll('tbody tr').forEach(function (row) {
      Array.prototype.forEach.call(row.children, function (cell) {
        cell.classList.remove('enro-col-action');
      });
      if (row.children[actionVisibleIndex]) {
        row.children[actionVisibleIndex].classList.add('enro-col-action');
      }
    });
  }

  function showRecordDetail(meta, rowData) {
    var panel = ensureDetailPanel();
    var totalEl = panel.querySelector('#enroRecordTotal');
    var metaEl = panel.querySelector('#enroRecordMeta');
    var sectionsEl = panel.querySelector('#enroRecordSections');

    function valueAt(index) {
      if (index < 0 || index >= rowData.length) return '';
      return stripHtml(rowData[index]);
    }

    var totalValue = valueAt(meta.totalIndex);
    if (!totalValue) totalValue = '-';

    totalEl.textContent = totalValue;

    var metaPairs = [
      { label: 'Date', value: valueAt(meta.dateIndex) || '-' },
      { label: 'Name', value: valueAt(meta.nameIndex) || '-' },
      { label: 'Barangay / Address', value: valueAt(meta.addressIndex) || '-' },
      { label: 'Status', value: valueAt(meta.statusIndex) || 'Active' }
    ];
    metaEl.innerHTML = buildMetaHtml(metaPairs);

    var groups = {
      Plastics: [],
      Paper: [],
      Glass: [],
      Others: []
    };

    meta.detailIndices.forEach(function (colIndex) {
      if (colIndex === meta.actionIndex) return;
      var label = meta.headers[colIndex] || ('Column ' + (colIndex + 1));
      var value = valueAt(colIndex);
      if (!label || value === '') return;
      groups[classifyDetailGroup(label)].push({ label: label, value: value });
    });

    sectionsEl.innerHTML = '';
    sectionsEl.innerHTML += buildSectionHtml('Plastics', groups.Plastics);
    sectionsEl.innerHTML += buildSectionHtml('Paper', groups.Paper);
    sectionsEl.innerHTML += buildSectionHtml('Glass', groups.Glass);
    sectionsEl.innerHTML += buildSectionHtml('Others', groups.Others);

    if (window.bootstrap && window.bootstrap.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(panel).show();
    }
  }

  ENRO_UI.getMasterDetailConfig = function (tableEl) {
    if (!tableEl) {
      return { enabled: false, columnDefs: [], headers: [], detailIndices: [], visibleIndices: [], actionIndex: -1 };
    }

    var headers = Array.prototype.map.call(tableEl.querySelectorAll('thead th'), function (th) {
      return (th.textContent || '').replace(/\s+/g, ' ').trim();
    });

    var colCount = headers.length;
    var actionIndex = findHeaderIndex(headers, ['action', 'actions', 'restore', 'options']);
    var indexIndex = firstValidIndex([
      findHeaderIndex(headers, ['#', 'no.']),
      findHeaderIndex(headers, ['id'])
    ]);
    var dateIndex = findHeaderIndex(headers, ['date']);
    var nameIndex = findHeaderIndex(headers, ['name']);
    var addressIndex = firstValidIndex([
      findHeaderIndex(headers, ['barangay']),
      findHeaderIndex(headers, ['address']),
      findHeaderIndex(headers, ['location'])
    ]);
    var totalIndex = firstValidIndex([
      findHeaderIndex(headers, ['total items']),
      findHeaderIndex(headers, ['overall total']),
      findHeaderIndex(headers, ['total']),
      findHeaderIndex(headers, ['amount'])
    ]);
    var statusIndex = findHeaderIndex(headers, ['status']);

    var visibleCandidates = uniqueSorted([
      indexIndex,
      dateIndex,
      nameIndex,
      addressIndex,
      totalIndex,
      statusIndex,
      actionIndex
    ]);

    if (visibleCandidates.length === 0) {
      visibleCandidates = headers.map(function (_h, idx) { return idx; }).slice(0, Math.min(8, colCount));
    }

    if (actionIndex >= 0 && visibleCandidates.indexOf(actionIndex) === -1) {
      visibleCandidates.push(actionIndex);
      visibleCandidates = uniqueSorted(visibleCandidates);
    }

    while (visibleCandidates.length > 8) {
      var removable = visibleCandidates.find(function (idx) {
        return idx !== actionIndex && idx !== dateIndex && idx !== nameIndex;
      });
      if (typeof removable === 'number') {
        visibleCandidates = visibleCandidates.filter(function (idx) { return idx !== removable; });
      } else {
        visibleCandidates = visibleCandidates.slice(0, 8);
        break;
      }
    }

    var detailIndices = headers
      .map(function (_h, idx) { return idx; })
      .filter(function (idx) { return visibleCandidates.indexOf(idx) === -1; });

    var isWide = colCount > 8 && detailIndices.length > 0;

    var columnDefs = [];
    if (isWide) {
      detailIndices.forEach(function (idx) {
        columnDefs.push({ targets: idx, visible: false });
      });
    }

    if (actionIndex >= 0) {
      columnDefs.push({ targets: actionIndex, orderable: false, className: 'enro-col-action' });
    }

    return {
      enabled: isWide,
      headers: headers,
      columnDefs: columnDefs,
      detailIndices: detailIndices,
      visibleIndices: visibleCandidates,
      actionIndex: actionIndex,
      dateIndex: dateIndex,
      nameIndex: nameIndex,
      addressIndex: addressIndex,
      totalIndex: totalIndex,
      statusIndex: statusIndex
    };
  };

  ENRO_UI.afterDataTableInit = function (tableEl, dt, config) {
    if (!tableEl || !dt || !config) return;

    var actionVisibleIndex = config.visibleIndices.indexOf(config.actionIndex);

    function redrawEnhancements() {
      if (config.enabled) tableEl.classList.add('enro-master-table');
      if (actionVisibleIndex >= 0) {
        applyStickyActionClass(tableEl, actionVisibleIndex);
      }
      if (config.enabled) {
        addViewActionItems(tableEl);
        addQuickViewButtons(tableEl, actionVisibleIndex);
      }
    }

    redrawEnhancements();
    dt.on('draw.enroUi', redrawEnhancements);

    if (!config.enabled) return;

    tableEl.addEventListener('click', function (event) {
      var quickView = event.target.closest('[data-enro-view-row]');
      if (quickView) {
        event.preventDefault();
        event.stopPropagation();
        var quickRow = quickView.closest('tr');
        if (!quickRow) return;
        showRecordDetail(config, extractRowData(dt, quickRow));
        return;
      }

      var viewAction = event.target.closest('[data-enro-view]');
      if (viewAction) {
        event.preventDefault();
        event.stopPropagation();
        var viewRow = viewAction.closest('tr');
        if (!viewRow) return;
        showRecordDetail(config, extractRowData(dt, viewRow));
      }
    });
  };

  function initSidebarActiveState() {
    var currentPath = window.location.pathname.replace(/\/+$/, '');
    document.querySelectorAll('#sidebarnav .sidebar-link[href]').forEach(function (link) {
      var linkPath;
      try {
        linkPath = new URL(link.getAttribute('href'), window.location.origin).pathname.replace(/\/+$/, '');
      } catch (err) {
        return;
      }
      if (linkPath === currentPath) {
        link.classList.add('active');
        var parent = link.closest('.sidebar-item');
        if (parent) parent.classList.add('selected');
      }
    });
  }

  function initSidebarGroups() {
    var nav = document.getElementById('sidebarnav');
    if (!nav) return;

    var caps = Array.prototype.filter.call(nav.children, function (li) {
      return li.classList.contains('nav-small-cap');
    });

    caps.forEach(function (cap, index) {
      var groupId = 'group-' + index;
      var originalLabel = (cap.textContent || '').replace(/\s+/g, ' ').trim() || ('Section ' + (index + 1));
      var iconHtml = '<i class="mdi mdi-folder-outline"></i>';
      cap.classList.add('enro-group-cap');
      cap.setAttribute('data-group', groupId);
      cap.innerHTML = '<button type="button" class="enro-group-toggle" data-group-toggle="' + groupId + '"><span class="enro-group-label">' + iconHtml + '<span>' + escapeHtml(originalLabel) + '</span></span><i class="mdi mdi-chevron-down enro-group-caret"></i></button>';

      var members = [];
      var pointer = cap.nextElementSibling;
      while (pointer && !pointer.classList.contains('nav-small-cap')) {
        if (pointer.classList.contains('sidebar-item')) {
          pointer.classList.add('enro-group-item');
          pointer.setAttribute('data-group', groupId);
          members.push(pointer);
        }
        pointer = pointer.nextElementSibling;
      }

      var hasActive = members.some(function (item) {
        return item.querySelector('.sidebar-link.active');
      });

      var stored = localStorage.getItem(GROUP_STATE_KEY + groupId);
      var expanded = stored === null ? (hasActive || index === 0) : stored === '1';

      function applyState(isExpanded) {
        cap.classList.toggle('is-collapsed', !isExpanded);
        members.forEach(function (member) {
          member.classList.toggle('is-hidden', !isExpanded);
        });
      }

      applyState(expanded);

      var toggle = cap.querySelector('[data-group-toggle]');
      if (toggle) {
        toggle.addEventListener('click', function () {
          expanded = !expanded;
          localStorage.setItem(GROUP_STATE_KEY + groupId, expanded ? '1' : '0');
          applyState(expanded);
        });
      }
    });
  }

  function initPageShell() {
    var breadcrumbs = document.querySelectorAll('.page-breadcrumb');
    breadcrumbs.forEach(function (crumb) {
      crumb.classList.add('enro-page-shell');
    });
  }

  function isDesktopViewport() {
    return window.matchMedia('(min-width: 992px)').matches;
  }

  function getMainWrapper() {
    return document.getElementById('main-wrapper');
  }

  function clearLegacySidebarState() {
    var wrapper = getMainWrapper();
    document.body.classList.remove('mini-sidebar');
    document.body.classList.remove('enro-sidebar-collapsed');
    if (!wrapper) return;
    wrapper.classList.remove('mini-sidebar');
    wrapper.setAttribute('data-sidebartype', 'full');
  }

  function syncSidebarTooltips() {
    var collapsed = isDesktopViewport() && document.body.classList.contains('sidebar-collapsed');
    document.querySelectorAll('#sidebarnav .sidebar-link').forEach(function (link) {
      var label = link.querySelector('.hide-menu');
      var text = (label ? label.textContent : link.textContent || '').replace(/\s+/g, ' ').trim();
      if (collapsed && text) {
        link.setAttribute('title', text);
      } else {
        link.removeAttribute('title');
      }
    });
  }

  function setMobileSidebarOpen(open) {
    var wrapper = getMainWrapper();
    var isOpen = !!open && !isDesktopViewport();

    // Force mobile mode to full drawer; neutralize legacy mini-sidebar state.
    document.body.classList.remove('mini-sidebar');
    document.body.classList.remove('sidebar-collapsed');
    document.body.classList.remove('enro-sidebar-collapsed');
    document.body.classList.toggle('sidebar-mobile-open', isOpen);
    if (wrapper) {
      wrapper.classList.remove('mini-sidebar');
      wrapper.classList.toggle('show-sidebar', isOpen);
      wrapper.setAttribute('data-sidebartype', 'full');
    }

    document.querySelectorAll('.nav-toggler i').forEach(function (icon) {
      icon.classList.remove('ti-menu');
      icon.classList.remove('ti-close');
      icon.classList.add(isOpen ? 'ti-close' : 'ti-menu');
    });

    removeMobileSidebarRails();
  }

  function enforceMobileSidebarState() {
    if (isDesktopViewport()) return;
    var wrapper = getMainWrapper();

    document.body.classList.remove('mini-sidebar');
    document.body.classList.remove('sidebar-collapsed');
    document.body.classList.remove('enro-sidebar-collapsed');

    if (!wrapper) return;
    wrapper.classList.remove('mini-sidebar');
    wrapper.setAttribute('data-sidebartype', 'full');
    removeMobileSidebarRails();
  }

  function removeMobileSidebarRails() {
    if (isDesktopViewport()) return;
    document.querySelectorAll('.left-sidebar .ps__rail-x, .left-sidebar .ps__rail-y, .left-sidebar .ps__thumb-x, .left-sidebar .ps__thumb-y').forEach(function (node) {
      if (node && node.parentNode) {
        node.parentNode.removeChild(node);
      }
    });
  }

  function watchLegacySidebarMutations() {
    var wrapper = getMainWrapper();
    if (!wrapper || wrapper.dataset.enroSidebarObserved === '1') return;

    wrapper.dataset.enroSidebarObserved = '1';

    var observer = new MutationObserver(function () {
      if (isDesktopViewport()) return;
      var sidebarType = String(wrapper.getAttribute('data-sidebartype') || '').toLowerCase();
      if (wrapper.classList.contains('mini-sidebar') || sidebarType === 'mini-sidebar' || sidebarType === 'iconbar' || sidebarType === 'overlay') {
        enforceMobileSidebarState();
      }
    });

    observer.observe(wrapper, {
      attributes: true,
      attributeFilter: ['class', 'data-sidebartype']
    });
  }

  function setDesktopSidebarCollapsed(collapsed, persistState) {
    clearLegacySidebarState();
    var canCollapse = isDesktopViewport();
    var shouldCollapse = !!collapsed && canCollapse;
    document.body.classList.toggle('sidebar-collapsed', shouldCollapse);

    if (persistState) {
      localStorage.setItem(SIDEBAR_COLLAPSE_KEY, shouldCollapse ? '1' : '0');
    }

    if (!canCollapse) {
      setMobileSidebarOpen(false);
    }

    syncSidebarTooltips();
  }

  function applyInitialSidebarState() {
    var storedCollapsed = localStorage.getItem(SIDEBAR_COLLAPSE_KEY) === '1';
    if (isDesktopViewport()) {
      setDesktopSidebarCollapsed(storedCollapsed, false);
      setMobileSidebarOpen(false);
    } else {
      setDesktopSidebarCollapsed(false, false);
      setMobileSidebarOpen(false);
    }
  }

  function unbindLegacySidebarHandlers() {
    if (!window.jQuery) return;
    window.jQuery('.sidebartoggler').off('click');
    window.jQuery('.nav-toggler').off('click');
  }

  function bindSidebarInteractions() {
    document.querySelectorAll('.sidebartoggler').forEach(function (toggle) {
      if (toggle.dataset.enroSidebarBound === '1') return;
      toggle.dataset.enroSidebarBound = '1';
      toggle.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (!isDesktopViewport()) return;
        setDesktopSidebarCollapsed(!document.body.classList.contains('sidebar-collapsed'), true);
      });
    });

    document.querySelectorAll('.nav-toggler').forEach(function (toggle) {
      if (toggle.dataset.enroSidebarBound === '1') return;
      toggle.dataset.enroSidebarBound = '1';
      toggle.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (isDesktopViewport()) return;
        setMobileSidebarOpen(!document.body.classList.contains('sidebar-mobile-open'));
      });
    });

    if (document.body.dataset.enroSidebarDocBound !== '1') {
      document.body.dataset.enroSidebarDocBound = '1';

      document.addEventListener('click', function (event) {
        if (isDesktopViewport()) return;
        if (!document.body.classList.contains('sidebar-mobile-open')) return;
        if (event.target.closest('.left-sidebar.app-sidebar')) return;
        if (event.target.closest('.nav-toggler')) return;
        setMobileSidebarOpen(false);
      });

      document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        if (!document.body.classList.contains('sidebar-mobile-open')) return;
        setMobileSidebarOpen(false);
      });

      window.addEventListener('resize', function () {
        if (isDesktopViewport()) {
          var stored = localStorage.getItem(SIDEBAR_COLLAPSE_KEY) === '1';
          setDesktopSidebarCollapsed(stored, false);
          setMobileSidebarOpen(false);
        } else {
          setDesktopSidebarCollapsed(false, false);
          setMobileSidebarOpen(false);
        }
      });
    }
  }

  function initSidebarUxBehavior() {
    var wrapper = document.getElementById('main-wrapper');
    if (!wrapper) {
      return;
    }

    unbindLegacySidebarHandlers();
    applyInitialSidebarState();
    enforceMobileSidebarState();
    watchLegacySidebarMutations();
    bindSidebarInteractions();
  }

  function moveModalToBody(modalEl) {
    if (!modalEl || !(modalEl instanceof HTMLElement) || !modalEl.classList.contains('modal')) return;
    if (modalEl.parentElement === document.body) return;
    document.body.appendChild(modalEl);
  }

  function normalizeModalsToBody() {
    document.querySelectorAll('.modal').forEach(function (modalEl) {
      moveModalToBody(modalEl);
    });
  }

  function initModalOverlayBehavior() {
    if (document.body.dataset.enroModalLayerFixBound === '1') return;
    document.body.dataset.enroModalLayerFixBound = '1';

    normalizeModalsToBody();

    // Also move target modal before Bootstrap starts opening it from trigger click.
    document.addEventListener('click', function (event) {
      var trigger = event.target.closest('[data-bs-toggle="modal"]');
      if (!trigger) return;
      var selector = String(trigger.getAttribute('data-bs-target') || trigger.getAttribute('href') || '').trim();
      if (!selector || selector.charAt(0) !== '#') return;
      var modalEl = document.querySelector(selector);
      moveModalToBody(modalEl);
    }, true);

    // Keep modal out of transformed/overflow wrappers so fixed overlay covers full viewport.
    document.addEventListener('show.bs.modal', function (event) {
      moveModalToBody(event.target);
    }, true);

    // Defensive cleanup if any legacy script leaves duplicate backdrops.
    document.addEventListener('hidden.bs.modal', function () {
      var openModals = document.querySelectorAll('.modal.show').length;
      if (openModals > 0) return;
      document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
        backdrop.parentNode && backdrop.parentNode.removeChild(backdrop);
      });
    });

    // Catch modals inserted dynamically after page load.
    var observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (!(node instanceof HTMLElement)) return;
          if (node.classList && node.classList.contains('modal')) {
            moveModalToBody(node);
          }
          if (typeof node.querySelectorAll === 'function') {
            node.querySelectorAll('.modal').forEach(function (childModal) {
              moveModalToBody(childModal);
            });
          }
        });
      });
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  function init() {
    initSidebarActiveState();
    initPageShell();
    // Run after other template handlers so we can neutralize legacy sidebar behavior.
    window.setTimeout(initSidebarUxBehavior, 0);
  }

  // Bind modal overlay behavior immediately so early show() calls are handled.
  initModalOverlayBehavior();
  document.addEventListener('DOMContentLoaded', init);

  window.ENRO_UI = ENRO_UI;
})(window, document);
