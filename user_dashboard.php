<?php
session_start();
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'org_units.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: login.php');
    exit;
}

// Auto-migrate: Add new columns if they don't exist
$migrate_columns = [
    "borrower_name"             => "ALTER TABLE borrowings ADD COLUMN borrower_name varchar(255) DEFAULT NULL",
    "borrower_position"         => "ALTER TABLE borrowings ADD COLUMN borrower_position varchar(100) DEFAULT NULL",
    "borrower_position_other"   => "ALTER TABLE borrowings ADD COLUMN borrower_position_other varchar(255) DEFAULT NULL",
    "borrower_unit"             => "ALTER TABLE borrowings ADD COLUMN borrower_unit varchar(255) DEFAULT NULL",
    "borrower_phone"            => "ALTER TABLE borrowings ADD COLUMN borrower_phone varchar(50) DEFAULT NULL",
    "borrower_email"            => "ALTER TABLE borrowings ADD COLUMN borrower_email varchar(100) DEFAULT NULL",
    "equipment_type"            => "ALTER TABLE borrowings ADD COLUMN equipment_type varchar(100) DEFAULT NULL",
    "equipment_type_other"      => "ALTER TABLE borrowings ADD COLUMN equipment_type_other varchar(255) DEFAULT NULL",
    "borrow_quantity"           => "ALTER TABLE borrowings ADD COLUMN borrow_quantity int(11) DEFAULT 1",
    "purpose"                   => "ALTER TABLE borrowings ADD COLUMN purpose varchar(100) DEFAULT NULL",
    "return_date_planned"       => "ALTER TABLE borrowings ADD COLUMN return_date_planned date DEFAULT NULL",
    "it_install"                => "ALTER TABLE borrowings ADD COLUMN it_install tinyint(1) DEFAULT 0",
];
foreach ($migrate_columns as $col => $sql) {
    try {
        $check = $pdo->query("SHOW COLUMNS FROM borrowings LIKE '$col'");
        if ($check->rowCount() === 0) {
            $pdo->exec($sql);
        }
    } catch (Exception $e) { /* ignore */ }
}

