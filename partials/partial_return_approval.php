<?php
// partial_return_approval.php — Admin: ยืนยันการคืนครุภัณฑ์
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        http_response_code(403);
        echo '<div class="alert alert-danger m-3">ไม่มีสิทธิ์เข้าถึง</div>';
    } else {
        header('Location: ../login.php');
    }
    exit;
}

// Auto-migrate columns for return approval workflow
$migrate_cols = [
    'status'               => "ALTER TABLE borrowings MODIFY COLUMN status ENUM('borrowing','waiting_return_approval','returned','borrowed','pending') DEFAULT 'borrowing'",
    'returned_request_at'  => "ALTER TABLE borrowings ADD COLUMN returned_request_at datetime DEFAULT NULL",
    'approved_return_at'   => "ALTER TABLE borrowings ADD COLUMN approved_return_at datetime DEFAULT NULL",
    'approved_by_admin'    => "ALTER TABLE borrowings ADD COLUMN approved_by_admin int(11) DEFAULT NULL",
    'actual_return_date'   => "ALTER TABLE borrowings ADD COLUMN actual_return_date date DEFAULT NULL",
];
foreach ($migrate_cols as $col => $sql) {
    try {
        if ($col === 'status') {
            $pdo->exec($sql);
        } else {
            $check = $pdo->query("SHOW COLUMNS FROM borrowings LIKE '$col'");
            if ($check->rowCount() === 0) $pdo->exec($sql);
        }
    } catch (Exception $e) {}
}
// migrate old active statuses -> borrowing
try {
    $pdo->exec("UPDATE borrowings SET status='borrowing' WHERE status IN ('borrowed','pending')");
} catch(Exception $e){}

$swal_msg = '';

