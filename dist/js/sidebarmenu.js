/* Side navigation behavior tuned for enterprise workflow */

$(function () {
  'use strict';

  const $body = $('body');
  const $main = $('#main-wrapper');
  const $sidebarNav = $('#sidebarnav');
  const $scrollWrap = $('.scroll-sidebar');
  const $desktopToggler = $('.sidebartoggler');
  const $mobileToggler = $('.nav-toggler');
  const STORAGE_KEY = 'city_enro.sidebar.collapsed';

  if (!$sidebarNav.length) return;

  function normalizePath(input) {
    let raw = String(input || '').trim();
    if (!raw || raw === '#' || raw.indexOf('javascript:') === 0) return '';

    try {
      raw = new URL(raw, window.location.origin).pathname;
    } catch (e) {
      raw = raw.split('?')[0].split('#')[0];
    }

    raw = raw.replace(/\\/g, '/').replace(/\/+$/, '');
    return raw || '/';
  }

  function currentPath() {
    return normalizePath(window.location.pathname);
  }

  function findBestMatchLink() {
    const cur = currentPath();
    let best = null;
    let bestLen = -1;

    $sidebarNav.find('a[href]').each(function () {
      const href = $(this).attr('href');
      const path = normalizePath(href);
      if (!path) return;

      const isExact = cur === path;
      const isChild = cur.indexOf(path + '/') === 0;

      if ((isExact || isChild) && path.length > bestLen) {
        best = this;
        bestLen = path.length;
      }
    });

    return best;
  }

  function setActiveLink() {
    $sidebarNav.find('a.active').removeClass('active').removeAttr('aria-current');
    $sidebarNav.find('li.active').removeClass('active');

    const best = findBestMatchLink();
    if (!best) return;

    const $best = $(best);
    $best.addClass('active').attr('aria-current', 'page');

    $best.parents('li').addClass('active');
    $best.parents('ul').show();

    if ($scrollWrap.length) {
      const top = $best.offset().top - $scrollWrap.offset().top + $scrollWrap.scrollTop();
      if (top > 220) {
        $scrollWrap.stop(true).animate({ scrollTop: top - 120 }, 220);
      }
    }
  }

  function bindSubMenus() {
    $sidebarNav.find('li > a').on('click', function (e) {
      const $link = $(this);
      const $sub = $link.next('ul');
      if (!$sub.length) return;

      e.preventDefault();
      const $parent = $link.parent('li');
      const open = $sub.is(':visible');

      if (open) {
        $sub.stop(true).slideUp(180);
        $parent.removeClass('active');
        return;
      }

      $sidebarNav.find('li > ul:visible').stop(true).slideUp(180);
      $sidebarNav.find('li.active').removeClass('active');

      $sub.stop(true).slideDown(180);
      $parent.addClass('active');
    });
  }

  function persistCollapsedState() {
    const collapsed = $body.hasClass('mini-sidebar') || $main.attr('data-sidebartype') === 'mini-sidebar';
    localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
  }

  function applyStoredCollapsedState() {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored === null) return;

    if (stored === '1') {
      $body.addClass('mini-sidebar');
      $main.attr('data-sidebartype', 'mini-sidebar');
    } else {
      $body.removeClass('mini-sidebar');
      if ($main.attr('data-sidebartype') === 'mini-sidebar') {
        $main.attr('data-sidebartype', 'full');
      }
    }
  }

  function bindTogglerStateSync() {
    $desktopToggler.on('click', function () {
      setTimeout(persistCollapsedState, 0);
    });

    $mobileToggler.on('click', function () {
      setTimeout(function () {
        $main.toggleClass('show-sidebar');
      }, 0);
    });
  }

  applyStoredCollapsedState();
  bindSubMenus();
  bindTogglerStateSync();
  setActiveLink();

  $(window).on('popstate hashchange', function () {
    setActiveLink();
  });
});
