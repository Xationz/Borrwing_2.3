<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/components/ui.php';
require_once dirname(__DIR__) . '/includes/borrow_handler.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    http_response_code(403);
    echo '<div class="empty-state"><p class="empty-state__title">ไม่มีสิทธิ์เข้าถึง</p></div>';
    exit;
}

$borrowings = load_user_borrowings($pdo, (int)$_SESSION['user_id']);

render_breadcrumb([
    ['label' => 'Dashboard', 'page' => 'dashboard'],
    ['label' => 'ประวัติการยืม', 'page' => 'borrow_history'],
]);
render_page_header('ประวัติการยืม', 'รายการยืม-คืนครุภัณฑ์ของคุณ');
?>

<div class="app-card">
    <div class="app-card__header">
        <h2 class="app-card__title"><i class="bi bi-list-ul"></i> รายการทั้งหมด</h2>
        <a href="?page=dashboard" class="btn btn--primary btn--sm spa-link" data-page="dashboard"><i class="bi bi-plus-lg"></i> ขอยืมใหม่</a>
    </div>
    <div class="app-card__body app-card__body--flush">
        <div class="app-table-wrap">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ครุภัณฑ์</th>
                        <th>จำนวน</th>
                        <th>วันที่ยืม</th>
                        <th>กำหนดคืน</th>
                        <th>สถานะ</th>
                        <th>การดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($borrowings)): ?>
                    <tr><td colspan="7"><?php render_empty_state('bi-clock-history', 'ยังไม่มีประวัติการยืม', 'เริ่มต้นด้วยการขอยืมครุภัณฑ์', '<a href="?page=dashboard" class="btn btn--primary spa-link" data-page="dashboard">ขอยืมครุภัณฑ์</a>'); ?></td></tr>
                <?php else: foreach ($borrowings as $b):
                    $st = normalize_borrow_status($b['status']);
                ?>
                    <tr data-searchable="<?= htmlspecialchars(($b['borrower_name'] ?? '') . ' ' . $b['equip_name']) ?>" data-status="<?= $st ?>">
                        <td><span class="text-secondary">#<?= $b['id'] ?></span></td>
                        <td class="font-medium"><?= htmlspecialchars($b['equip_name']) ?></td>
                        <td><?= (int)$b['display_qty'] ?></td>
                        <td><?= htmlspecialchars($b['borrow_date'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($b['return_date_planned'] ?? '-') ?></td>
                        <td><?php render_status_badge($st); ?></td>
                        <td>
                            <a href="generate_borrow_pdf.php?borrowing_id=<?= $b['id'] ?>" target="_blank" class="btn btn-print btn--sm"><i class="bi bi-printer"></i> พิมพ์</a>
                            <?php if ($st === 'borrowing'): ?>
                            <button type="button" class="btn btn--ghost btn--sm spa-action-return text-danger" data-id="<?= $b['id'] ?>"><i class="bi bi-arrow-return-left"></i> แจ้งคืน</button>
                            <?php elseif ($st === 'waiting_return_approval'): ?>
                            <span class="text-secondary text-sm"><i class="bi bi-hourglass"></i> รอตรวจสอบ</span>
                            <?php else: ?>
                            <span class="text-success text-sm"><i class="bi bi-check-all"></i> เสร็จสิ้น</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($swal_msg)): ?>
<script data-page-init="swal"><?= $swal_msg ?></script>
<?php endif; ?>
<script data-page-init="return">if(typeof initReturnActions==='function')initReturnActions('borrow_history');</script>
