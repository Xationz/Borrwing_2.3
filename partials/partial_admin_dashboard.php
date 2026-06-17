<?php
// partial_admin_dashboard.php — SPA partial (content only)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        http_response_code(403); echo '<div class="alert alert-danger m-3">ไม่มีสิทธิ์เข้าถึง</div>';
    } else { header('Location: ../login.php'); }
    exit;
}

// Stats
$total_equipment  = $pdo->query("SELECT COUNT(*) FROM equipment")->fetchColumn();
$total_categories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$total_borrowing  = $pdo->query("SELECT COUNT(*) FROM borrowings WHERE status IN ('borrowing','borrowed','pending')")->fetchColumn();
$total_waiting    = $pdo->query("SELECT COUNT(*) FROM borrowings WHERE status='waiting_return_approval'")->fetchColumn();
$total_returned   = $pdo->query("SELECT COUNT(*) FROM borrowings WHERE status='returned'")->fetchColumn();
$total_users      = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$total_admins     = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();

// Recent borrowings
$recent = $pdo->query("
    SELECT b.*, e.name AS equip_name, u.username
    FROM borrowings b
    JOIN equipment e ON b.equipment_id = e.id
    JOIN users u ON b.user_id = u.id
    ORDER BY b.created_at DESC LIMIT 10
")->fetchAll();

// Most borrowed
$most_borrowed = $pdo->query("
    SELECT e.name, SUM(b.quantity) as cnt
    FROM borrowings b JOIN equipment e ON b.equipment_id = e.id
    GROUP BY e.id ORDER BY cnt DESC LIMIT 5
")->fetchAll();
$chart_labels = array_column($most_borrowed, 'name');
$chart_data   = array_map(fn($r)=>['name'=>$r['name'],'y'=>(int)$r['cnt'],'color'=>['#7c3aed','#f59e0b','#ef4444','#3b82f6','#10b981'][array_search($r,$most_borrowed)%5]], $most_borrowed);
?>

<div class="page-header">
    <h4><i class="bi bi-grid-1x2 me-2"></i>Admin Dashboard</h4>
    <p>ยินดีต้อนรับ, <?php echo htmlspecialchars($_SESSION['username']); ?> — ภาพรวมระบบยืมครุภัณฑ์</p>
</div>

<style>
.btn-print-doc {
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 0.78rem;
    padding: 5px 12px;
    font-weight: 600;
    transition: opacity .2s, transform .1s;
    white-space: nowrap;
}
.btn-print-doc:hover {
    color: #fff;
    opacity: .88;
    transform: translateY(-1px);
}
.btn-print-doc:active { transform: translateY(0); }
#adminCalendar .fc-event-title { font-weight: 700; white-space: normal; }
</style>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <?php $stats = [
        ['icon'=>'bi-laptop','label'=>'ครุภัณฑ์ทั้งหมด','value'=>$total_equipment,'color'=>'#7c3aed','bg'=>'#ede9fe'],
        ['icon'=>'bi-tags','label'=>'หมวดหมู่','value'=>$total_categories,'color'=>'#2563eb','bg'=>'#dbeafe'],
        ['icon'=>'bi-clock','label'=>'กำลังยืม','value'=>$total_borrowing,'color'=>'#d97706','bg'=>'#fff7ed'],
        ['icon'=>'bi-hourglass-split','label'=>'รอยืนยันคืน','value'=>$total_waiting,'color'=>'#1d4ed8','bg'=>'#dbeafe'],
        ['icon'=>'bi-check-circle','label'=>'คืนแล้ว','value'=>$total_returned,'color'=>'#059669','bg'=>'#d1fae5'],
        ['icon'=>'bi-people','label'=>'ผู้ใช้งาน','value'=>$total_users,'color'=>'#dc2626','bg'=>'#fee2e2'],
    ]; foreach ($stats as $s): ?>
    <div class="col-6 col-md-4 col-lg-2"><?php // 6 items fit with col-lg-2 ?>
        <div class="card text-center" style="border-radius:14px">
            <div class="card-body py-3" style="background:<?php echo $s['bg']; ?>">
                <i class="bi <?php echo $s['icon']; ?> fs-2" style="color:<?php echo $s['color']; ?>"></i>
                <div class="fw-bold fs-4 mt-1" style="color:<?php echo $s['color']; ?>"><?php echo $s['value']; ?></div>
                <div style="font-size:0.78rem; color:#6b7280"><?php echo $s['label']; ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <!-- Chart -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-trophy"></i> ครุภัณฑ์ที่ถูกยืมมากที่สุด</div>
            <div class="card-body">
                <div id="adminBorrowChart" style="height:280px"></div>
            </div>
        </div>
    </div>
    <!-- Recent borrowings -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-clock-history"></i> รายการยืมล่าสุด</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">ผู้ยืม</th>
                                <th>ครุภัณฑ์</th>
                                <th>วันที่</th>
                                <th>สถานะ</th>
                                <th class="text-center">พิมพ์</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent as $b): ?>
                            <tr>
                                <td class="ps-3"><?php echo htmlspecialchars($b['borrower_name'] ?? $b['username']); ?></td>
                                <td><?php echo htmlspecialchars($b['equip_name']); ?></td>
                                <td><?php echo $b['borrow_date'] ?? '-'; ?></td>
                                <td>
                                    <?php if ($b['status'] === 'borrowed' || $b['status'] === 'borrowing' || $b['status'] === 'pending'): ?>
                                    <span class="badge-borrowed">กำลังยืม</span>
                                    <?php elseif ($b['status'] === 'waiting_return_approval'): ?>
                                    <span class="badge" style="background:#dbeafe;color:#1d4ed8;border-radius:20px">รอตรวจสอบ</span>
                                    <?php else: ?>
                                    <span class="badge-returned">คืนแล้ว</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="generate_borrow_pdf.php?borrowing_id=<?php echo $b['id']; ?>" target="_blank" class="btn btn-sm btn-print-doc" title="พิมพ์แบบฟอร์มยืม #<?php echo $b['id']; ?>">
                                        <i class="bi bi-printer-fill me-1"></i>พิมพ์
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recent)): ?>
                            <tr><td colspan="5" class="text-center py-3 text-muted">ยังไม่มีรายการยืม</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Calendar -->