// Admin confirms return
if (isset($_POST['confirm_return'])) {
    $borrowing_id = (int)($_POST['borrowing_id'] ?? 0);
    $pdo->beginTransaction();
    try {
        // Fetch borrowing
        $stmt = $pdo->prepare("SELECT * FROM borrowings WHERE id=? AND status='waiting_return_approval'");
        $stmt->execute([$borrowing_id]);
        $row = $stmt->fetch();
        if (!$row) throw new Exception("ไม่พบรายการหรือสถานะไม่ถูกต้อง");

        // Update borrowing: confirmed return
        $pdo->prepare("
            UPDATE borrowings SET
                status='returned',
                actual_return_date=CURDATE(),
                approved_return_at=NOW(),
                approved_by_admin=?
            WHERE id=?
        ")->execute([$_SESSION['user_id'], $borrowing_id]);

        // Add stock back
        $qty = $row['borrow_quantity'] ?? $row['quantity'];
        $pdo->prepare("UPDATE equipment SET quantity = quantity + ? WHERE id=?")->execute([$qty, $row['equipment_id']]);
        $pdo->prepare("
            UPDATE equipment_serials es
            JOIN borrow_serials bs ON bs.serial_id = es.id
            SET es.status = 'available'
            WHERE bs.borrowing_id = ?
        ")->execute([$borrowing_id]);

        $pdo->commit();
        $swal_msg = "Swal.fire({title:'ยืนยันสำเร็จ!',text:'บันทึกการคืนครุภัณฑ์เรียบร้อยแล้ว',icon:'success',confirmButtonColor:'#10B981'});";
    } catch (Exception $e) {
        $pdo->rollBack();
        $swal_msg = "Swal.fire('เกิดข้อผิดพลาด','".addslashes($e->getMessage())."','error');";
    }
}

// Fetch all borrowings (all statuses) with user/equipment info
$borrowings = $pdo->query("
    SELECT b.*,
        e.name AS equip_name,
        u.username,
        u.id AS uid,
        COALESCE(b.borrower_name, u.username) AS display_name,
        COALESCE(b.borrower_unit, '-') AS display_unit,
        COALESCE(b.borrow_quantity, b.quantity, 1) AS display_qty,
        adm.username AS admin_approver
    FROM borrowings b
    JOIN equipment e ON b.equipment_id = e.id
    JOIN users u ON b.user_id = u.id
    LEFT JOIN users adm ON adm.id = b.approved_by_admin
    ORDER BY
        CASE b.status
            WHEN 'waiting_return_approval' THEN 1
            WHEN 'borrowing' THEN 2
            ELSE 3
        END,
        b.created_at DESC
")->fetchAll();

// Count by status
$cnt_borrowing = 0; $cnt_waiting = 0; $cnt_returned = 0;
foreach ($borrowings as $b) {
    if ($b['status'] === 'borrowing' || $b['status'] === 'borrowed') $cnt_borrowing++;
    elseif ($b['status'] === 'waiting_return_approval') $cnt_waiting++;
    elseif ($b['status'] === 'returned') $cnt_returned++;
}
?>

<?php require_once dirname(__DIR__) . '/includes/components/ui.php'; ?>
<?php render_breadcrumb([
    ['label' => 'Dashboard', 'page' => 'dashboard'],
    ['label' => 'จัดการคำขอ', 'page' => 'return_approval'],
]); ?>
<?php render_page_header('จัดการคำขอ', 'ตรวจสอบและยืนยันรายการยืม-คืนครุภัณฑ์'); ?>

<div class="app-grid app-grid--12 u-mb-6">
    <div class="app-col-4"><?php render_kpi_card('กำลังยืม', $cnt_borrowing, 'bi-clock', 'warning'); ?></div>
    <div class="app-col-4"><?php render_kpi_card('รอการยืนยันคืน', $cnt_waiting, 'bi-hourglass-split', 'info'); ?></div>
    <div class="app-col-4"><?php render_kpi_card('คืนสำเร็จ', $cnt_returned, 'bi-check-circle', 'success'); ?></div>
</div>

<div class="app-card">
    <div class="app-card__header">
        <h2 class="app-card__title"><i class="bi bi-list-check"></i> รายการยืม-คืนทั้งหมด</h2>
        <div class="filter-tabs">
            <button type="button" class="filter-tab active" data-filter="all">ทั้งหมด</button>
            <button type="button" class="filter-tab" data-filter="waiting_return_approval">รอยืนยัน</button>
            <button type="button" class="filter-tab" data-filter="borrowing">กำลังยืม</button>
            <button type="button" class="filter-tab" data-filter="returned">คืนแล้ว</button>
        </div>
    </div>
    <div class="app-card__body app-card__body--flush">
        <div class="app-table-wrap">
            <table class="app-table" id="returnTable">
                <thead>
                    <tr>
                        <th class="ps-3">#ID</th>
                        <th>ชื่อผู้ยืม</th>
                        <th>หน่วยงาน</th>
                        <th>ชื่อครุภัณฑ์</th>
                        <th>จำนวน</th>
                        <th>วันที่ยืม</th>
                        <th>กำหนดคืน</th>
                        <th>วันที่แจ้งคืน</th>
                        <th>สถานะ</th>
                        <th class="text-center">พิมพ์เอกสาร</th>
                        <th class="text-center">ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($borrowings)): ?>
                    <tr><td colspan="11" class="text-center py-4 text-muted">ไม่มีรายการ</td></tr>
                <?php endif; ?>
                <?php foreach ($borrowings as $b):
                    $st = $b['status'];
                    if ($st === 'borrowed') $st = 'borrowing';
                    $badgeClass = match($st) {
                        'borrowing'              => 'status-borrowing',
                        'waiting_return_approval'=> 'status-waiting',
                        'returned'               => 'status-returned',
                        default                  => 'status-borrowing'
                    };
                    $badgeLabel = match($st) {
                        'borrowing'              => '<i class="bi bi-clock"></i> กำลังยืม',
                        'waiting_return_approval'=> '<i class="bi bi-hourglass-split"></i> รอการยืนยันคืน',
                        'returned'               => '<i class="bi bi-check-circle"></i> คืนสำเร็จ',
                        default                  => 'กำลังยืม'
                    };
                ?>
                <tr class="borrow-row filter-row" data-status="<?= htmlspecialchars($st) ?>" data-searchable="<?= htmlspecialchars($b['display_name'] . ' ' . $b['equip_name']) ?>">
                    <td class="ps-3"><span class="badge bg-secondary">#<?= $b['id'] ?></span></td>
                    <td>
                        <div class="fw-600"><?= htmlspecialchars($b['display_name']) ?></div>
                        <div class="text-muted" style="font-size:0.75rem"><?= htmlspecialchars($b['username']) ?></div>
                    </td>
                    <td><?= htmlspecialchars($b['display_unit']) ?></td>
                    <td><?= htmlspecialchars($b['equip_name']) ?></td>
                    <td><span class="badge" style="background:#ede9fe;color:#5b21b6"><?= (int)$b['display_qty'] ?></span></td>
                    <td><?= $b['borrow_date'] ? date('d/m/Y', strtotime($b['borrow_date'])) : '-' ?></td>
                    <td>
                        <?php
                        $planned = $b['return_date_planned'] ?? null;
                        $overdue = $planned && $st !== 'returned' && strtotime($planned) < strtotime('today');
                        echo $planned ? '<span'.($overdue?' style="color:#dc2626;font-weight:700"':'').'>'.date('d/m/Y',strtotime($planned)).'</span>'.($overdue?' <span class="badge bg-danger" style="font-size:0.65rem">เกินกำหนด</span>':'') : '-';
                        ?>
                    </td>
                    <td><?= $b['returned_request_at'] ? date('d/m/Y H:i', strtotime($b['returned_request_at'])) : '-' ?></td>
                    <td><span class="status-badge <?= $badgeClass ?>"><?= $badgeLabel ?></span></td>
                    <td class="text-center">
                        <a href="generate_borrow_pdf.php?borrowing_id=<?= $b['id'] ?>"
                           target="_blank"
                           class="btn btn-sm btn-print-doc"
                           title="พิมพ์/ดาวน์โหลดแบบฟอร์มการยืม #<?= $b['id'] ?>">
                            <i class="bi bi-printer-fill me-1"></i>พิมพ์
                        </a>
                    </td>
                    <td class="text-center">
                        <?php if ($st === 'waiting_return_approval'): ?>
                        <form method="POST" style="display:inline" onsubmit="return confirmReturn(this)">
                            <input type="hidden" name="confirm_return" value="1">
                            <input type="hidden" name="borrowing_id" value="<?= $b['id'] ?>">
                            <button type="submit" class="btn btn--success btn--sm">
                                <i class="bi bi-patch-check-fill me-1"></i>ยืนยันการคืน
                            </button>
                        </form>
                        <?php elseif ($st === 'returned'): ?>
                        <span class="text-muted" style="font-size:0.8rem">
                            <i class="bi bi-check-all"></i>
                            <?= $b['actual_return_date'] ? date('d/m/Y', strtotime($b['actual_return_date'])) : '' ?>
                            <?= $b['admin_approver'] ? '<br><span style="font-size:0.72rem">โดย: '.htmlspecialchars($b['admin_approver']).'</span>' : '' ?>
                        </span>
                        <?php else: ?>
                        <span class="text-muted" style="font-size:0.8rem">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($swal_msg)): ?>
<script data-page-init="swal"><?= $swal_msg ?></script>
<?php endif; ?>
<script data-page-init="filters">initFilterTabs('.filter-tabs', '.filter-row', 'data-status'); if(typeof initReturnApproval==='function')initReturnApproval();</script>
