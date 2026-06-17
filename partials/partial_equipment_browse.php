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
    ['label' => 'ครุภัณฑ์', 'page' => 'equipment_browse'],
]);
render_page_header('ครุภัณฑ์', 'รายการครุภัณฑ์ที่พร้อมให้ยืม');
?>

<div class="app-card">
    <div class="app-card__body">
        <?php if (empty($equipment)): ?>
            <?php render_empty_state('bi-laptop', 'ไม่มีครุภัณฑ์', 'ไม่มีรายการในขณะนี้'); ?>
        <?php else: ?>
        <div class="equip-grid">
            <?php foreach ($equipment as $item):
                $codes = $serials_by_equip[$item['id']] ?? [];
                $avail = count(array_filter($codes, fn($c) => ($c['status'] ?? 'available') === 'available'));
            ?>
            <article class="equip-card" data-searchable="<?= htmlspecialchars($item['name'] . ' ' . $item['category_name']) ?>">
                <img class="equip-card__img" src="Uploads/<?= htmlspecialchars($item['image'] ?: 'default.jpg') ?>" alt="">
                <div class="equip-card__body">
                    <h3 class="equip-card__title"><?= htmlspecialchars($item['name']) ?></h3>
                    <p class="text-secondary text-sm mb-2"><?= htmlspecialchars($item['category_name']) ?></p>
                    <div class="equip-card__meta">
                        <span class="equip-tag"><i class="bi bi-box"></i> คงเหลือ <?= $item['quantity'] ?></span>
                        <?php if ($avail > 0): ?>
                        <span class="equip-tag equip-tag--primary"><i class="bi bi-check-circle"></i> พร้อม <?= $avail ?></span>
                        <?php endif; ?>
                    </div>
                    <a href="?page=borrow" class="btn btn--primary btn--sm w-100 mt-2 spa-link" data-page="borrow">ขอยืม</a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
