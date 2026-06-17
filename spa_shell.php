<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$role     = $_SESSION['role'] ?? 'user';
$username = $_SESSION['username'] ?? '';

$allowed_admin = ['dashboard','equipment','calendar','return_approval','borrowing_dashboard','settings','admins','categorie'];
$allowed_user  = ['dashboard','borrow_history'];

$page = $_GET['page'] ?? 'dashboard';

if ($role === 'admin' && !in_array($page, $allowed_admin)) $page = 'dashboard';
if ($role !== 'admin' && !in_array($page, $allowed_user)) $page = 'dashboard';

$navItems = require __DIR__ . '/includes/nav_config.php';
$menu = $navItems[$role] ?? $navItems['user'];

// Deduplicate admin menu (return_approval appears twice with different labels - show unique pages)
$seen = [];
$menuFiltered = [];
foreach ($menu as $item) {
    if (isset($seen[$item['page']])) continue;
    $seen[$item['page']] = true;
    $menuFiltered[] = $item;
}
if ($role === 'admin') {
    $menuFiltered = $navItems['admin'];
}

$initials = mb_strtoupper(mb_substr($username, 0, 1));
$roleLabel = $role === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งาน';

// Notification count for admin
$notifCount = 0;
if ($role === 'admin') {
    try {
        $notifCount = (int)$pdo->query("SELECT COUNT(*) FROM borrowings WHERE status='waiting_return_approval'")->fetchColumn();
    } catch (Exception $e) {}
}

$partialFile = __DIR__ . "/partials/partial_{$page}.php";
if (!file_exists($partialFile)) {
    $page = 'dashboard';
    $partialFile = __DIR__ . '/partials/partial_dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบยืมครุภัณฑ์</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    <link href="assets/css/design-system.css" rel="stylesheet">
    <link href="assets/css/layout.css" rel="stylesheet">
    <link href="assets/css/components.css" rel="stylesheet">
    <link href="assets/css/pages/borrow-wizard.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <div class="app-sidebar-overlay" id="sidebar-overlay"></div>

    <aside class="app-sidebar" id="app-sidebar" aria-label="เมนูหลัก">
        <div class="app-sidebar__brand">
            <div class="app-sidebar__logo"><i class="bi bi-box-seam"></i></div>
            <div class="app-sidebar__brand-text">
                <h1>EquipFlow</h1>
                <span>ระบบยืมครุภัณฑ์</span>
            </div>
        </div>

        <nav class="app-sidebar__nav">
            <div class="app-sidebar__section-label">เมนู<?= $role === 'admin' ? 'ผู้ดูแล' : 'ผู้ใช้งาน' ?></div>
            <?php
            $activePages = array_count_values(array_column($menuFiltered, 'page'));
            foreach ($menuFiltered as $item):
                $isActive = ($page === $item['page']);
            ?>
            <a href="?page=<?= htmlspecialchars($item['page']) ?>"
               class="app-sidebar__link spa-link<?= $isActive ? ' active' : '' ?>"
               data-page="<?= htmlspecialchars($item['page']) ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <?= htmlspecialchars($item['label']) ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="app-sidebar__footer">
            <a href="logout.php" class="app-sidebar__link app-sidebar__link--logout">
                <i class="bi bi-box-arrow-right"></i> ออกจากระบบ
            </a>
        </div>
    </aside>

    <div class="app-main">
        <header class="app-navbar">
            <button type="button" class="app-navbar__toggle" id="sidebar-toggle" aria-label="เปิดเมนู">
                <i class="bi bi-list"></i>
            </button>

            <div class="app-navbar__search">
                <i class="bi bi-search"></i>
                <input type="search" id="global-search" placeholder="ค้นหาครุภัณฑ์, รายการ, ผู้ยืม..." aria-label="ค้นหา">
            </div>

            <div class="app-navbar__actions">
                <?php if ($role === 'admin'): ?>
                <button type="button" class="app-navbar__icon-btn spa-link" data-page="return_approval" title="การแจ้งเตือน" aria-label="การแจ้งเตือน">
                    <i class="bi bi-bell"></i>
                    <?php if ($notifCount > 0): ?><span class="badge-dot"></span><?php endif; ?>
                </button>
                <?php endif; ?>

                <div class="app-navbar__profile">
                    <div class="app-navbar__avatar"><?= htmlspecialchars($initials) ?></div>
                    <div class="app-navbar__profile-info">
                        <span class="app-navbar__profile-name"><?= htmlspecialchars($username) ?></span>
                        <span class="app-navbar__profile-role"><?= htmlspecialchars($roleLabel) ?></span>
                    </div>
                </div>
            </div>
        </header>

        <main class="app-content">
            <div class="app-container" id="main-content">
                <?php include $partialFile; ?>
            </div>
        </main>
    </div>
</div>

<div class="app-loading" id="app-loading" aria-live="polite" aria-busy="true">
    <div class="app-loading__spinner"></div>
</div>

<div id="app-config"
     data-role="<?= htmlspecialchars($role) ?>"
     data-username="<?= htmlspecialchars($username) ?>"
     data-page="<?= htmlspecialchars($page) ?>"
     hidden></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="assets/js/toast.js"></script>
<script src="assets/js/components.js"></script>
<script src="assets/js/borrow-wizard.js"></script>
<script src="assets/js/return-approval.js"></script>
<script src="assets/js/dashboard.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
