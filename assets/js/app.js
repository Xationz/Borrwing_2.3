/**
 * SPA Application Shell
 */
(function () {
  'use strict';

  var configEl = document.getElementById('app-config');
  if (!configEl) return;

  var ROLE = configEl.dataset.role;
  var USERNAME = configEl.dataset.username || '';
  var INITIAL_PAGE = configEl.dataset.page || 'dashboard';
  var loading = document.getElementById('app-loading');
  var sidebar = document.getElementById('app-sidebar');
  var overlay = document.getElementById('sidebar-overlay');

  function showLoading() { if (loading) loading.classList.add('show'); }
  function hideLoading() { if (loading) loading.classList.remove('show'); }

  function setActiveLink(page) {
    document.querySelectorAll('.spa-link').forEach(function (el) {
      el.classList.toggle('active', el.dataset.page === page);
    });
  }

  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('show');
  }

  function openSidebar() {
    if (sidebar) sidebar.classList.add('open');
    if (overlay) overlay.classList.add('show');
  }

  function runPageScripts() {
    document.querySelectorAll('#main-content script[data-page-init]').forEach(function (oldScript) {
      var s = document.createElement('script');
      s.textContent = oldScript.textContent;
      document.body.appendChild(s);
      document.body.removeChild(s);
    });

    if (typeof window.initBorrowWizard === 'function') window.initBorrowWizard();
    if (typeof window.initDashboardCharts === 'function') window.initDashboardCharts();
    if (typeof window.initReturnApproval === 'function') window.initReturnApproval();
    if (typeof initGlobalSearch === 'function') initGlobalSearch('global-search');
    if (typeof initFilterTabs === 'function') {
      initFilterTabs('.filter-tabs', '.filter-row', 'data-status');
    }
  }

  function loadPage(page, pushState) {
    showLoading();
    setActiveLink(page);
    closeSidebar();

    document.querySelectorAll('.modal.show').forEach(function (m) {
      var inst = bootstrap.Modal.getInstance(m);
      if (inst) inst.hide();
    });

    fetch('partials/partial_' + page + '.php', {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(function (r) {
        if (r.status === 403 || r.status === 401) {
          window.location.href = 'login.php';
          throw new Error('Unauthorized');
        }
        return r.text();
      })
      .then(function (html) {
        hideLoading();
        var container = document.getElementById('main-content');
        container.innerHTML = html;
        container.scrollTop = 0;
        window.scrollTo(0, 0);
        runPageScripts();
        if (pushState !== false) {
          history.pushState({ page: page }, '', '?page=' + page);
        }
        document.title = (document.querySelector('.page-header__title') || {}).textContent
          ? document.querySelector('.page-header__title').textContent.trim() + ' — ระบบยืมครุภัณฑ์'
          : 'ระบบยืมครุภัณฑ์';
      })
      .catch(function (err) {
        hideLoading();
        if (err.message !== 'Unauthorized') {
          if (window.AppToast) AppToast.error('ข้อผิดพลาด', 'ไม่สามารถโหลดหน้าได้ กรุณาลองใหม่');
          else Swal.fire('ข้อผิดพลาด', 'ไม่สามารถโหลดหน้าได้ กรุณาลองใหม่', 'error');
        }
      });
  }

  document.querySelectorAll('.spa-link').forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      var page = this.dataset.page;
      if (page) loadPage(page, true);
    });
  });

  window.addEventListener('popstate', function (e) {
    var page = (e.state && e.state.page) ? e.state.page : INITIAL_PAGE;
    loadPage(page, false);
  });

  window.spaNavigate = function (page) { loadPage(page, true); };

  var toggleBtn = document.getElementById('sidebar-toggle');
  if (toggleBtn) toggleBtn.addEventListener('click', openSidebar);
  if (overlay) overlay.addEventListener('click', closeSidebar);

  history.replaceState({ page: INITIAL_PAGE }, '', '?page=' + INITIAL_PAGE);
})();
