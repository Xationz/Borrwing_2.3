<?php
// partial_borrowing_dashboard.php — Analytics Dashboard (Full Version)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config.php';

if (!isset($_SESSION['user_id'])) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        http_response_code(403);
        echo '<div class="alert alert-danger m-3">ไม่มีสิทธิ์เข้าถึง</div>';
    } else {
        header('Location: ../login.php');
    }
    exit;
}

$role = $_SESSION['role'] ?? 'user';

// ── Handle Export CSV / Excel ──────────────────────────────────────────────────
if (!empty($_GET['export']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) === false) {
    $export = $_GET['export'];
    $stmt = $pdo->query("
        SELECT b.id,
            COALESCE(b.borrower_name, u.username) AS borrower_name,
            u.username AS student_code,
            COALESCE(b.borrower_unit,'ไม่ระบุ') AS unit,
            e.name AS equipment_name,
            COALESCE(b.quantity,1) AS quantity,
            b.borrow_date,
            COALESCE(b.return_date_planned, b.return_date) AS planned_return,
            b.actual_return_date,
            b.status
        FROM borrowings b
        JOIN equipment e ON b.equipment_id = e.id
        JOIN users u ON b.user_id = u.id
        ORDER BY b.created_at DESC
    ");
    $rows = $stmt->fetchAll();
    $statusMap = ['borrowing'=>'กำลังยืม','borrowed'=>'กำลังยืม','waiting_return_approval'=>'รอการยืนยันคืน','returned'=>'คืนสำเร็จ'];

    if ($export === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="borrowing_report_'.date('Ymd').'.csv"');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
        $out = fopen('php://output', 'w');
        fputcsv($out, ['รหัสรายการ','ชื่อผู้ยืม','รหัสนิสิต','หน่วยงาน','ครุภัณฑ์','จำนวน','วันที่ยืม','กำหนดคืน','วันคืนจริง','สถานะ']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['id'],$r['borrower_name'],$r['student_code'],$r['unit'],$r['equipment_name'],$r['quantity'],$r['borrow_date'],$r['planned_return'],$r['actual_return_date'],$statusMap[$r['status']] ?? $r['status']]);
        }
        fclose($out);
        exit;
    }
}

// ── Summary Cards ──────────────────────────────────────────────────────────────
try {
    $cnt_total    = $pdo->query("SELECT COUNT(*) FROM borrowings")->fetchColumn();
    $cnt_borrowing= $pdo->query("SELECT COUNT(*) FROM borrowings WHERE status IN ('borrowing','borrowed')")->fetchColumn();
    $cnt_waiting  = $pdo->query("SELECT COUNT(*) FROM borrowings WHERE status='waiting_return_approval'")->fetchColumn();
    $cnt_returned = $pdo->query("SELECT COUNT(*) FROM borrowings WHERE status='returned'")->fetchColumn();
    $cnt_users    = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
    $cnt_equip    = $pdo->query("SELECT COUNT(*) FROM equipment")->fetchColumn();
    $cnt_cats     = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
} catch(Exception $e) {
    $cnt_total=$cnt_borrowing=$cnt_waiting=$cnt_returned=$cnt_users=$cnt_equip=$cnt_cats=0;
}

