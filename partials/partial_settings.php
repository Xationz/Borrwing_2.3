<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/components/ui.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo '<div class="empty-state"><p class="empty-state__title">ไม่มีสิทธิ์เข้าถึง</p></div>';
    exit;
}

$admin_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$cat_count   = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();

render_breadcrumb([
    ['label' => 'Dashboard', 'page' => 'dashboard'],
    ['label' => 'ตั้งค่า', 'page' => 'settings'],
]);
render_page_header('ตั้งค่าระบบ', 'จัดการการตั้งค่าและข้อมูลพื้นฐานของระบบ');
?>

<div class="app-grid app-grid--12">
    <div class="app-col-6">
        <div class="app-card">
            <div class="app-card__body">
                <div class="kpi-card kpi-card--flat">
                    <div class="kpi-card__icon kpi-card__icon--primary"><i class="bi bi-people"></i></div>
                    <div class="kpi-card__content">
                        <div class="kpi-card__label">จัดการแอดมิน</div>
                        <div class="kpi-card__value"><?= $admin_count ?></div>
                        <p class="form-helper">ผู้ดูแลระบบที่มีสิทธิ์จัดการ</p>
                        <a href="?page=admins" class="btn btn--primary btn--sm mt-3 spa-link" data-page="admins">เปิดการจัดการ</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="app-col-6">
        <div class="app-card">
            <div class="app-card__body">
                <div class="kpi-card kpi-card--flat">
                    <div class="kpi-card__icon kpi-card__icon--info"><i class="bi bi-tags"></i></div>
                    <div class="kpi-card__content">
                        <div class="kpi-card__label">หมวดหมู่ครุภัณฑ์</div>
                        <div class="kpi-card__value"><?= $cat_count ?></div>
                        <p class="form-helper">หมวดหมู่สำหรับจัดกลุ่มครุภัณฑ์</p>
                        <a href="?page=categorie" class="btn btn--primary btn--sm mt-3 spa-link" data-page="categorie">เปิดการจัดการ</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