<div class="card mt-4">
    <div class="card-header"><i class="bi bi-calendar3"></i> ปฏิทินการยืม</div>
    <div class="card-body">
        <div id="adminCalendar" style="min-height:400px"></div>
        <div class="d-flex flex-wrap gap-2 mt-2" style="font-size:0.78rem;color:#6b7280">
            <span><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#10b981"></span> กำลังยืม</span>
            <span><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#3b82f6"></span> รอตรวจสอบคืน</span>
        </div>
    </div>
</div>

<script>
(function(){
    var chartData   = <?php echo json_encode($chart_data); ?>;
    var chartLabels = <?php echo json_encode($chart_labels); ?>;

    function initChart() {
        if (typeof Highcharts === 'undefined') { setTimeout(initChart,200); return; }
        var el = document.getElementById('adminBorrowChart');
        if (!el) return;
        Highcharts.chart('adminBorrowChart',{
            chart:{type:'column',backgroundColor:'#ffffff',borderRadius:10},
            title:{text:'',},
            xAxis:{categories:chartLabels,labels:{style:{color:'#6d28d9',fontFamily:'Sarabun'}}},
            yAxis:{min:0,title:{text:'จำนวนที่ยืม',style:{color:'#6d28d9',fontFamily:'Sarabun'}},allowDecimals:false},
            series:[{name:'ยืม',data:chartData,colorByPoint:true}],
            plotOptions:{column:{dataLabels:{enabled:true,style:{color:'#4c1d95',fontWeight:'bold'}},borderRadius:6}},
            tooltip:{backgroundColor:'#6d28d9',style:{color:'#fff',fontFamily:'Sarabun'}},
            legend:{enabled:false},credits:{enabled:false}
        });
    }

    function initCalendar() {
        if (typeof FullCalendar === 'undefined') { setTimeout(initCalendar,300); return; }
        var el = document.getElementById('adminCalendar');
        if (!el) return;
        if (el._fcAPI) { el._fcAPI.destroy(); }
        var statusCfg = {
            pending: { color:'#10b981', label:'กำลังยืม' },
            borrowing: { color:'#10b981', label:'กำลังยืม' },
            borrowed: { color:'#10b981', label:'กำลังยืม' },
            waiting_return_approval: { color:'#3b82f6', label:'รอตรวจสอบคืน' },
            returned: { color:'#9ca3af', label:'คืนแล้ว' }
        };
        function cfg(status) {
            return statusCfg[status] || { color:'#6b7280', label:status || '-' };
        }
        function escHtml(s) {
            return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
        function eventEndInclusive(event) {
            if (!event.end) return '-';
            var d = new Date(event.end.getTime() - 86400000);
            var y = d.getFullYear();
            var m = String(d.getMonth() + 1).padStart(2, '0');
            var day = String(d.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + day;
        }
        var cal = new FullCalendar.Calendar(el,{
            initialView:'dayGridMonth',
            events:'fetch_borrowings.php?include_returned=0',
            dayMaxEvents:3,
            headerToolbar:{
                left:'prev,next today',
                center:'title',
                right:'dayGridMonth,listMonth'
            },
            eventDidMount:function(info) {
                var c = cfg(info.event.extendedProps.status);
                info.el.style.backgroundColor = c.color;
                info.el.style.borderColor = c.color;
                info.el.style.borderRadius = '6px';
            },
            eventContent:function(arg) {
                var ep = arg.event.extendedProps;
                var c = cfg(ep.status);
                if (arg.view.type === 'listMonth') {
                    return { html:
                        '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:3px 0">' +
                        '<span style="background:' + c.color + ';color:#fff;border-radius:12px;padding:1px 8px;font-size:0.72rem">' + escHtml(c.label) + '</span>' +
                        '<strong>' + escHtml(arg.event.title) + '</strong>' +
                        '<span style="color:#6b7280">ผู้ยืม: ' + escHtml(ep.borrowerName || ep.username || '-') + '</span>' +
                        (ep.serials && ep.serials !== '-' ? '<span style="color:#6d28d9">รหัส: ' + escHtml(ep.serials) + '</span>' : '') +
                        '</div>'
                    };
                }
                return { html:
                    '<div style="padding:2px 5px;font-size:0.74rem;line-height:1.25">' +
                    '<strong>' + escHtml(arg.event.title) + '</strong><br>' +
                    '<span style="opacity:.9">' + escHtml(ep.serials || '') + '</span>' +
                    '</div>'
                };
            },
            eventClick:function(info){
                var ep = info.event.extendedProps;
                var c = cfg(ep.status);
                Swal.fire({
                    title:'รายละเอียดการยืม',
                    html:'<div style="text-align:left;line-height:1.8">' +
                         '<p><strong>ครุภัณฑ์:</strong> ' + escHtml(info.event.title) + '</p>' +
                         '<p><strong>รหัสครุภัณฑ์:</strong> ' + escHtml(ep.serials || '-') + '</p>' +
                         '<p><strong>ผู้ยืม:</strong> ' + escHtml(ep.borrowerName || ep.username || '-') + '</p>' +
                         '<p><strong>วันที่ยืม:</strong> ' + escHtml(info.event.startStr) + '</p>' +
                         '<p><strong>กำหนดคืน:</strong> ' + escHtml(eventEndInclusive(info.event)) + '</p>' +
                         '<p><strong>สถานะ:</strong> <span style="background:' + c.color + ';color:#fff;border-radius:12px;padding:2px 10px;font-size:0.8rem">' + escHtml(c.label) + '</span></p>' +
                         '</div>',
                    icon:'info',confirmButtonColor:c.color
                });
            }
        });
        cal.render();
        el._fcAPI = cal;
    }

    initChart();
    initCalendar();
})();
</script>