// ── Users ───────────────────────────────────────────────────────────────
try {
    $top_users = $pdo->query("
        SELECT u.id, u.username,
            COALESCE(MAX(b.borrower_name), u.username) AS display_name,
            COALESCE(MAX(b.borrower_unit), '-') AS unit,
            COUNT(b.id) AS borrow_times
        FROM users u
        JOIN borrowings b ON b.user_id = u.id
        GROUP BY u.id
        ORDER BY borrow_times DESC
        LIMIT 10
    ")->fetchAll();
} catch(Exception $e) { $top_users = []; }

// ──  Departments ────────────────────────────────────────────────────────
try {
    $top_units = $pdo->query("
        SELECT COALESCE(borrower_unit,'ไม่ระบุ') AS unit,
            COUNT(*) AS borrow_times,
            SUM(COALESCE(quantity,1)) AS total_qty
        FROM borrowings
        GROUP BY unit
        ORDER BY borrow_times DESC
        LIMIT 10
    ")->fetchAll();
} catch(Exception $e) { $top_units = []; }

// ── Equipment ──────────────────────────────────────────────────────────
try {
    $top_equip = $pdo->query("
        SELECT e.id, e.name,
            COALESCE(c.name,'ไม่ระบุ') AS category_name,
            COUNT(b.id) AS borrow_times
        FROM borrowings b
        JOIN equipment e ON b.equipment_id = e.id
        LEFT JOIN categories c ON e.category_id = c.id
        GROUP BY e.id
        ORDER BY borrow_times DESC
        LIMIT 10
    ")->fetchAll();
} catch(Exception $e) { $top_equip = []; }

// ── Monthly Stats (12 months) ─────────────────────────────────────────────────
try {
    $monthly_raw = $pdo->query("
        SELECT DATE_FORMAT(borrow_date,'%Y-%m') AS month,
               COUNT(*) AS count
        FROM borrowings
        WHERE borrow_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ")->fetchAll();
} catch(Exception $e) { $monthly_raw = []; }

// Fill all 12 months even if 0
$monthly_labels = [];
$monthly_data   = [];
$monthly_map    = [];
foreach ($monthly_raw as $r) $monthly_map[$r['month']] = (int)$r['count'];
for ($i = 11; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-$i months"));
    $label = date('M Y', strtotime("-$i months"));
    // Convert month to Thai short name
    $thMonths = ['Jan'=>'ม.ค.','Feb'=>'ก.พ.','Mar'=>'มี.ค.','Apr'=>'เม.ย.','May'=>'พ.ค.',
                 'Jun'=>'มิ.ย.','Jul'=>'ก.ค.','Aug'=>'ส.ค.','Sep'=>'ก.ย.','Oct'=>'ต.ค.',
                 'Nov'=>'พ.ย.','Dec'=>'ธ.ค.'];
    $parts = explode(' ', $label);
    $thLabel = ($thMonths[$parts[0]] ?? $parts[0]) . ' ' . ((int)$parts[1] + 543);
    $monthly_labels[] = $thLabel;
    $monthly_data[]   = $monthly_map[$key] ?? 0;
}

// ── Chart data prep ───────────────────────────────────────────────────────────
$colors = ['#7c3aed','#f59e0b','#ef4444','#3b82f6','#10b981','#ec4899','#8b5cf6','#14b8a6','#f97316','#6366f1'];

$chart_equip = [];
$equip_labels = [];
foreach ($top_equip as $i => $item) {
    $equip_labels[] = $item['name'];
    $chart_equip[]  = ['name' => $item['name'], 'y' => (int)$item['borrow_times'], 'color' => $colors[$i % 10]];
}

$chart_units = [];
$unit_labels  = [];
foreach ($top_units as $i => $item) {
    $unit_labels[]  = $item['unit'];
    $chart_units[]  = ['name' => $item['unit'], 'y' => (int)$item['borrow_times'], 'color' => $colors[$i % 10]];
}

// ── History Table with pagination/search/filter ───────────────────────────────
$per_page   = 20;
$page_num   = max(1, (int)($_GET['p'] ?? 1));
$search     = trim($_GET['q'] ?? '');
$filter_status = $_GET['status'] ?? 'all';
$offset     = ($page_num - 1) * $per_page;

$where_clauses = [];
$params = [];
if ($search !== '') {
    $where_clauses[] = "(COALESCE(b.borrower_name, u.username) LIKE :q OR u.username LIKE :q OR e.name LIKE :q OR COALESCE(b.borrower_unit,'') LIKE :q)";
    $params[':q'] = "%$search%";
}
if ($filter_status === 'borrowing') {
    $where_clauses[] = "b.status IN ('borrowing','borrowed')";
} elseif ($filter_status === 'waiting_return_approval') {
    $where_clauses[] = "b.status = 'waiting_return_approval'";
} elseif ($filter_status === 'returned') {
    $where_clauses[] = "b.status = 'returned'";
}
$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

try {
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) FROM borrowings b
        JOIN equipment e ON b.equipment_id = e.id
        JOIN users u ON b.user_id = u.id
        $where_sql
    ");
    $count_stmt->execute($params);
    $total_rows = (int)$count_stmt->fetchColumn();
} catch(Exception $e) { $total_rows = 0; }

$total_pages = max(1, ceil($total_rows / $per_page));

try {
    $hist_stmt = $pdo->prepare("
        SELECT b.id,
            COALESCE(b.borrower_name, u.username) AS borrower_name,
            u.username AS student_code,
            COALESCE(b.borrower_unit,'ไม่ระบุ') AS unit,
            e.name AS equipment_name,
            COALESCE(b.quantity,1) AS quantity,
            b.borrow_date,
            COALESCE(b.return_date_planned, b.return_date) AS planned_return,
            b.actual_return_date,
            b.status,
            b.created_at
        FROM borrowings b
        JOIN equipment e ON b.equipment_id = e.id
        JOIN users u ON b.user_id = u.id
        $where_sql
        ORDER BY b.created_at DESC
        LIMIT :lim OFFSET :off
    ");
    foreach ($params as $k => $v) $hist_stmt->bindValue($k, $v);
    $hist_stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
    $hist_stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $hist_stmt->execute();
    $history = $hist_stmt->fetchAll();
} catch(Exception $e) { $history = []; }

// Helper: format date as dd/mm/YYYY+543 (Buddhist Era)
function fmtDate($d) {
    if (!$d) return '-';
    $ts = strtotime($d);
    if (!$ts) return $d;
    return date('d/m/', $ts) . ((int)date('Y', $ts) + 543);
}

$statusLabel = ['pending'=>'กำลังยืม','borrowing'=>'กำลังยืม','borrowed'=>'กำลังยืม','waiting_return_approval'=>'รอการยืนยันคืน','returned'=>'คืนสำเร็จ'];
$statusClass = ['pending'=>'status-borrowing','borrowing'=>'status-borrowing','borrowed'=>'status-borrowing','waiting_return_approval'=>'status-waiting','returned'=>'status-returned'];
$statusIcon  = ['pending'=>'bi-clock','borrowing'=>'bi-clock','borrowed'=>'bi-clock','waiting_return_approval'=>'bi-hourglass-split','returned'=>'bi-check-circle-fill'];
?>

<!-- ==================== PAGE STYLES ==================== -->
<style>
.rpt-summary-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 12px; margin-bottom: 24px; }
@media(max-width:1200px){ .rpt-summary-grid { grid-template-columns: repeat(4,1fr); } }
@media(max-width:768px) { .rpt-summary-grid { grid-template-columns: repeat(2,1fr); } }

.rpt-stat-card {
    background: white;
    border-radius: 16px;
    padding: 16px 14px;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    box-shadow: 0 2px 16px rgba(109,40,217,0.08);
    transition: transform 0.2s, box-shadow 0.2s;
    text-align: center;
    border: 1.5px solid transparent;
}
.rpt-stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 28px rgba(109,40,217,0.16); }
.rpt-stat-icon { width: 44px; height: 44px; border-radius: 12px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; margin-bottom: 10px; }
.rpt-stat-value { font-size: 1.7rem; font-weight: 800; line-height: 1; }
.rpt-stat-label { font-size: 0.72rem; color: #6b7280; margin-top: 4px; font-weight: 500; }

.rank-badge-1 { background: #fbbf24; color: white; }
.rank-badge-2 { background: #9ca3af; color: white; }
.rank-badge-3 { background: #cd7c3a; color: white; }
.rank-badge-n { background: #ede9fe; color: #5b21b6; }
.rank-badge { width: 26px; height: 26px; border-radius: 50%; display:inline-flex; align-items:center; justify-content:center; font-size:0.75rem; font-weight:700; flex-shrink:0; }

.rpt-section-card { background:white; border-radius:16px; box-shadow:0 2px 16px rgba(109,40,217,0.07); margin-bottom:24px; overflow:hidden; }
.rpt-section-header { background: linear-gradient(135deg,#10B981,#059669); color:white; padding:14px 20px; display:flex; align-items:center; gap:8px; font-weight:700; font-size:0.95rem; }
.rpt-section-header i { font-size:1.1rem; opacity:0.9; }

.rpt-table th { font-size:0.78rem; font-weight:700; color:#4c1d95; background:#f5f0ff; padding:10px 14px; border:none; white-space:nowrap; }
.rpt-table td { font-size:0.83rem; vertical-align:middle; padding:9px 14px; border-color:#f0ebff; }
.rpt-table tbody tr:hover { background:#faf7ff; }

.chart-wrap { height: 320px; }

.status-pending   { background:#fef3c7; color:#92400e; }
.status-borrowing { background:#fef3c7; color:#d97706; }
.status-waiting   { background:#dbeafe; color:#1d4ed8; }
.status-returned  { background:#d1fae5; color:#059669; }
.status-pill { border-radius:20px; padding:3px 11px; font-size:0.73rem; font-weight:700; display:inline-flex; align-items:center; gap:4px; white-space:nowrap; }
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

.search-bar-wrap { background:#f9f7ff; border-radius:12px; padding:14px 18px; margin-bottom:16px; display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
.filter-tabs { display:flex; gap:6px; flex-wrap:wrap; }
.filter-tab { padding:5px 14px; border-radius:20px; font-size:0.8rem; font-weight:600; cursor:pointer; border:1.5px solid #ddd6fe; background:white; color:#6d28d9; transition:all 0.15s; }
.filter-tab.active { background:#10B981; color:white; border-color:#10B981; }
.filter-tab:hover:not(.active) { background:#ede9fe; }

.export-btn-wrap { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; justify-content:flex-end; }
.btn-export { padding:7px 16px; border-radius:10px; font-weight:600; font-size:0.83rem; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:all 0.2s; }
.btn-export-excel { background:#16a34a; color:white; }
.btn-export-excel:hover { background:#15803d; transform:translateY(-1px); }
.btn-export-csv   { background:#0284c7; color:white; }
.btn-export-csv:hover { background:#0369a1; transform:translateY(-1px); }
.btn-export-print { background:#7c3aed; color:white; }
.btn-export-print:hover { background:#6d28d9; transform:translateY(-1px); }

.pagination-wrap { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; padding:12px 16px; background:#f9f7ff; border-top:1px solid #f0ebff; }
.page-info { font-size:0.8rem; color:#6b7280; }
.page-btns { display:flex; gap:4px; }
.page-btn { width:32px; height:32px; border-radius:8px; border:1.5px solid #ddd6fe; display:inline-flex; align-items:center; justify-content:center; font-size:0.82rem; font-weight:600; cursor:pointer; background:white; color:#6d28d9; transition:all 0.15s; }
.page-btn:hover:not(.disabled):not(.active-pg) { background:#ede9fe; }
.page-btn.active-pg { background:#10B981; color:white; border-color:#10B981; }
.page-btn.disabled { opacity:0.4; cursor:not-allowed; }

@media print {
    .sidebar, .export-btn-wrap, .search-bar-wrap, .pagination-wrap, .filter-tabs { display:none !important; }
    #main-wrapper { margin-left: 0 !important; }
    .rpt-section-header { background: #10B981 !important; -webkit-print-color-adjust: exact; }
}
</style>

<!-- ==================== PAGE HEADER ==================== -->
<div class="page-header">
    <h4><i class="bi bi-bar-chart-line me-2"></i>รายงานการยืมครุภัณฑ์</h4>
    <p>Analytics Dashboard — ภาพรวมสถิติและประวัติการยืมครุภัณฑ์ทั้งหมด</p>
</div>

<!-- ==================== PART 1: SUMMARY CARDS ==================== -->
<div class="rpt-summary-grid">
    <?php
    $cards = [
        ['icon'=>'bi-clipboard-data','label'=>'รายการยืมทั้งหมด','value'=>$cnt_total,   'color'=>'#7c3aed','bg'=>'#ede9fe','border'=>'#c4b5fd'],
        ['icon'=>'bi-clock',         'label'=>'กำลังยืม',         'value'=>$cnt_borrowing,'color'=>'#d97706','bg'=>'#fef3c7','border'=>'#fde68a'],
        ['icon'=>'bi-hourglass-split','label'=>'รอยืนยันคืน',    'value'=>$cnt_waiting,  'color'=>'#1d4ed8','bg'=>'#dbeafe','border'=>'#bfdbfe'],
        ['icon'=>'bi-check-circle',  'label'=>'คืนสำเร็จ',        'value'=>$cnt_returned, 'color'=>'#059669','bg'=>'#d1fae5','border'=>'#a7f3d0'],
        ['icon'=>'bi-people',        'label'=>'ผู้ใช้งานทั้งหมด', 'value'=>$cnt_users,    'color'=>'#dc2626','bg'=>'#fee2e2','border'=>'#fecaca'],
        ['icon'=>'bi-tags',          'label'=>'หมวดหมู่ทั้งหมด',  'value'=>$cnt_cats,     'color'=>'#0369a1','bg'=>'#e0f2fe','border'=>'#bae6fd'],
        ['icon'=>'bi-laptop',        'label'=>'ครุภัณฑ์ทั้งหมด', 'value'=>$cnt_equip,    'color'=>'#7c3aed','bg'=>'#f5f0ff','border'=>'#ddd6fe'],
    ];
    foreach ($cards as $c):
    ?>
    <div class="rpt-stat-card" style="border-color:<?= $c['border'] ?>">
        <div class="rpt-stat-icon" style="background:<?= $c['bg'] ?>;color:<?= $c['color'] ?>">
            <i class="bi <?= $c['icon'] ?>"></i>
        </div>
        <div class="rpt-stat-value" style="color:<?= $c['color'] ?>"><?= number_format($c['value']) ?></div>
        <div class="rpt-stat-label"><?= $c['label'] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ==================== PART 2: TOP USERS ==================== -->
<div class="rpt-section-card">
    <div class="rpt-section-header"><i class="bi bi-person-check-fill"></i>ผู้ใช้งานที่ยืมบ่อยที่สุด</div>
    <div class="table-responsive">
        <table class="table rpt-table mb-0">
            <thead><tr>
                <th style="width:60px">อันดับ</th>
                <th>ชื่อ-นามสกุล</th>
                <th>รหัสนิสิต / Username</th>
                <th>หน่วยงาน</th>
                <th style="text-align:right">จำนวนครั้งที่ยืม</th>
            </tr></thead>
            <tbody>
            <?php if (empty($top_users)): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">ไม่มีข้อมูล</td></tr>
            <?php endif; ?>
            <?php foreach ($top_users as $i => $u): ?>
            <tr>
                <td>
                    <span class="rank-badge <?= $i===0?'rank-badge-1':($i===1?'rank-badge-2':($i===2?'rank-badge-3':'rank-badge-n')) ?>">
                        <?php if ($i < 3): ?>
                            <?= $i===0 ? '🥇' : ($i===1 ? '🥈' : '🥉') ?>
                        <?php else: ?>
                            <?= $i+1 ?>
                        <?php endif; ?>
                    </span>
                </td>
                <td>
                    <div class="fw-600"><?= htmlspecialchars($u['display_name']) ?></div>
                </td>
                <td><span class="text-muted" style="font-size:0.8rem"><?= htmlspecialchars($u['username']) ?></span></td>
                <td style="font-size:0.82rem"><?= htmlspecialchars($u['unit']) ?></td>
                <td style="text-align:right">
                    <span class="status-pill status-waiting"><?= (int)$u['borrow_times'] ?> ครั้ง</span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ==================== PART 3 + 4: TOP DEPARTMENTS + TOP EQUIPMENT ==================== -->
<div class="row g-3 mb-0">
    <div class="col-lg-6">
        <div class="rpt-section-card h-100" style="margin-bottom:0">
            <div class="rpt-section-header"><i class="bi bi-building"></i>หน่วยงานที่ยืมบ่อยที่สุด</div>
            <div class="table-responsive">
                <table class="table rpt-table mb-0">
                    <thead><tr>
                        <th style="width:56px">อันดับ</th>
                        <th>ชื่อหน่วยงาน</th>
                        <th style="text-align:right">จำนวนครั้ง</th>
                        <th style="text-align:right">จำนวนรายการ</th>
                    </tr></thead>
                    <tbody>
                    <?php if (empty($top_units)): ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted">ไม่มีข้อมูล</td></tr>
                    <?php endif; ?>
                    <?php foreach ($top_units as $i => $u): ?>
                    <tr>
                        <td>
                            <span class="rank-badge <?= $i===0?'rank-badge-1':($i===1?'rank-badge-2':($i===2?'rank-badge-3':'rank-badge-n')) ?>">
                                <?php if ($i < 3): ?><?= $i===0?'🥇':($i===1?'🥈':'🥉') ?><?php else: ?><?= $i+1 ?><?php endif; ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($u['unit']) ?></td>
                        <td style="text-align:right"><span class="status-pill status-waiting"><?= (int)$u['borrow_times'] ?> ครั้ง</span></td>
                        <td style="text-align:right"><?= (int)$u['total_qty'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="rpt-section-card h-100" style="margin-bottom:0">
            <div class="rpt-section-header"><i class="bi bi-laptop"></i>ครุภัณฑ์ที่ถูกยืมมากที่สุด</div>
            <div class="table-responsive">
                <table class="table rpt-table mb-0">
                    <thead><tr>
                        <th style="width:56px">อันดับ</th>
                        <th>ชื่อครุภัณฑ์</th>
                        <th>หมวดหมู่</th>
                        <th style="text-align:right">จำนวนครั้ง</th>
                    </tr></thead>
                    <tbody>
                    <?php if (empty($top_equip)): ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted">ไม่มีข้อมูล</td></tr>
                    <?php endif; ?>
                    <?php foreach ($top_equip as $i => $eq): ?>
                    <tr>
                        <td>
                            <span class="rank-badge <?= $i===0?'rank-badge-1':($i===1?'rank-badge-2':($i===2?'rank-badge-3':'rank-badge-n')) ?>">
                                <?php if ($i < 3): ?><?= $i===0?'🥇':($i===1?'🥈':'🥉') ?><?php else: ?><?= $i+1 ?><?php endif; ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($eq['name']) ?></td>
                        <td><span class="status-pill" style="background:#f3e8ff;color:#7c3aed"><?= htmlspecialchars($eq['category_name']) ?></span></td>
                        <td style="text-align:right"><span class="status-pill status-waiting"><?= (int)$eq['borrow_times'] ?> ครั้ง</span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div style="margin-bottom:24px"></div>



<!-- ==================== PART 6: HISTORY TABLE ==================== -->
<div class="rpt-section-card">
    <div class="rpt-section-header"><i class="bi bi-table"></i> ประวัติการยืมทั้งหมด</div>

    <!-- Search + Filter -->
    <div class="search-bar-wrap">
        <div class="flex-grow-1" style="min-width:220px">
            <div style="position:relative">
                <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#7c3aed;font-size:0.9rem"></i>
                <input type="text" id="histSearch" class="form-control" placeholder="ค้นหา ชื่อ, รหัส, หน่วยงาน, ครุภัณฑ์..."
                    value="<?= htmlspecialchars($search) ?>" style="padding-left:36px;border-radius:10px"
                    onkeydown="if(event.key==='Enter') applyFilter()">
            </div>
        </div>
        <div class="filter-tabs">
            <button class="filter-tab <?= $filter_status==='all'?'active':'' ?>" onclick="setFilter('all')">ทั้งหมด (<?= $cnt_total ?>)</button>
            <button class="filter-tab <?= $filter_status==='borrowing'?'active':'' ?>" onclick="setFilter('borrowing')">🟡 กำลังยืม (<?= $cnt_borrowing ?>)</button>
            <button class="filter-tab <?= $filter_status==='waiting_return_approval'?'active':'' ?>" onclick="setFilter('waiting_return_approval')">🔵 รอยืนยันคืน (<?= $cnt_waiting ?>)</button>
            <button class="filter-tab <?= $filter_status==='returned'?'active':'' ?>" onclick="setFilter('returned')">🟢 คืนสำเร็จ (<?= $cnt_returned ?>)</button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table rpt-table mb-0" id="histTable">
            <thead><tr>
                <th style="width:70px">รหัส</th>
                <th>ชื่อผู้ยืม</th>
                <th>รหัสนิสิต</th>
                <th>หน่วยงาน</th>
                <th>ครุภัณฑ์</th>
                <th style="text-align:center">จำนวน</th>
                <th style="white-space:nowrap">วันที่ยืม</th>
                <th style="white-space:nowrap">กำหนดคืน</th>
                <th style="white-space:nowrap">วันคืนจริง</th>
                <th>สถานะ</th>
                <th style="text-align:center">พิมพ์</th>
            </tr></thead>
            <tbody>
            <?php if (empty($history)): ?>
                <tr><td colspan="11" class="text-center py-4 text-muted">ไม่พบข้อมูล</td></tr>
            <?php endif; ?>
            <?php foreach ($history as $r): ?>
            <tr>
                <td><span class="text-muted" style="font-size:0.78rem">#<?= $r['id'] ?></span></td>
                <td><?= htmlspecialchars($r['borrower_name']) ?></td>
                <td style="font-size:0.8rem;color:#6b7280"><?= htmlspecialchars($r['student_code']) ?></td>
                <td style="font-size:0.8rem"><?= htmlspecialchars($r['unit']) ?></td>
                <td><?= htmlspecialchars($r['equipment_name']) ?></td>
                <td style="text-align:center"><?= (int)$r['quantity'] ?></td>
                <td style="white-space:nowrap"><?= $r['borrow_date'] ? fmtDate($r['borrow_date']) : '-' ?></td>
                <td style="white-space:nowrap"><?= $r['planned_return'] ? fmtDate($r['planned_return']) : '-' ?></td>
                <td style="white-space:nowrap"><?= $r['actual_return_date'] ? fmtDate($r['actual_return_date']) : '-' ?></td>
                <td>
                    <?php $st = $r['status'] ?? 'borrowing'; ?>
                    <span class="status-pill <?= $statusClass[$st] ?? 'status-borrowing' ?>">
                        <i class="bi <?= $statusIcon[$st] ?? 'bi-clock' ?>"></i>
                        <?= $statusLabel[$st] ?? $st ?>
                    </span>
                </td>
                <td style="text-align:center">
                    <a href="generate_borrow_pdf.php?borrowing_id=<?= $r['id'] ?>"
                       target="_blank"
                       class="btn btn-sm btn-print-doc"
                       title="พิมพ์แบบฟอร์มยืม #<?= $r['id'] ?>">
                        <i class="bi bi-printer-fill me-1"></i>พิมพ์
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="pagination-wrap">
        <div class="page-info">
            แสดง <?= $total_rows > 0 ? ($offset + 1) : 0 ?>–<?= min($offset + $per_page, $total_rows) ?>
            จาก <?= number_format($total_rows) ?> รายการ
        </div>
        <div class="page-btns">
            <button class="page-btn <?= $page_num<=1?'disabled':'' ?>" onclick="goPage(<?= $page_num-1 ?>)" <?= $page_num<=1?'disabled':'' ?>>
                <i class="bi bi-chevron-left"></i>
            </button>
            <?php
            $start = max(1, $page_num - 2);
            $end   = min($total_pages, $page_num + 2);
            for ($p = $start; $p <= $end; $p++):
            ?>
            <button class="page-btn <?= $p===$page_num?'active-pg':'' ?>" onclick="goPage(<?= $p ?>)"><?= $p ?></button>
            <?php endfor; ?>
            <button class="page-btn <?= $page_num>=$total_pages?'disabled':'' ?>" onclick="goPage(<?= $page_num+1 ?>)" <?= $page_num>=$total_pages?'disabled':'' ?>>
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    </div>
</div>

<!-- ==================== SCRIPTS ==================== -->
<script>
(function(){
    // ── Filter / Search / Pagination ──
    var currentFilter = '<?= $filter_status ?>';
    var currentSearch = <?= json_encode($search) ?>;
    var currentPage   = <?= $page_num ?>;

    window.setFilter = function(status) {
        currentFilter = status;
        currentPage   = 1;
        applyFilter();
    };

    window.goPage = function(p) {
        currentPage = p;
        applyFilter();
    };

    window.applyFilter = function() {
        var q   = document.getElementById('histSearch').value;
        var url = window.location.pathname + '?page=borrowing_dashboard&q=' + encodeURIComponent(q) + '&status=' + currentFilter + '&p=' + currentPage;
        if (window.spaNavigate) {
            // SPA mode: fetch partial with query params
            var partialUrl = 'partials/partial_borrowing_dashboard.php?q=' + encodeURIComponent(q) + '&status=' + currentFilter + '&p=' + currentPage;
            fetch(partialUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.text())
                .then(html => {
                    document.getElementById('main-content').innerHTML = html;
                    // Re-run inline scripts
                    document.getElementById('main-content').querySelectorAll('script').forEach(s => {
                        if (!s.src) { var ns = document.createElement('script'); ns.textContent = s.textContent; document.head.appendChild(ns); document.head.removeChild(ns); }
                    });
                    history.replaceState({ page: 'borrowing_dashboard' }, '', url);
                });
        } else {
            window.location.href = url;
        }
    };

    // ── Export functions ──
    window.exportCSV = function() {
        window.open('partials/partial_borrowing_dashboard.php?export=csv', '_blank');
    };

    window.exportExcel = function() {
        // Export as CSV with Excel hint
        var link = document.createElement('a');
        link.href = 'partials/partial_borrowing_dashboard.php?export=csv&excel=1';
        link.download = 'borrowing_report_' + new Date().toISOString().slice(0,10) + '.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };
})();
</script>
