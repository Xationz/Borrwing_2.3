/**
 * Confirm modal helper (uses SweetAlert2 with design system colors)
 */
window.AppConfirm = function (options) {
  'use strict';
  return Swal.fire({
    title: options.title || 'ยืนยัน',
    html: options.message || '',
    icon: options.icon || 'question',
    showCancelButton: true,
    confirmButtonColor: '#10B981',
    cancelButtonColor: '#6B7280',
    confirmButtonText: options.confirmText || 'ยืนยัน',
    cancelButtonText: options.cancelText || 'ยกเลิก',
    reverseButtons: true,
    focusCancel: true,
  });
};

/**
 * Global search filter for tables and cards
 */
window.initGlobalSearch = function (inputId) {
  'use strict';
  var input = document.getElementById(inputId);
  if (!input) return;

  input.addEventListener('input', function () {
    var q = this.value.toLowerCase().trim();
    document.querySelectorAll('[data-searchable]').forEach(function (row) {
      var text = (row.getAttribute('data-searchable') || row.textContent || '').toLowerCase();
      row.style.display = !q || text.indexOf(q) !== -1 ? '' : 'none';
    });
  });
};

/**
 * Filter tabs helper
 */
window.initFilterTabs = function (containerSelector, rowSelector, attr) {
  'use strict';
  attr = attr || 'data-status';
  var container = document.querySelector(containerSelector);
  if (!container) return;

  container.querySelectorAll('.filter-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
      container.querySelectorAll('.filter-tab').forEach(function (t) { t.classList.remove('active'); });
      tab.classList.add('active');
      var filter = tab.getAttribute('data-filter');
      document.querySelectorAll(rowSelector).forEach(function (row) {
        if (filter === 'all' || row.getAttribute(attr) === filter) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });
  });
};

/**
 * Return borrowing confirmation
 */
window.initReturnActions = function (pageName) {
  'use strict';
  document.querySelectorAll('.spa-action-return').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var id = this.getAttribute('data-id');
      AppConfirm({
        title: 'แจ้งคืนครุภัณฑ์?',
        message: 'รายการจะเปลี่ยนเป็น <strong>รอเจ้าหน้าที่ตรวจสอบ</strong><br>จะถือว่าคืนสำเร็จเมื่อเจ้าหน้าที่ยืนยัน',
        icon: 'question',
        confirmText: 'แจ้งคืน',
      }).then(function (r) {
        if (r.isConfirmed) {
          window.location.href = 'spa_shell.php?page=' + pageName + '&return_borrowing=' + id;
        }
      });
    });
  });
};

/**
 * Status badge renderer (for dynamic use)
 */
window.statusBadgeHtml = function (status) {
  'use strict';
  var map = {
    borrowing: { cls: 'status-badge--borrowing', icon: 'bi-clock', label: 'กำลังยืม' },
    waiting_return_approval: { cls: 'status-badge--waiting', icon: 'bi-hourglass-split', label: 'รอตรวจสอบ' },
    returned: { cls: 'status-badge--returned', icon: 'bi-check-circle', label: 'คืนสำเร็จ' },
  };
  if (status === 'borrowed' || status === 'pending') status = 'borrowing';
  var s = map[status] || map.borrowing;
  return '<span class="status-badge ' + s.cls + '"><i class="bi ' + s.icon + '"></i> ' + s.label + '</span>';
};
