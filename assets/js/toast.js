/**
 * Toast notification system
 */
const AppToast = (function () {
  'use strict';

  let container = null;

  function getContainer() {
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      container.setAttribute('role', 'region');
      container.setAttribute('aria-label', 'Notifications');
      document.body.appendChild(container);
    }
    return container;
  }

  const icons = {
    success: 'bi-check-circle-fill',
    error: 'bi-x-circle-fill',
    warning: 'bi-exclamation-triangle-fill',
    info: 'bi-info-circle-fill',
  };

  function show(type, title, message, duration) {
    duration = duration || 4500;
    const el = document.createElement('div');
    el.className = 'toast toast--' + type;
    el.setAttribute('role', 'alert');
    el.innerHTML =
      '<i class="bi ' + (icons[type] || icons.info) + ' toast__icon" aria-hidden="true"></i>' +
      '<div class="toast__content">' +
        '<div class="toast__title">' + escapeHtml(title) + '</div>' +
        (message ? '<div class="toast__message">' + escapeHtml(message) + '</div>' : '') +
      '</div>' +
      '<button type="button" class="toast__close" aria-label="ปิด"><i class="bi bi-x"></i></button>';

    const closeBtn = el.querySelector('.toast__close');
    closeBtn.addEventListener('click', function () { removeToast(el); });

    getContainer().appendChild(el);

    if (duration > 0) {
      setTimeout(function () { removeToast(el); }, duration);
    }

    return el;
  }

  function removeToast(el) {
    if (!el || !el.parentNode) return;
    el.style.opacity = '0';
    el.style.transform = 'translateX(16px)';
    el.style.transition = 'opacity 0.2s, transform 0.2s';
    setTimeout(function () { el.remove(); }, 200);
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  return {
    success: function (title, msg, dur) { return show('success', title, msg, dur); },
    error: function (title, msg, dur) { return show('error', title, msg, dur); },
    warning: function (title, msg, dur) { return show('warning', title, msg, dur); },
    info: function (title, msg, dur) { return show('info', title, msg, dur); },
  };
})();

window.AppToast = AppToast;
