/**
 * Return approval confirm handler
 */
window.initReturnApproval = function () {
  'use strict';
  document.querySelectorAll('form[data-confirm-return]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      AppConfirm({
        title: 'ยืนยันการคืนครุภัณฑ์?',
        message: 'ระบบจะบันทึกการคืนและเพิ่มสต๊อกคืน',
        confirmText: 'ยืนยัน',
      }).then(function (r) {
        if (r.isConfirmed) form.submit();
      });
    });
  });
};

window.confirmReturn = function (form) {
  if (typeof event !== 'undefined') event.preventDefault();
  AppConfirm({
    title: 'ยืนยันการคืนครุภัณฑ์?',
    message: 'ระบบจะบันทึกการคืนและเพิ่มสต๊อกคืน',
    confirmText: 'ยืนยัน',
  }).then(function (r) {
    if (r.isConfirmed) form.submit();
  });
  return false;
};
