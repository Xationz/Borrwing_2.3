<?php
// partial_calendar.php — SPA partial (content only)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        http_response_code(403); echo '<div class="alert alert-danger m-3">ไม่มีสิทธิ์เข้าถึง</div>';
    } else { header('Location: ../login.php'); }
    exit;
}
?>

<style>
/* Calendar legend */
.cal-legend { display:flex; flex-wrap:wrap; gap:10px; align-items:center; font-size:0.82rem; }
.cal-legend-dot { width:12px; height:12px; border-radius:3px; display:inline-block; }
/* Tooltip-style popup */
.fc-event-title { font-weight:600; white-space:normal; overflow:hidden; }
#spa-calendar .fc-bg-event { opacity: .72; }
#spa-calendar .fc-bg-event .fc-event-title,
#spa-calendar .fc-bg-event .fc-event-main { display: none !important; }
#spa-calendar .fc-daygrid-day-number { position: relative; z-index: 3; font-weight: 700; }
#spa-calendar .fc-daygrid-day { cursor: pointer; }
#spa-calendar .fc-daygrid-day:hover { background: #f5f3ff !important; }
.borrow-status-panel { margin-top:14px; background:#f9f7ff; border:1px solid #ede9fe; border-radius:12px; padding:12px 14px; }
.borrow-status-title { color:#4c1d95; font-weight:800; font-size:0.9rem; margin-bottom:8px; display:flex; align-items:center; gap:6px; }
.borrow-status-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:8px; }
.borrow-status-item { background:#fff; border:1px solid #ede9fe; border-left:5px solid #7c3aed; border-radius:8px; padding:8px 10px; font-size:0.82rem; }
.borrow-status-item strong { color:#1f2937; }
.borrow-status-meta { color:#6b7280; font-size:0.76rem; margin-top:2px; }
</style>

<div class="page-header">
    <h4><i class="bi bi-calendar3 me-2"></i>ปฏิทินการยืมครุภัณฑ์</h4>
    <p>แสดงตารางการยืม-คืนครุภัณฑ์ทั้งหมด พร้อมสถานะ</p>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span><i class="bi bi-calendar3"></i> ปฏิทินการยืม</span>
        <div class="cal-legend" id="equipmentColorLegend">
            <span class="text-white-50">กำลังโหลดสีประจำเครื่อง...</span>
        </div>
    </div>
    <div class="card-body">
        <div id="spa-calendar" style="min-height:520px;"></div>
        <div class="borrow-status-panel">
            <div class="borrow-status-title"><i class="bi bi-list-check"></i> สรุปสถานะครุภัณฑ์ที่ถูกจอง/ยืมอยู่</div>
            <div id="borrowStatusGrid" class="borrow-status-grid">
                <div class="text-muted small">กำลังโหลดข้อมูล...</div>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    // Status → colour + label
    var STATUS_CONFIG = {
        'pending':                  { color: '#10b981', border: '#059669', label: 'กำลังยืม',           icon: '📦' },
        'approved':                 { color: '#7c3aed', border: '#6d28d9', label: 'อนุมัติแล้ว',        icon: '✅' },
        'borrowed':                 { color: '#10b981', border: '#059669', label: 'กำลังยืม',           icon: '📦' },
        'borrowing':                { color: '#10b981', border: '#059669', label: 'กำลังยืม',           icon: '📦' },
        'waiting_return_approval':  { color: '#3b82f6', border: '#2563eb', label: 'รอตรวจสอบการคืน',   icon: '🔄' },
        'returned':                 { color: '#9ca3af', border: '#6b7280', label: 'คืนแล้ว',            icon: '✔' },
    };

    function cfgOf(status) {
        return STATUS_CONFIG[status] || { color:'#6b7280', border:'#4b5563', label: status, icon:'?' };
    }

    var EQUIP_COLORS = ['#ef4444', '#2563eb', '#eab308', '#16a34a', '#f97316', '#9333ea', '#0891b2', '#db2777', '#64748b', '#14b8a6'];
    var equipColorMap = {};
    var equipOrder = [];
    var calBookings = [];

    function equipmentKey(ev) {
        return String(ev.equipment_id || ev.id || ev.title || '');
    }

    function colorForEquipment(ev) {
        var key = equipmentKey(ev);
        if (!equipColorMap[key]) {
            equipColorMap[key] = EQUIP_COLORS[equipOrder.length % EQUIP_COLORS.length];
            equipOrder.push({
                key: key,
                title: ev.title || 'ครุภัณฑ์'
            });
        }
        return equipColorMap[key];
    }

    function rebuildEquipmentColors(events) {
        equipColorMap = {};
        equipOrder = [];
        (events || []).forEach(function(ev) { colorForEquipment(ev); });
        updateEquipmentLegend();
    }

    function updateEquipmentLegend() {
        var legend = document.getElementById('equipmentColorLegend');
        if (!legend) return;
        if (equipOrder.length === 0) {
            legend.innerHTML = '<span class="text-white-50">ไม่มีครุภัณฑ์ที่ถูกจอง/ยืมอยู่</span>';
            return;
        }
        legend.innerHTML = equipOrder.map(function(item) {
            return '<span><span class="cal-legend-dot" style="background:' + equipColorMap[item.key] + '"></span> ' + escHtml(item.title) + '</span>';
        }).join('');
    }

    function formatDateThai(dateStr) {
        if (!dateStr) return '-';
        var p = String(dateStr).split('-').map(function(n){ return parseInt(n, 10); });
        if (!p[0] || !p[1] || !p[2]) return dateStr;
        return String(p[2]).padStart(2, '0') + '/' + String(p[1]).padStart(2, '0') + '/' + (p[0] + 543);
    }

    function endInclusiveStr(eventOrRaw) {
        var endStr = eventOrRaw.endStr || eventOrRaw.end || '';
        if (!endStr) return eventOrRaw.startStr || eventOrRaw.start || '';
        var p = String(endStr).split('T')[0].split('-').map(function(n){ return parseInt(n, 10); });
        var d = new Date(p[0], (p[1] || 1) - 1, p[2] || 1);
        d.setDate(d.getDate() - 1);
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function updateBorrowStatusPanel(events) {
        var grid = document.getElementById('borrowStatusGrid');
        if (!grid) return;
        var active = (events || []).filter(function(ev) {
            var st = ev.extendedProps && ev.extendedProps.status ? ev.extendedProps.status : '';
            return st !== 'returned';
        });
        if (active.length === 0) {
            grid.innerHTML = '<div class="text-muted small">ไม่มีครุภัณฑ์ที่ถูกจองหรือยืมอยู่</div>';
            return;
        }
        active.sort(function(a, b) {
            return String(a.start || '').localeCompare(String(b.start || '')) || String(a.title || '').localeCompare(String(b.title || ''));
        });
        grid.innerHTML = active.map(function(ev) {
            var ep = ev.extendedProps || {};
            var cfg = cfgOf(ep.status);
            var equipColor = colorForEquipment(ev);
            return '<div class="borrow-status-item" style="border-left-color:' + equipColor + '">'
                + '<div><span style="display:inline-block;width:11px;height:11px;border-radius:3px;background:' + equipColor + ';margin-right:5px"></span>'
                + '<span style="background:' + cfg.color + ';color:#fff;border-radius:12px;padding:1px 8px;font-size:0.72rem;font-weight:700">' + escHtml(cfg.label) + '</span> '
                + '<strong>' + escHtml(ev.title) + '</strong></div>'
                + '<div class="borrow-status-meta">รหัส: ' + escHtml(ep.serials || '-') + '</div>'
                + '<div class="borrow-status-meta">ช่วงยืม: ' + formatDateThai(ev.start) + ' - ' + formatDateThai(endInclusiveStr(ev)) + '</div>'
                + '<div class="borrow-status-meta">ผู้ยืม: ' + escHtml(ep.borrowerName || ep.username || '-') + '</div>'
                + '</div>';
        }).join('');
    }

    function eachBookingDate(ev, cb) {
        if (!ev.start) return;
        var cur = parseDate(ev.start);
        var end = ev.end ? parseDate(ev.end) : new Date(cur.getTime() + 86400000);
        while (cur < end) {
            cb(formatDate(cur));
            cur.setDate(cur.getDate() + 1);
        }
    }

    function bookingsOnDate(dateStr) {
        var entries = [];
        calBookings.forEach(function(ev) {
            eachBookingDate(ev, function(key) {
                if (key !== dateStr) return;
                entries.push(ev);
            });
        });
        return entries;
    }

    function showDayBookings(dateStr) {
        var entries = bookingsOnDate(dateStr);
        if (entries.length === 0) return;
        var html = '<div style="text-align:left;font-size:0.9rem;line-height:1.75">';
        entries.forEach(function(ev) {
            var ep = ev.extendedProps || {};
            var cfg = cfgOf(ep.status);
            var equipColor = colorForEquipment(ev);
            html += '<div style="border-left:5px solid ' + equipColor + ';padding:6px 0 6px 10px;border-bottom:1px solid #e5e7eb">'
                + '<div><span style="display:inline-block;width:11px;height:11px;border-radius:3px;background:' + equipColor + ';margin-right:5px"></span>'
                + '<strong>' + escHtml(ev.title) + '</strong> '
                + '<span style="background:' + cfg.color + ';color:#fff;border-radius:12px;padding:1px 8px;font-size:0.75rem">' + escHtml(cfg.label) + '</span></div>'
                + '<div style="color:#6b7280;font-size:0.82rem">รหัส: ' + escHtml(ep.serials || '-') + '</div>'
                + '<div style="color:#6b7280;font-size:0.82rem">ช่วงยืม: ' + formatDateThai(ev.start) + ' - ' + formatDateThai(endInclusiveStr(ev)) + '</div>'
                + '<div style="color:#6b7280;font-size:0.82rem">ผู้ยืม: ' + escHtml(ep.borrowerName || ep.username || '-') + '</div>'
                + '</div>';
        });
        html += '</div>';
        Swal.fire({
            title: 'รายการวันที่ ' + formatDateThai(dateStr),
            html: html,
            icon: 'info',
            confirmButtonColor: '#7c3aed',
            width: 520
        });
    }

    function parseDate(dateStr) {
        var p = String(dateStr).split('T')[0].split('-').map(function(n){ return parseInt(n, 10); });
        return new Date(p[0], (p[1] || 1) - 1, p[2] || 1);
    }

    function formatDate(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function initCalendar() {
        if (typeof FullCalendar === 'undefined') { setTimeout(initCalendar, 300); return; }
        var el = document.getElementById('spa-calendar');
        if (!el) return;
        if (el._fcAPI) { el._fcAPI.destroy(); }

        var calendar = new FullCalendar.Calendar(el, {
            initialView: 'dayGridMonth',
            events: function(fetchInfo, successCallback, failureCallback) {
                fetch('fetch_borrowings.php?include_returned=0')
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        calBookings = data;
                        rebuildEquipmentColors(data);
                        updateBorrowStatusPanel(data);
                        successCallback(data.map(function(ev) {
                            var color = colorForEquipment(ev);
                            return {
                                id: 'bg-' + (ev.equipment_id || ev.title) + '-' + ev.start + '-' + (ev.extendedProps ? ev.extendedProps.serials : ''),
                                title: ev.title,
                                start: ev.start,
                                end: ev.end,
                                display: 'background',
                                backgroundColor: color,
                                borderColor: color,
                                extendedProps: ev.extendedProps || {}
                            };
                        }));
                    })
                    .catch(failureCallback);
            },
            headerToolbar: {
                left:   'prev,next today',
                center: 'title',
                right:  'dayGridMonth,timeGridWeek'
            },
            views: {
                dayGridMonth: { buttonText: '📅 เดือน' },
                timeGridWeek: { buttonText: '📆 สัปดาห์' }
            },
            dayMaxEvents: 3,
            dateClick: function(info) {
                showDayBookings(info.dateStr);
            },
            // Apply status-based colour + tooltip per event
            eventDidMount: function(info) {
                var cfg = cfgOf(info.event.extendedProps.status);
                var equipColor = colorForEquipment({
                    equipment_id: info.event.extendedProps.equipment_id || info.event.extendedProps.equipmentId || info.event._def.extendedProps.equipment_id,
                    title: info.event.title
                });
                info.el.style.backgroundColor = equipColor;
                info.el.style.borderColor     = equipColor;
                info.el.style.borderRadius    = '5px';
                var ep = info.event.extendedProps;
                info.el.title = info.event.title + ' | ' + cfg.label
                    + '\nยืมถึง: ' + formatDateThai(endInclusiveStr(info.event))
                    + '\nผู้ยืม: ' + (ep.borrowerName || ep.username || '-')
                    + (ep.serials && ep.serials !== '-' ? '\nรหัส: ' + ep.serials : '');
            },
            // Render title with icon + equip name + borrower
            eventContent: function(arg) {
                if (arg.event.display === 'background') {
                    return { html: '' };
                }
                var ep  = arg.event.extendedProps;
                var cfg = cfgOf(ep.status);
                var name = ep.borrowerName || ep.username || '';
                var dueText = formatDateThai(endInclusiveStr(arg.event));
                var equipColor = colorForEquipment({ equipment_id: ep.equipment_id || ep.equipmentId || arg.event.title, title: arg.event.title });
                if (arg.view.type === 'listMonth') {
                    var html = '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:4px 0">'
                             + '<span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:' + equipColor + '"></span>'
                             + '<span style="background:' + cfg.color + ';color:#fff;border-radius:10px;padding:1px 8px;font-size:0.73rem;font-weight:600">' + cfg.icon + ' ' + cfg.label + '</span>'
                             + '<span style="font-weight:600;color:#1f2937">' + escHtml(arg.event.title) + '</span>'
                             + '<span style="color:#dc2626;font-size:0.8rem">ถึง ' + escHtml(dueText) + '</span>'
                             + '<span style="color:#6b7280;font-size:0.8rem">| ' + escHtml(name) + '</span>'
                             + (ep.serials && ep.serials !== '-' ? '<span style="color:#7c3aed;font-size:0.75rem">S/N: ' + escHtml(ep.serials) + '</span>' : '')
                             + '</div>';
                    return { html: html };
                }
                var html = '<div class="fc-event-title" style="padding:2px 5px;font-size:0.74rem;line-height:1.25;overflow:hidden">'
                         + '<strong>' + escHtml(arg.event.title) + '</strong>'
                         + ' <span style="font-size:0.68rem;background:rgba(255,255,255,.24);border-radius:8px;padding:0 5px">' + escHtml(cfg.label) + '</span>'
                         + '<br><span style="font-size:0.68rem">ถึง ' + escHtml(dueText) + '</span>'
                         + '<br><span style="opacity:.9;font-size:0.68rem">' + escHtml(name) + '</span>'
                         + '</div>';
                return { html: html };
            },
            eventClick: function(info) {
                var ep  = info.event.extendedProps;
                var cfg = cfgOf(ep.status);
                Swal.fire({
                    title: '<span style="font-size:1rem">' + cfg.icon + ' ' + escHtml(info.event.title) + '</span>',
                    html: '<div style="text-align:left;font-size:0.9rem;line-height:1.8">'
                        + '<p><i class="bi bi-person me-1"></i><strong>ผู้ยืม:</strong> '      + escHtml(ep.borrowerName || ep.username || '-') + '</p>'
                        + '<p><i class="bi bi-upc me-1"></i><strong>รหัสครุภัณฑ์:</strong> '   + escHtml(ep.serials || '-') + '</p>'
                        + '<p><i class="bi bi-calendar me-1"></i><strong>วันที่ยืม:</strong> '  + info.event.startStr + '</p>'
                        + '<p><i class="bi bi-calendar-check me-1"></i><strong>ยืมถึงวันที่:</strong> '
                            + formatDateThai(endInclusiveStr(info.event)) + '</p>'
                        + '<p><i class="bi bi-info-circle me-1"></i><strong>สถานะ:</strong> '
                            + '<span style="background:' + cfg.color + ';color:#fff;border-radius:12px;padding:2px 10px;font-size:0.8rem">'
                            + cfg.label + '</span></p>'
                        + (ep.image ? '<img src="Uploads/' + escHtml(ep.image) + '" style="max-width:180px;height:auto;margin-top:8px;border-radius:8px">' : '')
                        + '</div>',
                    confirmButtonColor: cfg.color,
                    confirmButtonText: 'ปิด'
                });
            }
        });

        calendar.render();
        el._fcAPI = calendar;
    }

    function escHtml(s) {
        return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    initCalendar();
})();
</script>
