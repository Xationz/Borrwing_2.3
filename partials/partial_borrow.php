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

$data = load_borrow_equipment_data($pdo);
extract($data);

render_breadcrumb([
    ['label' => 'Dashboard', 'page' => 'dashboard'],
    ['label' => 'ขอยืมครุภัณฑ์', 'page' => 'borrow'],
]);
render_page_header(
    'ขอยืมครุภัณฑ์',
    'เลือกครุภัณฑ์และกรอกแบบฟอร์มขอยืม',
    '<button type="button" class="btn btn--secondary btn--sm" id="multiSelectToggleBtn"><i class="bi bi-ui-checks"></i> <span id="multiSelectBtnLabel">เลือกหลายรายการ</span></button>'
);
?>

<div class="app-card" id="equipmentCard">
    <div class="multi-select-bar" id="multiSelectBar">
        <span class="font-medium text-sm"><i class="bi bi-check2-square"></i> เลือกแล้ว <strong id="multiSelectCount">0</strong> รายการ</span>
        <div id="multiSelectChips" class="d-flex flex-wrap gap-1 flex-grow-1"></div>
        <button type="button" class="btn btn--primary btn--sm" id="multiSelectBorrowBtn" disabled>
            <i class="bi bi-clipboard-plus"></i> ยืมรายการที่เลือก
        </button>
    </div>

    <div class="app-card__body">
        <?php if (empty($equipment)): ?>
            <?php render_empty_state('bi-inbox', 'ไม่มีครุภัณฑ์พร้อมให้ยืม', 'กรุณาติดต่อเจ้าหน้าที่'); ?>
        <?php else: ?>
        <div class="equip-grid" id="equipmentGrid">
            <?php foreach ($equipment as $item):
                $codes = $serials_by_equip[$item['id']] ?? [];
            ?>
            <article class="equip-card equip-card--selectable"
                     data-id="<?= $item['id'] ?>"
                     data-name="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>"
                     data-max="<?= $item['quantity'] ?>"
                     data-cat="<?= htmlspecialchars($item['category_name'], ENT_QUOTES) ?>"
                     data-searchable="<?= htmlspecialchars($item['name'] . ' ' . $item['category_name']) ?>">
                <div class="equip-card__check"><i class="bi bi-check-lg"></i></div>
                <img class="equip-card__img" src="Uploads/<?= htmlspecialchars($item['image'] ?: 'default.jpg') ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                <div class="equip-card__body">
                    <h3 class="equip-card__title"><?= htmlspecialchars($item['name']) ?></h3>
                    <div class="equip-card__meta">
                        <?php if (!empty($codes)): ?>
                        <span class="equip-tag equip-tag--primary"><i class="bi bi-upc-scan"></i> <?= htmlspecialchars($codes[0]['code']) ?></span>
                        <?php if (count($codes) > 1): ?>
                        <button type="button" class="equip-tag btn-show-codes" data-id="<?= $item['id'] ?>" data-name="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>">+<?= count($codes)-1 ?></button>
                        <?php endif; ?>
                        <?php endif; ?>
                        <span class="equip-tag"><i class="bi bi-box"></i> คงเหลือ <?= $item['quantity'] ?></span>
                    </div>
                    <button type="button" class="btn btn--primary btn--sm w-100 btn-single-borrow btn-open-wizard"
                            data-id="<?= $item['id'] ?>"
                            data-name="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>"
                            data-max="<?= $item['quantity'] ?>">
                        <i class="bi bi-clipboard-plus"></i> ยืม
                    </button>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script type="application/json" id="borrow-data"><?= json_encode([
    'serials' => $serials_by_equip,
    'busy' => $busy_by_serial,
    'today' => date('Y-m-d'),
], JSON_UNESCAPED_UNICODE) ?></script>

<?php include dirname(__DIR__) . '/includes/views/borrow_wizard.php'; ?>

<?php if (!empty($swal_msg)): ?>
<script data-page-init="swal"><?= $swal_msg ?></script>
<?php endif; ?>

<script data-page-init="borrow">if(typeof initBorrowWizard==='function')initBorrowWizard();</script>
