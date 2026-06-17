/**
 * Borrow Wizard — 4-step form controller
 */
window.initBorrowWizard = function () {
  'use strict';

  var dataEl = document.getElementById('borrow-data');
  if (!dataEl) return;

  var DATA = {};
  try { DATA = JSON.parse(dataEl.textContent); } catch (e) { return; }

  var SERIALS_BY_EQUIP = DATA.serials || {};
  var BUSY_BY_SERIAL = DATA.busy || {};
  var CONFIRM_COLOR = '#10B981';

  var wizEquipId = 0, wizEquipName = '', wizEquipMax = 0, wizStep = 1;
  var wizCalendar = null, wizMultiMode = false, wizMultiEquips = [];
  var msActive = false, msSelected = {}, calBookings = [];
  var EQUIP_COLORS = ['#10B981', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6', '#0891B2'];

  function $(id) { return document.getElementById(id); }
  function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
  function showEl(el, show) { if (el) el.classList.toggle('d-none', !show); }

  // ── Equipment codes modal ──
  document.querySelectorAll('.btn-show-codes').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      showEquipCodes(this.dataset.id, this.dataset.name);
    });
  });

  window.showEquipCodes = function (equipId, name) {
    var serials = SERIALS_BY_EQUIP[equipId] || [];
    $('equipCodeModalTitle').textContent = name;
    var html = '<p class="form-helper mb-3">รหัสครุภัณฑ์ทั้งหมด ' + serials.length + ' รายการ</p><ul class="list-unstyled mb-0">';
    serials.forEach(function (s) {
      html += '<li class="py-2 border-bottom"><i class="bi bi-upc me-2 text-secondary"></i>' + escHtml(s.code);
      if (s.status === 'borrowed') html += ' <span class="status-badge status-badge--borrowing ms-2">กำลังยืม</span>';
      html += '</li>';
    });
    html += '</ul>';
    $('equipCodeModalBody').innerHTML = html;
    new bootstrap.Modal($('equipCodeModal')).show();
  };

  // ── Multi-select ──
  var msToggle = $('multiSelectToggleBtn');
  if (msToggle) msToggle.addEventListener('click', toggleMultiSelectMode);

  function toggleMultiSelectMode() {
    msActive = !msActive;
    document.body.classList.toggle('multi-select-active', msActive);
    var bar = $('multiSelectBar');
    if (bar) bar.classList.toggle('show', msActive);
    var label = $('multiSelectBtnLabel');
    if (label) label.textContent = msActive ? 'ยกเลิกการเลือก' : 'เลือกหลายรายการ';
    document.querySelectorAll('.equip-card--selectable').forEach(function (card) {
      if (msActive) card.addEventListener('click', onCardClick);
      else { card.classList.remove('equip-card--selected'); card.removeEventListener('click', onCardClick); }
    });
    if (!msActive) { msSelected = {}; updateMultiSelectBar(); }
  }

  function onCardClick() {
    if (!msActive) return;
    var id = this.dataset.id;
    if (this.classList.contains('equip-card--selected')) {
      this.classList.remove('equip-card--selected');
      delete msSelected[id];
    } else {
      this.classList.add('equip-card--selected');
      msSelected[id] = { id: id, name: this.dataset.name, max: this.dataset.max };
    }
    updateMultiSelectBar();
  }

  function updateMultiSelectBar() {
    var keys = Object.keys(msSelected);
    if ($('multiSelectCount')) $('multiSelectCount').textContent = keys.length;
    var chips = $('multiSelectChips');
    if (chips) {
      chips.innerHTML = keys.map(function (id) {
        return '<span class="multi-select-chip"><i class="bi bi-laptop"></i> ' + escHtml(msSelected[id].name) + '</span>';
      }).join('');
    }
    var btn = $('multiSelectBorrowBtn');
    if (btn) btn.disabled = keys.length === 0;
  }

  var msBorrowBtn = $('multiSelectBorrowBtn');
  if (msBorrowBtn) msBorrowBtn.addEventListener('click', openMultiBorrowWizard);

  document.querySelectorAll('.btn-open-wizard').forEach(function (btn) {
    btn.addEventListener('click', function (e) { e.stopPropagation(); openBorrowWizard(this); });
  });

  function resetWizardForm() {
    $('borrowWizardForm').reset();
    $('wiz_equipment_id').value = '';
    $('wiz_it_install').value = '';
    if ($('wiz_it_install_select')) $('wiz_it_install_select').value = '';
    $('wiz_serial_inputs').innerHTML = '';
    $('wiz_multi_equip_inputs').innerHTML = '';
    $('wiz_selected_count').textContent = '0';
    $('wiz_conflict_alert').classList.add('hidden');
  }

  function openMultiBorrowWizard() {
    var keys = Object.keys(msSelected);
    if (!keys.length) return;
    wizMultiMode = true;
    wizMultiEquips = keys.map(function (id) { return msSelected[id]; });
    var first = wizMultiEquips[0];
    wizEquipId = parseInt(first.id);
    wizEquipName = wizMultiEquips.map(function (e) { return e.name; }).join(', ');
    wizEquipMax = wizMultiEquips.reduce(function (s, e) { return s + parseInt(e.max); }, 0);
    wizStep = 1;
    resetWizardForm();
    $('wiz_equipment_id').value = wizEquipId;
    $('wiz_equip_name_display').textContent = wizMultiEquips.length + ' รายการที่เลือก';
    showEl($('wiz_equip_qty_span'), false);
    var list = $('wiz_multi_equip_list');
    list.classList.remove('d-none');
    list.innerHTML = wizMultiEquips.map(function (e) {
      return '<span class="multi-select-chip">' + escHtml(e.name) + '</span>';
    }).join(' ');
    buildSerialChipsMulti();
    showWizPage(1);
    openModal();
  }

  function openBorrowWizard(btn) {
    if (msActive) return;
    wizMultiMode = false;
    wizMultiEquips = [];
    wizEquipId = parseInt(btn.dataset.id);
    wizEquipName = btn.dataset.name;
    wizEquipMax = parseInt(btn.dataset.max);
    wizStep = 1;
    resetWizardForm();
    $('wiz_equipment_id').value = wizEquipId;
    $('wiz_equip_name_display').textContent = wizEquipName;
    $('wiz_equip_max_display').textContent = wizEquipMax;
    showEl($('wiz_equip_qty_span'), true);
    $('wiz_multi_equip_list').classList.add('d-none');
    buildSerialChips();
    showWizPage(1);
    openModal();
  }

  function openModal() {
    var modal = new bootstrap.Modal($('borrowWizardModal'));
    modal.show();
    $('borrowWizardModal').addEventListener('shown.bs.modal', function onShown() {
      initWizCalendar();
      initTomSelect();
      this.removeEventListener('shown.bs.modal', onShown);
    });
  }

  // ── Serial chips ──
  function isSerialBusyOnDates(sid, sd, ed) {
    if (!sd || !ed) return false;
    return (BUSY_BY_SERIAL[sid] || []).some(function (r) {
      return r.start && r.end && sd <= r.end && ed >= r.start;
    });
  }

  function makeSerialChip(s, sd, ed) {
    var busy = isSerialBusyOnDates(s.id, sd, ed);
    var chip = document.createElement('div');
    chip.className = 'serial-chip selected' + (busy ? ' busy' : '');
    chip.dataset.id = s.id;
    chip.dataset.code = s.code;
    chip.innerHTML = (busy ? '<i class="bi bi-exclamation-triangle-fill"></i>' : '<i class="bi bi-upc"></i>') + ' ' + escHtml(s.code)
      + (busy ? ' <small>(มีผู้จอง)</small>' : '');
    chip.addEventListener('click', function () { chip.classList.toggle('selected'); updateSelectedCount(); });
    return chip;
  }

  function buildSerialChips() {
    var c = $('wiz_serial_list');
    c.innerHTML = '';
    var serials = SERIALS_BY_EQUIP[wizEquipId] || [];
    if (!serials.length) { c.innerHTML = '<span class="text-muted">ไม่พบรหัสครุภัณฑ์</span>'; return; }
    var sd = $('wiz_borrow_date').value, ed = $('wiz_return_date').value;
    serials.forEach(function (s) { c.appendChild(makeSerialChip(s, sd, ed)); });
    updateSelectedCount();
  }

  function buildSerialChipsMulti() {
    var c = $('wiz_serial_list');
    c.innerHTML = '';
    var sd = $('wiz_borrow_date').value, ed = $('wiz_return_date').value;
    var has = false;
    wizMultiEquips.forEach(function (eq) {
      var serials = SERIALS_BY_EQUIP[eq.id] || [];
      if (!serials.length) return;
      has = true;
      var hdr = document.createElement('div');
      hdr.className = 'serial-group-header';
      hdr.textContent = eq.name;
      c.appendChild(hdr);
      serials.forEach(function (s) { c.appendChild(makeSerialChip(s, sd, ed)); });
    });
    if (!has) c.innerHTML = '<span class="text-muted">ไม่พบรหัสครุภัณฑ์</span>';
    updateSelectedCount();
  }

  function updateSelectedCount() {
    var n = document.querySelectorAll('#wiz_serial_list .serial-chip.selected').length;
    if ($('wiz_selected_count')) $('wiz_selected_count').textContent = n;
  }

  function getSelectedSerialIds() {
    var ids = [];
    document.querySelectorAll('#wiz_serial_list .serial-chip.selected').forEach(function (c) { ids.push(c.dataset.id); });
    return ids;
  }

  function getSelectedSerialCodes() {
    var codes = [];
    document.querySelectorAll('#wiz_serial_list .serial-chip.selected').forEach(function (c) { codes.push(c.dataset.code); });
    return codes;
  }

  // ── Calendar ──
  function getCalendarEquips() {
    return wizMultiMode ? wizMultiEquips : [{ id: String(wizEquipId), name: wizEquipName }];
  }

  function colorForEquip(eid) {
    var ids = getCalendarEquips().map(function (e) { return parseInt(e.id); });
    var idx = ids.indexOf(parseInt(eid));
    return EQUIP_COLORS[(idx >= 0 ? idx : 0) % EQUIP_COLORS.length];
  }

  function calendarEventsUrl() {
    var ids = getCalendarEquips().map(function (e) { return parseInt(e.id); }).filter(function (id) { return id > 0; });
    var qs = new URLSearchParams({ include_returned: '0' });
    if (ids.length) qs.set('equipment_ids', ids.join(','));
    return 'fetch_borrowings.php?' + qs.toString();
  }

  function initWizCalendar() {
    if (typeof FullCalendar === 'undefined') { setTimeout(initWizCalendar, 300); return; }
    var el = $('wiz_calendar');
    if (!el) return;
    if (wizCalendar) wizCalendar.destroy();
    wizCalendar = new FullCalendar.Calendar(el, {
      initialView: 'dayGridMonth',
      selectable: true,
      headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
      events: function (info, ok, fail) {
        fetch(calendarEventsUrl()).then(function (r) { return r.json(); }).then(function (data) {
          calBookings = data;
          ok(data.map(function (ev) {
            var color = colorForEquip(ev.equipment_id);
            return { title: ev.title, start: ev.start, end: ev.end, display: 'background', backgroundColor: color, borderColor: color, extendedProps: ev.extendedProps || {} };
          }));
        }).catch(fail);
      },
      select: function (info) {
        var s = info.startStr;
        var eDate = new Date(info.end.getTime() - 86400000);
        var eStr = formatDate(eDate);
        $('wiz_borrow_date').value = s;
        $('wiz_return_date').value = eStr;
        $('wiz_return_date').min = s;
        updateDayCount();
        checkConflict();
      },
    });
    wizCalendar.render();
  }

  function formatDate(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  function updateDayCount() {
    var d1 = $('wiz_borrow_date').value, d2 = $('wiz_return_date').value;
    if (d1) $('wiz_return_date').min = d1;
    if (d1 && d2) {
      var diff = Math.round((new Date(d2) - new Date(d1)) / 86400000);
      if (diff >= 0) { $('wiz_day_num').textContent = diff + 1; showEl($('wiz_day_badge'), true); }
      else showEl($('wiz_day_badge'), false);
    } else showEl($('wiz_day_badge'), false);
  }

  if ($('wiz_borrow_date')) $('wiz_borrow_date').addEventListener('change', function () { updateDayCount(); checkConflict(); });
  if ($('wiz_return_date')) $('wiz_return_date').addEventListener('change', function () { updateDayCount(); checkConflict(); });

  function checkConflict() {
    var sd = $('wiz_borrow_date').value, ed = $('wiz_return_date').value;
    var alertEl = $('wiz_conflict_alert');
    if (!sd || !ed) { alertEl.classList.add('hidden'); return false; }
    var any = calBookings.some(function (ev) { return ev.start && sd < (ev.end || '9999-12-31') && ed >= ev.start; });
    if (any) { $('wiz_conflict_msg').textContent = 'ช่วงวันที่นี้มีการจองแล้ว'; alertEl.classList.remove('hidden'); return true; }
    alertEl.classList.add('hidden');
    return false;
  }

  // ── Wizard navigation ──
  function showWizPage(n) {
    wizStep = n;
    [1, 2, 3, 4].forEach(function (i) {
      var panel = $('wizPage' + i);
      if (panel) panel.classList.toggle('active', i === n);
      var step = $('wstep' + i);
      if (step) {
        step.classList.remove('active', 'done');
        if (i < n) step.classList.add('done');
        if (i === n) step.classList.add('active');
      }
    });
    showEl($('wiz_btn_prev'), n > 1 && n < 4);
    showEl($('wiz_btn_draft'), n < 4);
    showEl($('wiz_btn_next'), n < 3);
    showEl($('wiz_btn_submit'), n === 3);
    showEl($('wizardFooter'), n !== 4);
  }

  if ($('wiz_btn_prev')) $('wiz_btn_prev').addEventListener('click', function () { if (wizStep > 1) showWizPage(wizStep - 1); });
  if ($('wiz_btn_next')) $('wiz_btn_next').addEventListener('click', wizNext);

  function wizNext() {
    if (wizStep === 1) {
      if (!getSelectedSerialIds().length) return Swal.fire('แจ้งเตือน', 'กรุณาเลือกรหัสครุภัณฑ์', 'warning');
      if (!$('wiz_borrow_date').value || !$('wiz_return_date').value) return Swal.fire('แจ้งเตือน', 'กรุณาเลือกวันที่', 'warning');
      if (checkConflict()) return;
      if ($('wiz_serial_code_display')) $('wiz_serial_code_display').value = getSelectedSerialCodes().join(', ');
      showWizPage(2);
    } else if (wizStep === 2) {
      var errs = [];
      if (!$('wiz_borrower_name').value.trim()) errs.push('กรุณากรอกชื่อ-นามสกุล');
      if (!$('wiz_borrower_position').value) errs.push('กรุณาเลือกตำแหน่ง');
      if (!$('wiz_borrower_unit').value) errs.push('กรุณาเลือกหน่วยงาน');
      if (!$('wiz_equipment_type_select').value) errs.push('กรุณาเลือกประเภทการยืม');
      if (!$('wiz_purpose').value) errs.push('กรุณาเลือกเหตุผล');
      if ($('wiz_it_install').value === '') errs.push('กรุณาเลือกการติดตั้ง IT');
      if (!$('wiz_location').value.trim()) errs.push('กรุณากรอกสถานที่');
      var ph = $('wiz_phone').value.trim();
      if (ph && !/^[0-9]{4,6}$/.test(ph)) errs.push('เบอร์ภายใน 4-6 หลัก');
      if (errs.length) return Swal.fire({ title: 'ข้อมูลไม่ครบ', html: errs.join('<br>'), icon: 'warning' });
      buildSummary();
      showWizPage(3);
    }
  }

  function buildSummary() {
    var rows = [
      ['ครุภัณฑ์', wizMultiMode ? wizMultiEquips.map(function (e) { return e.name; }).join(', ') : wizEquipName],
      ['รหัส', getSelectedSerialCodes().join(', ')],
      ['วันที่ยืม', $('wiz_borrow_date').value],
      ['กำหนดคืน', $('wiz_return_date').value],
      ['ผู้ยืม', $('wiz_borrower_name').value],
      ['หน่วยงาน', $('wiz_borrower_unit').value],
      ['สถานที่', $('wiz_location').value],
    ];
    $('wiz_summary').innerHTML = rows.map(function (r) {
      return '<div class="summary-row"><span class="summary-row__label">' + escHtml(r[0]) + '</span>' + escHtml(r[1]) + '</div>';
    }).join('');
  }

  if ($('wiz_it_install_select')) {
    $('wiz_it_install_select').addEventListener('change', function () {
      $('wiz_it_install').value = this.value;
    });
  }

  function initTomSelect() {
    var el = $('wiz_borrower_unit');
    if (el && !el.tomselect && typeof TomSelect !== 'undefined') {
      new TomSelect(el, { placeholder: 'ค้นหาหน่วยงาน...', maxOptions: 300 });
    }
  }

  if ($('borrowWizardForm')) {
    $('borrowWizardForm').addEventListener('submit', function (e) {
      if (wizStep !== 3) { e.preventDefault(); wizNext(); return; }
      $('wiz_serial_inputs').innerHTML = '';
      getSelectedSerialIds().forEach(function (id) {
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'serial_ids[]'; inp.value = id;
        $('wiz_serial_inputs').appendChild(inp);
      });
      if (wizMultiMode) {
        $('wiz_multi_equip_inputs').innerHTML = '';
        wizMultiEquips.forEach(function (eq) {
          var inp = document.createElement('input');
          inp.type = 'hidden'; inp.name = 'multi_equipment_ids[]'; inp.value = eq.id;
          $('wiz_multi_equip_inputs').appendChild(inp);
        });
      }
      $('wiz_btn_submit').disabled = true;
      showEl($('wiz_submit_spinner'), true);
      $('wiz_submit_text').textContent = 'กำลังส่ง...';
    });
  }
};
