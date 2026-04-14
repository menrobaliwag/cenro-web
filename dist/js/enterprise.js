(function () {
  'use strict';

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function ensureToastContainer() {
    var el = document.getElementById('app-toast-container');
    if (el) return el;
    el = document.createElement('div');
    el.id = 'app-toast-container';
    el.className = 'toast-container position-fixed top-0 end-0 p-3';
    el.style.zIndex = '1080';
    document.body.appendChild(el);
    return el;
  }

  function normalizeVariant(variant) {
    var allowed = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];
    if (!variant || allowed.indexOf(variant) === -1) return 'primary';
    return variant;
  }

  function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function showToast(opts) {
    opts = opts || {};
    var title = opts.title || 'Notification';
    var message = opts.message || '';
    var variant = normalizeVariant(opts.variant || 'primary');
    var delay = Number.isFinite(opts.delay) ? opts.delay : 4500;

    var container = ensureToastContainer();
    var toastEl = document.createElement('div');
    toastEl.className = 'toast align-items-center text-bg-' + variant + ' border-0';
    toastEl.setAttribute('role', 'status');
    toastEl.setAttribute('aria-live', 'polite');
    toastEl.setAttribute('aria-atomic', 'true');
    toastEl.innerHTML =
      '<div class="d-flex">' +
      '  <div class="toast-body">' +
      '    <div class="fw-semibold mb-1">' + escapeHtml(title) + '</div>' +
      '    <div class="small opacity-90">' + escapeHtml(message) + '</div>' +
      '  </div>' +
      '  <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
      '</div>';

    container.appendChild(toastEl);

    if (!window.bootstrap || !window.bootstrap.Toast) return;
    var toast = window.bootstrap.Toast.getOrCreateInstance(toastEl, { delay: delay });
    toast.show();
    toastEl.addEventListener('hidden.bs.toast', function () {
      toastEl.remove();
    });
  }

  function renderTimeline(items) {
    if (!Array.isArray(items) || items.length === 0) {
      return '<div class="text-muted small">No activity yet.</div>';
    }

    var html = '<div class="activity-timeline">';
    items.forEach(function (it) {
      var action = it.action || 'ACTIVITY';
      var desc = it.description || '';
      var at = it.created_at || '';
      var by = it.user_name || '';
      html +=
        '<div class="activity-item">' +
        '  <div class="activity-dot"></div>' +
        '  <div class="activity-content">' +
        '    <div class="d-flex justify-content-between gap-2 flex-wrap">' +
        '      <div class="fw-semibold">' + escapeHtml(action) + '</div>' +
        '      <div class="activity-meta">' + escapeHtml(at) + '</div>' +
        '    </div>' +
        (desc ? '<div class="mt-1 small">' + escapeHtml(desc) + '</div>' : '') +
        (by ? '<div class="activity-meta mt-1">By ' + escapeHtml(by) + '</div>' : '') +
        '  </div>' +
        '</div>';
    });
    html += '</div>';
    return html;
  }

  async function fetchJson(url) {
    var csrf = getCsrfToken();
    var res = await fetch(url, {
      method: 'GET',
      headers: csrf ? { 'X-CSRF-Token': csrf } : {},
      credentials: 'same-origin'
    });
    var text = await res.text();
    var data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      throw new Error('Invalid server response.');
    }
    if (!res.ok) {
      var msg = data && data.message ? data.message : 'Request failed.';
      throw new Error(msg);
    }
    return data;
  }

  function bindActivityTimelineModal() {
    var modalEl = document.getElementById('activityTimelineModal');
    if (!modalEl) return;

    modalEl.addEventListener('show.bs.modal', function (event) {
      var trigger = event.relatedTarget;
      if (!trigger) return;

      var entityType = trigger.getAttribute('data-entity-type') || '';
      var entityId = trigger.getAttribute('data-entity-id') || '';
      var title = trigger.getAttribute('data-activity-title') || 'Activity';

      var titleEl = modalEl.querySelector('.js-activity-title');
      if (titleEl) titleEl.textContent = title;

      var bodyEl = modalEl.querySelector('.js-activity-body');
      if (!bodyEl) return;

      bodyEl.innerHTML = '<div class="skeleton skeleton-line mb-2"></div>'.repeat(6);

      var url =
        (window.BASE_URL || '') + '/modules/audit_logs/api.php?entity_type=' +
        encodeURIComponent(entityType) +
        '&entity_id=' +
        encodeURIComponent(entityId);

      fetchJson(url)
        .then(function (payload) {
          bodyEl.innerHTML = renderTimeline(payload.items || []);
        })
        .catch(function (err) {
          bodyEl.innerHTML = '<div class="text-danger small">' + escapeHtml(err.message || 'Failed to load activity.') + '</div>';
          showToast({ title: 'Activity', message: err.message || 'Failed to load activity.', variant: 'danger' });
        });
    });
  }

  window.AppToast = { show: showToast };

  document.addEventListener('DOMContentLoaded', function () {
    bindActivityTimelineModal();

    if (window.__FLASH_TOAST__ && (window.__FLASH_TOAST__.message || window.__FLASH_TOAST__.title)) {
      showToast(window.__FLASH_TOAST__);
      window.__FLASH_TOAST__ = null;
    }
  });
})();