// Borrow equipment (detailed form)
if (isset($_POST['borrow_equipment'])) {
    $borrower_name           = trim($_POST['borrower_name'] ?? '');
    $borrower_position       = $_POST['borrower_position'] ?? '';
    $borrower_position_other = trim($_POST['borrower_position_other'] ?? '');
    $borrower_unit           = trim($_POST['borrower_unit'] ?? '');
    $borrower_phone          = trim($_POST['borrower_phone'] ?? '');
    $borrower_email          = trim($_POST['borrower_email'] ?? '');
    $equipment_type          = $_POST['equipment_type'] ?? '';
    $equipment_type_other    = trim($_POST['equipment_type_other'] ?? '');
    $borrow_quantity         = (int)($_POST['borrow_quantity'] ?? 1);
    $purpose                 = $_POST['purpose'] ?? '';
    $borrow_date             = $_POST['borrow_date'] ?? '';
    $return_date_planned     = $_POST['return_date_planned'] ?? '';
    $it_install              = isset($_POST['it_install']) && $_POST['it_install'] === '1' ? 1 : 0;
    $equipment_id            = (int)($_POST['equipment_id'] ?? 0);

    $errors = [];
    if (empty($borrower_name)) $errors[] = 'กรุณากรอกชื่อ-นามสกุลผู้ยืม';
    if (empty($borrower_position)) $errors[] = 'กรุณาเลือกตำแหน่งผู้ยืม';
    if ($borrower_position === 'other' && empty($borrower_position_other)) $errors[] = 'กรุณาระบุตำแหน่งอื่น ๆ';
    if (empty($borrower_unit)) $errors[] = 'กรุณากรอกหน่วยสังกัด';
    if (empty($borrower_phone)) $errors[] = 'กรุณากรอกเบอร์ภายใน';
    if (empty($borrower_email) || !filter_var($borrower_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'กรุณากรอกอีเมลให้ถูกต้อง';
    if (empty($equipment_type)) $errors[] = 'กรุณาเลือกประเภทครุภัณฑ์ที่ยืม';
    if ($equipment_type === 'other' && empty($equipment_type_other)) $errors[] = 'กรุณาระบุครุภัณฑ์อื่น ๆ';
    if ($borrow_quantity < 1) $errors[] = 'กรุณากรอกจำนวนที่ยืม';
    if (empty($purpose)) $errors[] = 'กรุณาเลือกเหตุผลในการยืม';
    if (empty($borrow_date)) $errors[] = 'กรุณาเลือกวันที่ยืม';
    if (empty($return_date_planned)) $errors[] = 'กรุณาเลือกวันที่คืน';
    if (!empty($borrow_date) && !empty($return_date_planned) && strtotime($return_date_planned) < strtotime($borrow_date)) $errors[] = 'วันที่คืนต้องไม่น้อยกว่าวันที่ยืม';
    if ($equipment_id <= 0) $errors[] = 'ข้อมูลครุภัณฑ์ไม่ถูกต้อง';

    if (!empty($errors)) {
        $swal_msg = "Swal.fire('ข้อมูลไม่ครบถ้วน', '" . addslashes(implode('<br>', $errors)) . "', 'error');";
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT quantity FROM equipment WHERE id = ?");
            $stmt->execute([$equipment_id]);
            $available = $stmt->fetchColumn();

            if ($borrow_quantity > $available) {
                throw new Exception("จำนวนที่ยืมเกินจำนวนที่มีอยู่ (มีอยู่: $available)");
            }

            $stmt = $pdo->prepare("
                INSERT INTO borrowings 
                    (user_id, equipment_id, quantity, borrow_date, return_date_planned,
                     borrower_name, borrower_position, borrower_position_other,
                     borrower_unit, borrower_phone, borrower_email, equipment_type, equipment_type_other,
                     borrow_quantity, purpose, it_install)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'], $equipment_id, $borrow_quantity, $borrow_date, $return_date_planned,
                $borrower_name, $borrower_position, $borrower_position_other,
                $borrower_unit, $borrower_phone, $borrower_email, $equipment_type, $equipment_type_other,
                $borrow_quantity, $purpose, $it_install
            ]);

            $stmt = $pdo->prepare("UPDATE equipment SET quantity = quantity - ? WHERE id = ?");
            $stmt->execute([$borrow_quantity, $equipment_id]);

            $new_borrowing_id = $pdo->lastInsertId();
            $pdo->commit();

            // Send data to Google Sheet via Apps Script Web App
            try {
                $sheetData = [
                    'borrower_name'          => $borrower_name,
                    'borrower_position'      => positionLabel($borrower_position, $borrower_position_other),
                    'borrower_unit'          => $borrower_unit,
                    'borrower_phone'         => $borrower_phone,
                    'borrower_email'         => $borrower_email,
                    'equipment_type'         => positionLabel($equipment_type, $equipment_type_other),
                    'borrow_quantity'        => (string)$borrow_quantity,
                    'purpose'                => $purpose,
                    'borrow_date'            => $borrow_date,
                    'return_date_planned'    => $return_date_planned,
                    'borrow_days'            => calculateDays($borrow_date, $return_date_planned),
                    'it_install'             => ($it_install ? 'Yes' : 'No'),
                    'location'               => '',
                    'asset_code'             => '',
                ];

                $ch = curl_init(GOOGLE_APPS_SCRIPT_WEB_APP_URL);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($sheetData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $response = curl_exec($ch);
                curl_close($ch);
            } catch (Exception $e) {
                // Log error but don't fail the borrow operation
                error_log('Google Sheet sync failed: ' . $e->getMessage());
            }

            // Redirect to PDF preview after successful submission
            header('Location: generate_borrow_pdf.php?borrowing_id=' . $new_borrowing_id);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $swal_msg = "Swal.fire('เกิดข้อผิดพลาด', '" . addslashes($e->getMessage()) . "', 'error');";
        }
    }
}

// Return equipment
if (isset($_GET['return_borrowing'])) {
    $borrowing_id = $_GET['return_borrowing'];
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE borrowings SET status = 'returned', return_date = CURDATE() WHERE id = ? AND user_id = ? AND status = 'borrowed'");
        $stmt->execute([$borrowing_id, $_SESSION['user_id']]);
        if ($stmt->rowCount() === 0) throw new Exception("ไม่พบรายการหรือคืนแล้ว");

        $stmt = $pdo->prepare("SELECT equipment_id, quantity FROM borrowings WHERE id = ?");
        $stmt->execute([$borrowing_id]);
        $borrowing = $stmt->fetch();
        if ($borrowing) {
            $stmt = $pdo->prepare("UPDATE equipment SET quantity = quantity + ? WHERE id = ?");
            $stmt->execute([$borrowing['quantity'], $borrowing['equipment_id']]);
        }
        $pdo->commit();
        $swal_msg = "Swal.fire('สำเร็จ', 'คืนครุภัณฑ์เรียบร้อยแล้ว', 'success');";
    } catch (Exception $e) {
        $pdo->rollBack();
        $swal_msg = "Swal.fire('เกิดข้อผิดพลาด', '" . addslashes($e->getMessage()) . "', 'error');";
    }
}

