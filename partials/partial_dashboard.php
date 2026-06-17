<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/components/ui.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo '<div class="empty-state"><p class="empty-state__title">ไม่มีสิทธิ์เข้าถึง</p></div>';
    exit;
}

$role = $_SESSION['role'] ?? 'user';
$username = $_SESSION['username'] ?? '';

if ($role === 'admin') {
    $total_equipment = (int)$pdo->query("SELECT COUNT(*) FROM equipment")->fetchColumn();
    $total_borrowing = (int)$pdo->query("SELECT COUNT(*) FROM borrowings WHERE status IN ('borrowing','borrowed','pending')")->fetchColumn();
    $total_available = (int)$pdo->query("SELECT COALESCE(SUM(quantity),0) FROM equipment")->fetchColumn() - $total_borrowing;
    if ($total_available < 0) $total_available = 0;
    $total_pending   = (int)$pdo->query("SELECT COUNT(*) FROM borrowings WHERE status='waiting_return_approval'")->fetchColumn();

    $recent = $pdo->query("
        SELECT b.*, e.name AS equip_name, COALESCE(b.borrower_name, u.username) AS display_name
        FROM borrowings b
        JOIN equipment e ON b.equipment_id = e.id
        JOIN users u ON b.user_id = u.id
        ORDER BY b.created_at DESC LIMIT 8
    ")->fetchAll();

    $equip_status = $pdo->query("
        SELECT e.name,
            COALESCE(SUM(CASE WHEN b.status IN ('borrowing','borrowed','pending') THEN COALESCE(b.borrow_quantity,b.quantity,1) ELSE 0 END),0) AS borrowed,
            e.quantity AS total
        FROM equipment e
        LEFT JOIN borrowings b ON b.equipment_id = e.id
        GROUP BY e.id ORDER BY e.name LIMIT 6
    ")->fetchAll();

    $chart_monthly = $pdo->query("
        SELECT DATE_FORMAT(borrow_date,'%Y-%m') AS ym, COUNT(*) AS cnt
        FROM borrowings WHERE borrow_date IS NOT NULL
        GROUP BY ym ORDER BY ym DESC LIMIT 6
    ")->fetchAll();
    $chart_monthly = array_reverse($chart_monthly);

    $activities = $pdo->query("
        SELECT b.*, e.name AS equip_name, COALESCE(b.borrower_name, u.username) AS display_name
        FROM borrowings b
        JOIN equipment e ON b.equipment_id = e.id
        JOIN users u ON b.user_id = u.id
        ORDER BY b.created_at DESC LIMIT 6
    ")->fetchAll();
} else {
    require_once dirname(__DIR__) . '/includes/borrow_handler.php';
    $uid = (int)$_SESSION['user_id'];
    $total_equipment = (int)$pdo->query("SELECT COUNT(*) FROM equipment WHERE quantity > 0")->fetchColumn();
    $total_borrowing = (int)$pdo->query("SELECT COUNT(*) FROM borrowings WHERE user_id=$uid AND status IN ('borrowing','borrowed','pending')")->fetchColumn();
    $total_available = max(0, (int)$pdo->query("SELECT COALESCE(SUM(quantity),0) FROM equipment")->fetchColumn() - (int)$pdo->query("SELECT COUNT(*) FROM borrowings WHERE status IN ('borrowing','borrowed','pending')")->fetchColumn());
    $total_pending   = (int)$pdo->query("SELECT COUNT(*) FROM borrowings WHERE user_id=$uid AND status='waiting_return_approval'")->fetchColumn();

    $recent = $pdo->query("
        SELECT b.*, e.name AS equip_name, COALESCE(b.borrow_quantity,b.quantity,1) AS display_qty
        FROM borrowings b JOIN equipment e ON b.equipment_id = e.id
        WHERE b.user_id = $uid ORDER BY b.created_at DESC LIMIT 8
    ")->fetchAll();

    $equip_status = $pdo->query("
        SELECT e.name, e.quantity AS total,
            (SELECT COUNT(*) FROM borrowings b WHERE b.equipment_id=e.id AND b.user_id=$uid AND b.status NOT IN ('returned')) AS borrowed
        FROM equipment e WHERE e.quantity > 0 ORDER BY e.name LIMIT 6
    ")->fetchAll();

    $chart_monthly = $pdo->query("
        SELECT DATE_FORMAT(borrow_date,'%Y-%m') AS ym, COUNT(*) AS cnt
        FROM borrowings WHERE user_id=$uid AND borrow_date IS NOT NULL
        GROUP BY ym ORDER BY ym DESC LIMIT 6
    ")->fetchAll();
    $chart_monthly = array_reverse($chart_monthly);

    $activities = $recent;
}

render_breadcrumb([['label' => 'Dashboard', 'page' => 'dashboard']]);
render_page_header(
    'Dashboard',
    'ยินดีต้อนรับ, ' . $username . ' — ภาพรวมระบบยืมครุภัณฑ์'
);
?>

<div class="app-grid app-grid--12 u-mb-6">
    <div class="app-col-3"><?php render_kpi_card('ครุภัณฑ์ทั้งหมด', $total_equipment, 'bi-laptop', 'primary'); ?></div>
    <div class="app-col-3"><?php render_kpi_card('กำลังถูกยืม', $total_borrowing, 'bi-arrow-repeat', 'warning'); ?></div>
    <div class="app-col-3"><?php render_kpi_card('พร้อมใช้งาน', $total_available, 'bi-check2-circle', 'success'); ?></div>
    <div class="app-col-3"><?php render_kpi_card('รออนุมัติ', $total_pending, 'bi-hourglass-split', 'info'); ?></div>
</div>

<div class="app-grid app-grid--12 u-mb-6">
    <div class="app-col-8">
        <div class="app-card">
            <div class="app-card__header">
                <h2 class="app-card__title"><i class="bi bi-clock-history"></i> คำขอล่าสุด</h2>
                <?php if ($role === 'user'): ?>
                <a href="?page=borrow_history" class="btn btn--ghost btn--sm spa-link" data-page="borrow_history">ดูทั้งหมด</a>
                <?php else: ?>
                <a href="?page=return_approval" class="btn btn--ghost btn--sm spa-link" data-page="return_approval">ดูทั้งหมด</a>
                <?php endif; ?>
            </div>
            <div class="app-card__body app-card__body--flush">
                <div class="app-table-wrap">
                    <table class="app-table">
                        <thead>
                            <tr>
                                <th>ผู้ยืม</th>
                                <th>ครุภัณฑ์</th>
                                <th>วันที่ยืม</th>
                                <th>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($recent)): ?>
                            <tr><td colspan="4"><?php render_empty_state('bi-inbox', 'ยังไม่มีรายการ', 'เมื่อมีการยืมครุภัณฑ์จะแสดงที่นี่'); ?></td></tr>
                        <?php else: foreach ($recent as $b): ?>
                            <tr data-searchable="<?= htmlspecialchars(($b['display_name'] ?? $b['borrower_name'] ?? '') . ' ' . $b['equip_name']) ?>">
                                <td class="font-medium"><?= htmlspecialchars($b['display_name'] ?? $b['borrower_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($b['equip_name']) ?></td>
                                <td><?= htmlspecialchars($b['borrow_date'] ?? '-') ?></td>
                                <td><?php render_status_badge(normalize_borrow_status($b['status'])); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="app-col-4">
        <div class="app-card" style="height:100%;">
            <div class="app-card__header">
                <h2 class="app-card__title"><i class="bi bi-pie-chart"></i> สถานะครุภัณฑ์</h2>
            </div>
            <div class="app-card__body">
                <?php if (empty($equip_status)): ?>
                    <?php render_empty_state('bi-laptop', 'ไม่มีข้อมูล'); ?>
                <?php else: foreach ($equip_status as $eq):
                    $borrowed = (int)($eq['borrowed'] ?? 0);
                    $total = max(1, (int)($eq['total'] ?? 1));
                    $pct = min(100, round($borrowed / $total * 100));
                ?>
                <div style="margin-bottom: var(--space-4);">
                    <div style="display:flex;justify-content:space-between;font-size:var(--text-sm);margin-bottom:var(--space-1);">
                        <span class="font-medium"><?= htmlspecialchars($eq['name']) ?></span>
                        <span class="text-secondary"><?= $borrowed ?>/<?= $total ?></span>
                    </div>
                    <div style="height:6px;background:var(--color-bg);border-radius:var(--radius-full);overflow:hidden;">
                        <div style="height:100%;width:<?= $pct ?>%;background:var(--color-primary);border-radius:var(--radius-full);"></div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="app-grid app-grid--12">
    <div class="app-col-8">
        <div class="app-card">
            <div class="app-card__header">
                <h2 class="app-card__title"><i class="bi bi-bar-chart-line"></i> แนวโน้มการยืมรายเดือน</h2>
            </div>
            <div class="app-card__body">
                <div id="dashboardChart" class="chart-container"
                     data-labels='<?= json_encode(array_column($chart_monthly, 'ym'), JSON_UNESCAPED_UNICODE) ?>'
                     data-values='<?= json_encode(array_map('intval', array_column($chart_monthly, 'cnt'))) ?>'></div>
            </div>
        </div>
    </div>
    <div class="app-col-4">
        <div class="app-card" style="height:100%;">
            <div class="app-card__header">
                <h2 class="app-card__title"><i class="bi bi-activity"></i> กิจกรรมล่าสุด</h2>
            </div>
            <div class="app-card__body">
                <ul class="activity-timeline">
                <?php if (empty($activities)): ?>
                    <li class="text-secondary text-sm">ยังไม่มีกิจกรรม</li>
                <?php else: foreach ($activities as $a):
                    $st = normalize_borrow_status($a['status']);
                    $icon = $st === 'returned' ? 'bi-check-lg' : ($st === 'waiting_return_approval' ? 'bi-hourglass' : 'bi-arrow-repeat');
                ?>
                    <li class="activity-timeline__item">
                        <div class="activity-timeline__dot"><i class="bi <?= $icon ?>"></i></div>
                        <div class="activity-timeline__content">
                            <div class="activity-timeline__text">
                                <strong><?= htmlspecialchars($a['display_name'] ?? $a['borrower_name'] ?? '-') ?></strong>
                                ยืม <?= htmlspecialchars($a['equip_name']) ?>
                            </div>
                            <div class="activity-timeline__time"><?= htmlspecialchars($a['borrow_date'] ?? '-') ?></div>
                        </div>
                    </li>
                <?php endforeach; endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script data-page-init="dashboard">if(typeof initDashboardCharts==='function')initDashboardCharts();</script>
