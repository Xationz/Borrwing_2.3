<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

// Fetch equipment availability
$equipment = $pdo->query("SELECT e.*, c.name AS category_name FROM equipment e JOIN categories c ON e.category_id = c.id")->fetchAll();

// Most borrowed
$stmt = $pdo->prepare("
    SELECT e.name, SUM(b.quantity) as borrow_count
    FROM borrowings b JOIN equipment e ON b.equipment_id = e.id
    WHERE b.status = 'borrowed'
    GROUP BY e.id ORDER BY borrow_count DESC LIMIT 5
");
$stmt->execute();
$most_borrowed = $stmt->fetchAll();
$labels = array_column($most_borrowed, 'name');
$colors = ['#7c3aed','#f59e0b','#ef4444','#3b82f6','#10b981'];
$chart_data = [];
foreach ($most_borrowed as $i => $item) {
    $chart_data[] = ['name' => $item['name'], 'y' => (int)$item['borrow_count'], 'color' => $colors[$i % count($colors)]];
}
$current_page = 'borrowing_dashboard';
?>
<?php if (empty($_SERVER["HTTP_X_REQUESTED_WITH"])) { header("Location: spa_shell.php?page=borrowing_dashboard"); exit; } ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานการยืม - ระบบยืมครุภัณฑ์</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="content">
    <div class="page-header">
        <h4><i class="bi bi-bar-chart-line me-2"></i>รายงานการยืมครุภัณฑ์</h4>
        <p>ภาพรวมสถิติการยืมครุภัณฑ์ทั้งหมด</p>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-trophy"></i> ครุภัณฑ์ที่ถูกยืมมากที่สุด</div>
                <div class="card-body">
                    <div id="borrowingChart" style="height:360px;"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><i class="bi bi-box-seam"></i> สถานะครุภัณฑ์ทั้งหมด</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">ชื่อครุภัณฑ์</th>
                                <th>หมวดหมู่</th>
                                <th>คงเหลือ (ชิ้น)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($equipment as $item): ?>
                            <tr>
                                <td class="ps-3"><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                <td>
                                    <span class="badge rounded-pill" style="background:<?php echo $item['quantity'] > 5 ? '#d1fae5;color:#059669' : ($item['quantity'] > 0 ? '#fef3c7;color:#d97706' : '#fee2e2;color:#dc2626'); ?>">
                                        <?php echo $item['quantity']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.highcharts.com/highcharts.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Highcharts.chart('borrowingChart', {
        chart: { type: 'column', backgroundColor: '#ffffff', borderRadius: 10 },
        title: { text: 'ครุภัณฑ์ที่ถูกยืมมากที่สุด', style: { color: '#4c1d95', fontWeight: 'bold', fontFamily: 'Sarabun' } },
        xAxis: { categories: <?php echo json_encode($labels); ?>, labels: { style: { color: '#6d28d9', fontFamily: 'Sarabun' } } },
        yAxis: { min: 0, title: { text: 'จำนวนที่ยืม', style: { color: '#6d28d9', fontFamily: 'Sarabun' } }, allowDecimals: false },
        series: [{ name: 'ยืม', data: <?php echo json_encode($chart_data); ?>, colorByPoint: true }],
        plotOptions: { column: { dataLabels: { enabled: true, style: { color: '#4c1d95', fontWeight: 'bold' } }, borderRadius: 6 } },
        tooltip: { backgroundColor: '#6d28d9', style: { color: '#fff', fontFamily: 'Sarabun' } },
        legend: { enabled: false },
        credits: { enabled: false }
    });
});
</script>
</body>
</html>