// Fetch equipment and borrowings
$equipment = $pdo->query("SELECT e.*, c.name AS category_name FROM equipment e JOIN categories c ON e.category_id = c.id WHERE e.quantity > 0")->fetchAll();
$borrowings = $pdo->query("SELECT b.*, e.name AS equip_name FROM borrowings b JOIN equipment e ON b.equipment_id = e.id WHERE b.user_id = " . $_SESSION['user_id'] . " ORDER BY b.created_at DESC")->fetchAll();
?>
<?php if (empty($_SERVER["HTTP_X_REQUESTED_WITH"])) { header("Location: spa_shell.php?page=user_dashboard"); exit; } ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - ระบบยืมครุภัณฑ์</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Sarabun', sans-serif; }
        body { background: linear-gradient(135deg, #f0ebff 0%, #e8e0ff 50%, #ede9fe 100%); min-height: 100vh; }

        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, #5b21b6 0%, #722ff9 60%, #8b5cf6 100%);
            color: white;
            height: 100vh;
            position: fixed;
            width: 260px;
            box-shadow: 4px 0 20px rgba(114,47,249,0.3);
            z-index: 1000;
        }
        .sidebar-brand {
            padding: 24px 20px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.15);
        }
        .sidebar-brand h5 { font-weight: 700; font-size: 1.1rem; margin: 0; }
        .sidebar-brand p { font-size: 0.8rem; opacity: 0.75; margin: 4px 0 0; }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.85);
            border-radius: 10px;
            padding: 10px 14px;
            margin: 2px 0;
            transition: all 0.2s;
            font-weight: 500;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: #fff;
            transform: translateX(4px);
        }
        .sidebar .nav-link i { margin-right: 8px; }

        /* Content */
        .content { margin-left: 260px; padding: 28px; }

        /* Cards */
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(114,47,249,0.08);
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #722ff9, #8b5cf6);
            color: white;
            border: none;
            padding: 16px 20px;
            font-weight: 600;
        }
        .card-header .bi { margin-right: 8px; }

        /* Equipment Cards */
        .equip-card {
            border: 1px solid #ede9fe;
            border-radius: 14px;
            transition: all 0.25s;
            overflow: hidden;
            background: white;
        }
        .equip-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(114,47,249,0.18);
            border-color: #722ff9;
        }
        .equip-card img {
            width: 100%;
            height: 130px;
            object-fit: cover;
        }
        .equip-card .card-body { padding: 12px; }
        .equip-card .card-title { font-size: 0.9rem; font-weight: 700; color: #3b0f9e; margin-bottom: 4px; }
        .equip-card .badge-qty {
            background: #ede9fe;
            color: #5b21b6;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #722ff9, #8b5cf6);
            border: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-primary:hover { background: linear-gradient(135deg, #5b21b6, #722ff9); transform: translateY(-1px); }
        .btn-borrow {
            width: 100%;
            padding: 7px;
            font-size: 0.85rem;
            border-radius: 8px;
            margin-top: 8px;
        }
        .btn-outline-danger { border-radius: 8px; font-size: 0.8rem; }

        /* Modal */
        .modal-content { border: none; border-radius: 20px; overflow: hidden; }
        .modal-header {
            background: linear-gradient(135deg, #722ff9, #8b5cf6);
            color: white;
            border: none;
            padding: 18px 24px;
        }
        .modal-header .btn-close { filter: invert(1); }
        .modal-title { font-weight: 700; font-size: 1.1rem; }
        .modal-body { padding: 24px; max-height: 75vh; overflow-y: auto; }
        .modal-footer { border: none; padding: 16px 24px; background: #f9f7ff; }

        /* Form Styles */
        .form-label { font-weight: 600; color: #4c1d95; font-size: 0.88rem; margin-bottom: 5px; }
        .form-label .required { color: #dc2626; margin-left: 3px; }
        .form-control, .form-select {
            border: 1.5px solid #d8d0f7;
            border-radius: 10px;
            padding: 9px 14px;
            font-size: 0.9rem;
            transition: all 0.2s;
            background: #fdfcff;
        }
        .form-control:focus, .form-select:focus {
            border-color: #722ff9;
            box-shadow: 0 0 0 3px rgba(114,47,249,0.12);
            background: white;
        }
        .form-section {
            background: linear-gradient(135deg, #f5f0ff, #faf7ff);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid #ede9fe;
        }
        .form-section-title {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #722ff9;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .day-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #722ff9, #8b5cf6);
            color: white;
            border-radius: 20px;
            padding: 5px 14px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-top: 8px;
        }

        /* Radio/Check custom */
        .radio-group { display: flex; flex-direction: column; gap: 6px; }
        .radio-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            border: 1.5px solid #d8d0f7;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.18s;
            background: white;
        }
        .radio-option:hover { border-color: #722ff9; background: #f5f0ff; }
        .radio-option input[type="radio"] { accent-color: #722ff9; width: 16px; height: 16px; }
        .radio-option.selected { border-color: #722ff9; background: #f0ebff; }
        .radio-option label { cursor: pointer; margin: 0; font-size: 0.88rem; }

        /* IT install toggle */
        .toggle-group { display: flex; gap: 10px; }
        .toggle-btn {
            flex: 1;
            padding: 10px;
            border: 2px solid #d8d0f7;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 600;
            font-size: 0.88rem;
            background: white;
            color: #6b7280;
        }
        .toggle-btn.active-yes { border-color: #16a34a; background: #f0fdf4; color: #16a34a; }
        .toggle-btn.active-no  { border-color: #dc2626; background: #fef2f2; color: #dc2626; }

        /* Status badge */
        .status-badge {
            border-radius: 20px;
            padding: 3px 12px;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .status-borrowed { background: #fef3c7; color: #d97706; }
        .status-returned { background: #d1fae5; color: #059669; }

        /* Table */
        .table th { font-size: 0.82rem; font-weight: 700; color: #4c1d95; background: #f5f0ff; }
        .table td { font-size: 0.85rem; vertical-align: middle; }

        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .content { margin-left: 0; padding: 16px; }
        }
    /* Tom Select placeholder */
    #borrower_unit-ts-control input::placeholder,
    .ts-wrapper .ts-control .placeholder { color: #6c757d; opacity: 1; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <h5><i class="bi bi-laptop"></i> ระบบยืมครุภัณฑ์</h5>
            <p><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </div>
        <ul class="nav flex-column p-3">
            <li class="nav-item">
                <a class="nav-link active" href="user_dashboard.php"><i class="bi bi-grid-1x2"></i> หน้าหลัก</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="borrowing_dashboard.php"><i class="bi bi-bar-chart-line"></i> ประวัติการยืม</a>
            </li>
            <li class="nav-item mt-4">
                <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
            </li>
        </ul>
    </div>

    <!-- Content -->
    <div class="content">
        <div class="d-flex align-items-center mb-4">
            <div>
                <h4 class="mb-0 fw-bold" style="color:#3b0f9e">User Dashboard</h4>
                <p class="text-muted mb-0" style="font-size:0.88rem">ยินดีต้อนรับ, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
            </div>
        </div>

        <!-- Equipment Grid -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-laptop"></i> ครุภัณฑ์ที่พร้อมให้ยืม
            </div>
            <div class="card-body p-3">
                <div class="row g-3">
                    <?php foreach ($equipment as $item): ?>
                    <div class="col-6 col-md-3 col-lg-2">
                        <div class="equip-card h-100">
                            <img src="Uploads/<?php echo htmlspecialchars($item['image'] ?: 'default.jpg'); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                            <div class="card-body d-flex flex-column">
                                <h6 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h6>
                                <div class="text-muted" style="font-size:0.75rem"><?php echo htmlspecialchars($item['category_name']); ?></div>
                                <div class="mt-1"><span class="badge-qty"><i class="bi bi-box"></i> คงเหลือ <?php echo $item['quantity']; ?></span></div>
                                <button
                                    class="btn btn-primary btn-borrow mt-auto"
                                    data-bs-toggle="modal"
                                    data-bs-target="#borrowDetailModal"
                                    data-id="<?php echo $item['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                    data-max="<?php echo $item['quantity']; ?>">
                                    <i class="bi bi-clipboard-plus"></i> ยืม
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($equipment)): ?>
                    <div class="col-12 text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-2"></i>
                        <p class="mt-2">ไม่มีครุภัณฑ์ที่พร้อมให้ยืมในขณะนี้</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Borrowing History -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clock-history"></i> ประวัติการยืม
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">#</th>
                                <th>ชื่อผู้ยืม</th>
                                <th>ครุภัณฑ์</th>
                                <th>ประเภท</th>
                                <th>จำนวน</th>
                                <th>วันที่ยืม</th>
                                <th>กำหนดคืน</th>
                                <th>วันคืนจริง</th>
                                <th>สถานะ</th>
                                <th>IT ติดตั้ง</th>
                                <th>การดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($borrowings as $b): ?>
                            <tr>
                                <td class="ps-3"><?php echo $b['id']; ?></td>
                                <td><?php echo htmlspecialchars($b['borrower_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($b['equip_name']); ?></td>
                                <td>
                                    <?php
                                    $etype = $b['equipment_type'] ?? '';
                                    $labels = [
                                        'notebook' => 'Notebook',
                                        'pc'       => 'คอมพิวเตอร์ตั้งโต๊ะ (PC)',
                                        'aio'      => 'คอมพิวเตอร์ตั้งโต๊ะ (AIO)',
                                        'printer'  => 'เครื่องพิมพ์ (Printer)',
                                        'other'    => 'อื่น ๆ: ' . htmlspecialchars($b['equipment_type_other'] ?? ''),
                                    ];
                                    echo htmlspecialchars($labels[$etype] ?? $etype ?: '-');
                                    ?>
                                </td>
                                <td><?php echo $b['quantity']; ?></td>
                                <td><?php echo $b['borrow_date'] ?? '-'; ?></td>
                                <td><?php echo $b['return_date_planned'] ?? '-'; ?></td>
                                <td><?php echo $b['return_date'] ?? '-'; ?></td>
                                <td>
                                    <?php if ($b['status'] === 'borrowed'): ?>
                                        <span class="status-badge status-borrowed"><i class="bi bi-clock"></i> กำลังยืม</span>
                                    <?php else: ?>
                                        <span class="status-badge status-returned"><i class="bi bi-check-circle"></i> คืนแล้ว</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($b['it_install'])): ?>
                                        <?php if ($b['it_install']): ?>
                                            <span class="badge bg-success">ต้องการ</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">ไม่ต้องการ</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($b['status'] === 'borrowed'): ?>
                                        <a href="generate_borrow_pdf.php?borrowing_id=<?php echo $b['id']; ?>"
                                           target="_blank"
                                           class="btn btn-sm btn-outline-primary me-1"
                                           title="พิมพ์แบบฟอร์มยืม">
                                           <i class="bi bi-printer"></i> พิมพ์
                                        </a>
                                        <a href="?return_borrowing=<?php echo $b['id']; ?>"
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('ยืนยันการคืนครุภัณฑ์?')">
                                           <i class="bi bi-arrow-return-left"></i> คืน
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($borrowings)): ?>
                            <tr><td colspan="11" class="text-center py-4 text-muted">ยังไม่มีประวัติการยืม</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== BORROW DETAIL MODAL ===== -->
    <div class="modal fade" id="borrowDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-clipboard-plus me-2"></i>แบบฟอร์มขอยืมครุภัณฑ์</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="borrowDetailForm">
                    <input type="hidden" name="borrow_equipment" value="1">
                    <input type="hidden" name="equipment_id" id="modal_equipment_id">

                    <div class="modal-body">
                        <!-- Equipment Info Banner -->
                        <div class="alert alert-info d-flex align-items-center gap-2 mb-3" style="border-radius:10px; background:#ede9fe; border:none; color:#4c1d95">
                            <i class="bi bi-laptop fs-5"></i>
                            <div>
                                <strong>ครุภัณฑ์ที่เลือก:</strong>
                                <span id="modal_equip_name" class="ms-1">-</span>
                                <span class="ms-2 text-muted" style="font-size:0.82rem">(คงเหลือ: <span id="modal_equip_max">-</span> ชิ้น)</span>
                            </div>
                        </div>

                        <!-- Section 1: ข้อมูลผู้ยืม -->
                        <div class="form-section">
                            <div class="form-section-title"><i class="bi bi-person-badge"></i> ข้อมูลผู้ยืม</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">ชื่อ-นามสกุล ผู้ยืม <span class="required">*</span></label>
                                    <input type="text" name="borrower_name" id="borrower_name" class="form-control" placeholder="กรอกชื่อ-นามสกุล" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ตำแหน่งผู้ยืม <span class="required">*</span></label>
                                    <div class="radio-group" id="position_group">
                                        <label class="radio-option">
                                            <input type="radio" name="borrower_position" value="doctor"> แพทย์
                                        </label>
                                        <label class="radio-option">
                                            <input type="radio" name="borrower_position" value="professional"> บุคลากรสายวิชาชีพ
                                        </label>
                                        <label class="radio-option">
                                            <input type="radio" name="borrower_position" value="support"> บุคลากรสายสนับสนุน
                                        </label>
                                        <label class="radio-option">
                                            <input type="radio" name="borrower_position" value="student"> นิสิต
                                        </label>
                                        <label class="radio-option">
                                            <input type="radio" name="borrower_position" value="external"> บุคคลภายนอก
                                        </label>
                                        <label class="radio-option" id="position_other_wrap">
                                            <input type="radio" name="borrower_position" value="other"> อื่น ๆ:
                                            <input type="text" name="borrower_position_other" id="borrower_position_other" class="form-control form-control-sm ms-2" placeholder="ระบุ..." style="display:none; width:auto; flex:1">
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">หน่วยสังกัด <span class="required">*</span></label>
                                    <select name="borrower_unit" id="borrower_unit" class="form-select" required>
                                        <option value=""></option>
                                        <?php foreach ($BORROWER_UNITS as $u): ?>
                                        <option value="<?= htmlspecialchars($u['label']) ?>"><?= htmlspecialchars($u['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="mt-3">
                                        <label class="form-label">เบอร์ภายใน <span class="required">*</span></label>
                                        <input type="text" name="borrower_phone" id="borrower_phone" class="form-control" placeholder="เช่น 1234">
                                    </div>
                                    <div class="mt-3">
                                        <label class="form-label">อีเมล <span class="required">*</span></label>
                                        <input type="email" name="borrower_email" id="borrower_email" class="form-control" placeholder="example@domain.com" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: ครุภัณฑ์ที่ต้องการยืม -->
                        <div class="form-section">
                            <div class="form-section-title"><i class="bi bi-laptop"></i> ครุภัณฑ์ที่ต้องการยืม</div>
                            <div class="row g-3">
                                <div class="col-md-7">
                                    <label class="form-label">มีความประสงค์จะขอยืม <span class="required">*</span></label>
                                    <div class="radio-group" id="equip_type_group">
                                        <label class="radio-option">
                                            <input type="radio" name="equipment_type" value="notebook"> Notebook
                                        </label>
                                        <label class="radio-option">
                                            <input type="radio" name="equipment_type" value="pc"> คอมพิวเตอร์ตั้งโต๊ะ (PC)
                                        </label>
                                        <label class="radio-option">
                                            <input type="radio" name="equipment_type" value="aio"> คอมพิวเตอร์ตั้งโต๊ะ (AIO)
                                        </label>
                                        <label class="radio-option">
                                            <input type="radio" name="equipment_type" value="printer"> เครื่องพิมพ์ (Printer)
                                        </label>
                                        <label class="radio-option" id="equip_other_wrap">
                                            <input type="radio" name="equipment_type" value="other"> อื่น ๆ:
                                            <input type="text" name="equipment_type_other" id="equipment_type_other" class="form-control form-control-sm ms-2" placeholder="ระบุ..." style="display:none; width:auto; flex:1">
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">จำนวนที่ยืม (ชิ้น) <span class="required">*</span></label>
                                    <input type="number" name="borrow_quantity" id="borrow_quantity" class="form-control" min="1" value="1" required>
                                    <div class="text-muted mt-1" style="font-size:0.78rem">คงเหลือในระบบ: <strong id="qty_remaining">-</strong> ชิ้น</div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: เหตุผล -->
                        <div class="form-section">
                            <div class="form-section-title"><i class="bi bi-chat-text"></i> เหตุผลในการยืม / การนำไปใช้งาน</div>
                            <div class="radio-group" id="purpose_group" style="flex-direction: row; flex-wrap: wrap; gap: 8px;">
                                <label class="radio-option" style="flex: 1; min-width: 160px;">
                                    <input type="radio" name="purpose" value="teaching"> การเรียนการสอน
                                </label>
                                <label class="radio-option" style="flex: 1; min-width: 160px;">
                                    <input type="radio" name="purpose" value="meeting"> จัดประชุม
                                </label>
                                <label class="radio-option" style="flex: 1; min-width: 160px;">
                                    <input type="radio" name="purpose" value="training"> จัดอบรม
                                </label>
                                <label class="radio-option" style="flex: 1; min-width: 160px;">
                                    <input type="radio" name="purpose" value="project"> จัดโครงการ
                                </label>
                            </div>
                        </div>

                        <!-- Section 4: วันที่ยืม-คืน -->
                        <div class="form-section">
                            <div class="form-section-title"><i class="bi bi-calendar-range"></i> ระยะเวลาการยืม</div>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label">วันที่ยืม <span class="required">*</span></label>
                                    <input type="date" name="borrow_date" id="borrow_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">วันที่กำหนดคืน <span class="required">*</span></label>
                                    <input type="date" name="return_date_planned" id="return_date_planned" class="form-control" required>
                                </div>
                                <div class="col-md-2 text-center">
                                    <div id="day_count_badge" style="display:none">
                                        <div style="font-size:0.75rem; color:#6b7280; margin-bottom:4px">ระยะเวลา</div>
                                        <div class="day-badge">
                                            <i class="bi bi-calendar-check"></i>
                                            <span id="day_count_num">0</span> วัน
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 5: IT Installation -->
                        <div class="form-section mb-0">
                            <div class="form-section-title"><i class="bi bi-tools"></i> การติดตั้งโดยเจ้าหน้าที่ IT</div>
                            <p class="text-muted mb-2" style="font-size:0.85rem">ต้องการให้เจ้าหน้าที่ IT ดำเนินการติดตั้งครุภัณฑ์ที่จะยืมให้หรือไม่?</p>
                            <div class="toggle-group">
                                <div class="toggle-btn" id="btn_it_yes" onclick="selectIT(1)">
                                    <i class="bi bi-check-circle me-1"></i> ต้องการ
                                </div>
                                <div class="toggle-btn" id="btn_it_no" onclick="selectIT(0)">
                                    <i class="bi bi-x-circle me-1"></i> ไม่ต้องการ
                                </div>
                            </div>
                            <input type="hidden" name="it_install" id="it_install" value="">
                        </div>
                    </div>

                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                            <i class="bi bi-x"></i> ยกเลิก
                        </button>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-send me-1"></i> ส่งคำขอยืม
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php if (!empty($swal_msg)): ?>
    <script><?php echo $swal_msg; ?></script>
    <?php endif; ?>
    <script>
    // ---- Tom Select: searchable dropdown for หน่วยสังกัด ----
    window._tsUnit = new TomSelect('#borrower_unit', {
        placeholder: 'พิมพ์เพื่อค้นหาหน่วยสังกัด',
        allowEmptyOption: true,
        maxOptions: 400,
        searchField: ['text'],
        render: {
            no_results: function() { return '<div class="no-results">ไม่พบหน่วยสังกัดที่ค้นหา</div>'; }
        }
    });
    // ---- Open modal with equipment data ----
    document.getElementById('borrowDetailModal').addEventListener('show.bs.modal', function(e) {
        const btn = e.relatedTarget;
        const id   = btn.getAttribute('data-id');
        const name = btn.getAttribute('data-name');
        const max  = btn.getAttribute('data-max');

        document.getElementById('modal_equipment_id').value = id;
        document.getElementById('modal_equip_name').textContent = name;
        document.getElementById('modal_equip_max').textContent = max;
        document.getElementById('qty_remaining').textContent = max;
        document.getElementById('borrow_quantity').max = max;

        // Reset form
        document.getElementById('borrowDetailForm').reset();
        // Clear Tom Select dropdown
        if (window._tsUnit) window._tsUnit.clear();
        document.getElementById('it_install').value = '';
        document.getElementById('btn_it_yes').className = 'toggle-btn';
        document.getElementById('btn_it_no').className  = 'toggle-btn';
        document.getElementById('borrower_position_other').style.display = 'none';
        document.getElementById('equipment_type_other').style.display    = 'none';
        document.getElementById('day_count_badge').style.display = 'none';
        document.querySelectorAll('.radio-option').forEach(el => el.classList.remove('selected'));
    });

    // ---- Radio option highlight ----
    document.querySelectorAll('.radio-option input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const group = this.closest('.radio-group');
            group.querySelectorAll('.radio-option').forEach(opt => opt.classList.remove('selected'));
            this.closest('.radio-option').classList.add('selected');

            // Show/hide "other" text inputs
            if (this.name === 'borrower_position') {
                const txt = document.getElementById('borrower_position_other');
                txt.style.display = this.value === 'other' ? 'inline-block' : 'none';
                if (this.value !== 'other') txt.value = '';
            }
            if (this.name === 'equipment_type') {
                const txt = document.getElementById('equipment_type_other');
                txt.style.display = this.value === 'other' ? 'inline-block' : 'none';
                if (this.value !== 'other') txt.value = '';
            }
        });
    });

    // ---- IT install toggle ----
    function selectIT(val) {
        document.getElementById('it_install').value = val;
        if (val === 1) {
            document.getElementById('btn_it_yes').className = 'toggle-btn active-yes';
            document.getElementById('btn_it_no').className  = 'toggle-btn';
        } else {
            document.getElementById('btn_it_yes').className = 'toggle-btn';
            document.getElementById('btn_it_no').className  = 'toggle-btn active-no';
        }
    }

    // ---- Date range & day count ----
    function updateDayCount() {
        const d1 = document.getElementById('borrow_date').value;
        const d2 = document.getElementById('return_date_planned').value;
        const badge = document.getElementById('day_count_badge');
        const num   = document.getElementById('day_count_num');

        // Auto-set min return date
        if (d1) {
            document.getElementById('return_date_planned').min = d1;
        }

        if (d1 && d2) {
            const diff = Math.round((new Date(d2) - new Date(d1)) / (1000 * 60 * 60 * 24));
            if (diff >= 0) {
                num.textContent = diff + 1; // inclusive
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        } else {
            badge.style.display = 'none';
        }
    }

    document.getElementById('borrow_date').addEventListener('change', updateDayCount);
    document.getElementById('return_date_planned').addEventListener('change', updateDayCount);

    // ---- Form validation before submit ----
    document.getElementById('borrowDetailForm').addEventListener('submit', function(e) {
        const position = document.querySelector('input[name="borrower_position"]:checked');
        const eqType   = document.querySelector('input[name="equipment_type"]:checked');
        const purpose  = document.querySelector('input[name="purpose"]:checked');
        const itInstall = document.getElementById('it_install').value;

        if (!position) { e.preventDefault(); Swal.fire('แจ้งเตือน', 'กรุณาเลือกตำแหน่งผู้ยืม', 'warning'); return; }
        if (!eqType)   { e.preventDefault(); Swal.fire('แจ้งเตือน', 'กรุณาเลือกประเภทครุภัณฑ์ที่ยืม', 'warning'); return; }
        if (!purpose)  { e.preventDefault(); Swal.fire('แจ้งเตือน', 'กรุณาเลือกเหตุผลในการยืม', 'warning'); return; }
        if (itInstall === '') { e.preventDefault(); Swal.fire('แจ้งเตือน', 'กรุณาเลือกว่าต้องการให้ IT ติดตั้งหรือไม่', 'warning'); return; }
    });
    </script>
</body>
</html>