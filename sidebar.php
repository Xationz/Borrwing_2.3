<?php
// sidebar.php — shared layout include for all pages
// Usage: include 'sidebar.php'; at the top of <body>
// Requires: session_start() and $current_page variable to be set before including
// $current_page options: 'user_dashboard', 'borrowing_dashboard', 'admin_dashboard', 'admins', 'equipment', 'categorie', 'calendar'
if (!isset($current_page)) $current_page = '';
$role = $_SESSION['role'] ?? 'user';
$username = $_SESSION['username'] ?? '';
?>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
* { font-family: 'Sarabun', sans-serif; }
body { background: linear-gradient(135deg, #f0ebff 0%, #e8e0ff 50%, #ede9fe 100%); min-height: 100vh; }

/* ===== SIDEBAR ===== */
.sidebar {
    background: linear-gradient(180deg, #4c1d95 0%, #6d28d9 40%, #7c3aed 100%);
    color: white;
    height: 100vh;
    position: fixed;
    width: 240px;
    top: 0; left: 0;
    box-shadow: 4px 0 24px rgba(76,29,149,0.25);
    z-index: 1000;
    display: flex;
    flex-direction: column;
}
.sidebar-brand {
    padding: 20px 18px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.12);
    flex-shrink: 0;
}
.sidebar-brand .brand-icon {
    width: 40px; height: 40px;
    background: rgba(255,255,255,0.18);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
    margin-bottom: 10px;
}
.sidebar-brand h5 { font-weight: 700; font-size: 0.95rem; margin: 0; letter-spacing: 0.3px; }
.sidebar-brand p  { font-size: 0.75rem; opacity: 0.7; margin: 4px 0 0; }
.sidebar-nav { padding: 12px 12px; flex: 1; overflow-y: auto; }
.nav-section-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    opacity: 0.5;
    padding: 10px 8px 4px;
}
.sidebar .nav-link {
    color: rgba(255,255,255,0.78);
    border-radius: 10px;
    padding: 9px 12px;
    margin: 1px 0;
    transition: all 0.18s;
    font-weight: 500;
    font-size: 0.88rem;
    display: flex;
    align-items: center;
    gap: 9px;
}
.sidebar .nav-link i { font-size: 1rem; opacity: 0.85; }
.sidebar .nav-link:hover {
    background: rgba(255,255,255,0.15);
    color: #fff;
    transform: translateX(3px);
}
.sidebar .nav-link.active {
    background: rgba(255,255,255,0.22);
    color: #fff;
    font-weight: 600;
}
.sidebar-footer {
    padding: 12px;
    border-top: 1px solid rgba(255,255,255,0.1);
    flex-shrink: 0;
}
.sidebar-footer .nav-link { color: rgba(255,255,255,0.6); font-size: 0.83rem; }
.sidebar-footer .nav-link:hover { background: rgba(220,38,38,0.18); color: #fca5a5; }

/* ===== CONTENT ===== */
.content { margin-left: 240px; padding: 28px; }
.page-header { margin-bottom: 24px; }
.page-header h4 { font-weight: 700; color: #3b0f9e; margin: 0; }
.page-header p  { color: #7c3aed; font-size: 0.85rem; margin: 4px 0 0; opacity: 0.75; }

/* ===== CARDS ===== */
.card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 2px 20px rgba(109,40,217,0.08);
    overflow: hidden;
    margin-bottom: 20px;
}
.card-header {
    background: linear-gradient(135deg, #6d28d9, #7c3aed);
    color: white;
    border: none;
    padding: 14px 20px;
    font-weight: 600;
    font-size: 0.95rem;
}
.card-header .bi { margin-right: 8px; }
.card-body { padding: 20px; }

/* ===== BUTTONS ===== */
.btn-primary {
    background: linear-gradient(135deg, #6d28d9, #7c3aed);
    border: none;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.2s;
}
.btn-primary:hover { background: linear-gradient(135deg, #5b21b6, #6d28d9); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(109,40,217,0.3); }
.btn-info    { background: #ede9fe; color: #6d28d9; border: none; border-radius: 8px; font-weight: 600; }
.btn-info:hover { background: #ddd6fe; color: #5b21b6; }
.btn-danger  { border-radius: 8px; font-weight: 600; }
.btn-secondary { border-radius: 8px; font-weight: 600; }

/* ===== FORMS ===== */
.form-label { font-weight: 600; color: #4c1d95; font-size: 0.85rem; margin-bottom: 4px; }
.form-control, .form-select {
    border: 1.5px solid #ddd6fe;
    border-radius: 10px;
    padding: 9px 13px;
    font-size: 0.88rem;
    transition: all 0.2s;
    background: #fdfcff;
}
.form-control:focus, .form-select:focus {
    border-color: #7c3aed;
    box-shadow: 0 0 0 3px rgba(124,58,237,0.1);
    background: white;
}

/* ===== TABLE ===== */
.table th {
    font-size: 0.8rem;
    font-weight: 700;
    color: #4c1d95;
    background: #f5f0ff;
    padding: 10px 14px;
    border: none;
}
.table td {
    font-size: 0.85rem;
    vertical-align: middle;
    padding: 10px 14px;
    border-color: #f0ebff;
}
.table tbody tr:hover { background: #faf7ff; }
.table-responsive { border-radius: 10px; overflow: hidden; }

/* ===== MODAL ===== */
.modal-content  { border: none; border-radius: 16px; overflow: hidden; }
.modal-header   { background: linear-gradient(135deg, #6d28d9, #7c3aed); color: white; border: none; padding: 16px 20px; }
.modal-header .btn-close { filter: invert(1); }
.modal-title    { font-weight: 700; font-size: 1rem; }
.modal-footer   { border: none; background: #f9f7ff; padding: 14px 20px; }
.modal-body     { padding: 20px; }

/* ===== STATUS BADGES ===== */
.badge-borrowed { background: #fef3c7; color: #d97706; border-radius: 20px; padding: 3px 12px; font-size: 0.75rem; font-weight: 700; }
.badge-returned { background: #d1fae5; color: #059669; border-radius: 20px; padding: 3px 12px; font-size: 0.75rem; font-weight: 700; }

@media (max-width: 768px) {
    .sidebar { width: 100%; height: auto; position: relative; }
    .content { margin-left: 0; padding: 16px; }
}
</style>

<!-- ===== SIDEBAR HTML ===== -->
<div class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="bi bi-laptop"></i></div>
        <h5>ระบบยืมครุภัณฑ์</h5>
        <p><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($username); ?></p>
    </div>

    <div class="sidebar-nav">
        <?php if ($role === 'admin'): ?>
        <div class="nav-section-label">เมนูผู้ดูแลระบบ</div>
        <a class="nav-link <?= $current_page === 'admins' ? 'active' : '' ?>" href="spa_shell.php?page=admins">
            <i class="bi bi-people-fill"></i> จัดการแอดมิน
        </a>
        <a class="nav-link <?= $current_page === 'categorie' ? 'active' : '' ?>" href="spa_shell.php?page=categorie">
            <i class="bi bi-tags"></i> หมวดหมู่
        </a>
        <a class="nav-link <?= $current_page === 'equipment' ? 'active' : '' ?>" href="spa_shell.php?page=equipment">
            <i class="bi bi-laptop"></i> จัดการครุภัณฑ์
        </a>
        <a class="nav-link <?= $current_page === 'return_approval' ? 'active' : '' ?>" href="spa_shell.php?page=return_approval">
            <i class="bi bi-patch-check"></i> ยืนยันการคืน
        </a>
        <a class="nav-link <?= $current_page === 'calendar' ? 'active' : '' ?>" href="spa_shell.php?page=calendar">
            <i class="bi bi-calendar3"></i> ปฏิทินการยืม
        </a>
        <a class="nav-link <?= $current_page === 'borrowing_dashboard' ? 'active' : '' ?>" href="spa_shell.php?page=borrowing_dashboard">
            <i class="bi bi-bar-chart-line"></i> รายงานการยืม
        </a>
        <?php else: ?>
        <div class="nav-section-label">เมนูผู้ใช้งาน</div>
        <a class="nav-link <?= $current_page === 'user_dashboard' ? 'active' : '' ?>" href="user_dashboard.php">
            <i class="bi bi-grid-1x2"></i> หน้าหลัก
        </a>
        <a class="nav-link <?= $current_page === 'borrowing_dashboard' ? 'active' : '' ?>" href="borrowing_dashboard.php">
            <i class="bi bi-bar-chart-line"></i> รายงานการยืม
        </a>
        <?php endif; ?>
    </div>

    <div class="sidebar-footer">
        <a class="nav-link" href="logout.php">
            <i class="bi bi-box-arrow-right"></i> ออกจากระบบ
        </a>
    </div>
</div>
