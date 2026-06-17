<?php
/**
 * Borrow business logic — migrations, handlers, data loading
 * Requires: $pdo, active session, org_units.php
 */
if (!isset($swal_msg)) $swal_msg = '';

require_once dirname(__DIR__) . '/org_units.php';

$migrate_columns = [
    "borrower_name"           => "ALTER TABLE borrowings ADD COLUMN borrower_name varchar(255) DEFAULT NULL",
    "borrower_position"       => "ALTER TABLE borrowings ADD COLUMN borrower_position varchar(100) DEFAULT NULL",
    "borrower_position_other" => "ALTER TABLE borrowings ADD COLUMN borrower_position_other varchar(255) DEFAULT NULL",
    "borrower_unit"           => "ALTER TABLE borrowings ADD COLUMN borrower_unit varchar(255) DEFAULT NULL",
    "borrower_phone"          => "ALTER TABLE borrowings ADD COLUMN borrower_phone varchar(50) DEFAULT NULL",
    "borrower_email"          => "ALTER TABLE borrowings ADD COLUMN borrower_email varchar(100) DEFAULT NULL",
    "borrower_student_id"     => "ALTER TABLE borrowings ADD COLUMN borrower_student_id varchar(50) DEFAULT NULL",
    "equipment_type"          => "ALTER TABLE borrowings ADD COLUMN equipment_type varchar(100) DEFAULT NULL",
    "equipment_type_other"    => "ALTER TABLE borrowings ADD COLUMN equipment_type_other varchar(255) DEFAULT NULL",
    "borrow_quantity"         => "ALTER TABLE borrowings ADD COLUMN borrow_quantity int(11) DEFAULT 1",
    "purpose"                 => "ALTER TABLE borrowings ADD COLUMN purpose text DEFAULT NULL",
    "use_location"            => "ALTER TABLE borrowings ADD COLUMN use_location varchar(255) DEFAULT NULL",
    "return_date_planned"     => "ALTER TABLE borrowings ADD COLUMN return_date_planned date DEFAULT NULL",
    "it_install"              => "ALTER TABLE borrowings ADD COLUMN it_install tinyint(1) DEFAULT 0",
];
foreach ($migrate_columns as $col => $sql) {
    try {
        $check = $pdo->query("SHOW COLUMNS FROM borrowings LIKE '$col'");
        if ($check->rowCount() === 0) $pdo->exec($sql);
    } catch (Exception $e) {}
}

$migrate_return_cols = [
    'returned_request_at' => "ALTER TABLE borrowings ADD COLUMN returned_request_at datetime DEFAULT NULL",
    'approved_return_at'  => "ALTER TABLE borrowings ADD COLUMN approved_return_at datetime DEFAULT NULL",
    'approved_by_admin'   => "ALTER TABLE borrowings ADD COLUMN approved_by_admin int(11) DEFAULT NULL",
    'actual_return_date'  => "ALTER TABLE borrowings ADD COLUMN actual_return_date date DEFAULT NULL",
];
foreach ($migrate_return_cols as $col => $sql) {
    try {
        $check = $pdo->query("SHOW COLUMNS FROM borrowings LIKE '$col'");
        if ($check->rowCount() === 0) $pdo->exec($sql);
    } catch (Exception $e) {}
}
try {
    $pdo->exec("ALTER TABLE borrowings MODIFY COLUMN status ENUM('borrowing','waiting_return_approval','returned','borrowed','pending') DEFAULT 'borrowing'");
    $pdo->exec("UPDATE borrowings SET status='borrowing' WHERE status IN ('borrowed','pending')");
} catch (Exception $e) {}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `borrow_serials` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `borrowing_id` int(11) NOT NULL,
        `serial_id` int(11) NOT NULL,
        `serial_code` varchar(100) NOT NULL,
        PRIMARY KEY (`id`), KEY `borrowing_id` (`borrowing_id`), KEY `serial_id` (`serial_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `equipment_serials` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `equipment_id` int(11) NOT NULL,
        `serial_code` varchar(100) NOT NULL,
        `status` enum('available','borrowed') DEFAULT 'available',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`), UNIQUE KEY `serial_code` (`serial_code`), KEY `equipment_id` (`equipment_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $chk = $pdo->query("SHOW COLUMNS FROM equipment_serials LIKE 'status'");
    if ($chk->rowCount() === 0) {
        $pdo->exec("ALTER TABLE equipment_serials ADD COLUMN status enum('available','borrowed') DEFAULT 'available'");
    }
} catch (Exception $e) {}

if (!function_exists('borrow_days_count')) {
    function borrow_days_count($borrow_date, $return_date_planned) {
        if (empty($borrow_date) || empty($return_date_planned)) return 0;
        $start = strtotime($borrow_date);
        $end = strtotime($return_date_planned);
        if ($start === false || $end === false || $end < $start) return 0;
        return (int)floor(($end - $start) / 86400) + 1;
    }
}

if (!function_exists('post_borrow_to_apps_script')) {
    function post_borrow_to_apps_script(array $payload) {
        if (!defined('GOOGLE_APPS_SCRIPT_WEB_APP_URL') || trim(GOOGLE_APPS_SCRIPT_WEB_APP_URL) === '') {
            return ['ok' => true, 'skipped' => true];
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => $json, 'timeout' => 8, 'ignore_errors' => true,
        ]]);
        $response = @file_get_contents(GOOGLE_APPS_SCRIPT_WEB_APP_URL, false, $context);
        $status_line = '';
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/^HTTP\//', $header)) $status_line = $header;
        }
        $http_ok = preg_match('/\s2\d\d\s/', $status_line) === 1;
        $decoded = is_string($response) ? json_decode($response, true) : null;
        $app_ok = is_array($decoded) ? (($decoded['success'] ?? $decoded['ok'] ?? false) === true) : $http_ok;
        if (!$http_ok || !$app_ok) {
            return ['ok' => false, 'message' => $decoded['error'] ?? $decoded['message'] ?? 'Apps Script POST failed'];
        }
        return ['ok' => true, 'response' => $decoded ?: $response];
    }
}

$confirm_color = '#10B981';

if (isset($_POST['borrow_equipment'])) {
    $equipment_id         = (int)($_POST['equipment_id'] ?? 0);
    $serial_ids           = $_POST['serial_ids'] ?? [];
    $multi_equipment_ids  = $_POST['multi_equipment_ids'] ?? [];
    $equipment_type       = $_POST['equipment_type'] ?? '';
    $equipment_type_other = trim($_POST['equipment_type_other'] ?? '');
    $borrow_date          = $_POST['borrow_date'] ?? '';
    $return_date_planned  = $_POST['return_date_planned'] ?? '';
    $borrower_name        = trim($_POST['borrower_name'] ?? '');
    $borrower_student_id  = trim($_POST['borrower_student_id'] ?? '');
    $borrower_position    = trim($_POST['borrower_position'] ?? '');
    $borrower_unit        = trim($_POST['borrower_unit'] ?? '');
    $borrower_phone       = trim($_POST['borrower_phone'] ?? '');
    $borrower_email       = trim($_POST['borrower_email'] ?? '');
    $purpose              = trim($_POST['purpose'] ?? '');
    $use_location         = trim($_POST['use_location'] ?? '');
    $it_install           = isset($_POST['it_install']) && $_POST['it_install'] === '1' ? 1 : 0;

    $errors = [];
    if ($equipment_id <= 0) $errors[] = 'ข้อมูลครุภัณฑ์ไม่ถูกต้อง';
    if (empty($serial_ids)) $errors[] = 'กรุณาเลือกรหัสครุภัณฑ์อย่างน้อย 1 รายการ';
    if (empty($equipment_type)) $errors[] = 'กรุณาเลือกประเภทครุภัณฑ์';
    if (empty($borrow_date)) $errors[] = 'กรุณาเลือกวันที่ยืม';
    if (empty($return_date_planned)) $errors[] = 'กรุณาเลือกวันที่กำหนดคืน';
    if (!empty($borrow_date) && !empty($return_date_planned) && strtotime($return_date_planned) < strtotime($borrow_date))
        $errors[] = 'วันที่คืนต้องไม่น้อยกว่าวันที่ยืม';
    if (empty($borrower_name)) $errors[] = 'กรุณากรอกชื่อ-นามสกุลผู้ยืม';
    if (empty($borrower_position)) $errors[] = 'กรุณาเลือกตำแหน่งผู้ยืม';
    if (empty($borrower_unit)) $errors[] = 'กรุณาเลือกหน่วยงานที่สังกัด';
    if (!empty($borrower_phone) && !preg_match('/^[0-9]{4,6}$/', $borrower_phone)) $errors[] = 'กรุณากรอกเบอร์ภายใน 4-6 หลัก';
    if (empty($purpose)) $errors[] = 'กรุณากรอกเหตุผลการยืม/การใช้งาน';
    if (empty($use_location)) $errors[] = 'กรุณากรอกสถานที่ใช้งาน';
    if (($_POST['it_install'] ?? '') === '') $errors[] = 'กรุณาเลือกการติดตั้งโดยเจ้าหน้าที่ IT';

    $serial_ids_int = array_values(array_unique(array_filter(array_map('intval', $serial_ids))));
    if (empty($serial_ids_int)) $errors[] = 'รหัสครุภัณฑ์ที่เลือกไม่ถูกต้อง';

    $allowed_equipment_ids = array_values(array_unique(array_filter(array_map('intval', $multi_equipment_ids))));
    if (empty($allowed_equipment_ids) && $equipment_id > 0) $allowed_equipment_ids = [$equipment_id];

    $serials_by_selected_equipment = [];
    if (empty($errors) && !empty($serial_ids_int)) {
        $placeholders = implode(',', array_fill(0, count($serial_ids_int), '?'));
        $serial_stmt = $pdo->prepare("SELECT id, equipment_id, serial_code FROM equipment_serials WHERE id IN ($placeholders)");
        $serial_stmt->execute($serial_ids_int);
        $serial_rows = $serial_stmt->fetchAll();
        if (count($serial_rows) !== count($serial_ids_int)) $errors[] = 'รหัสครุภัณฑ์บางรายการไม่ถูกต้อง';
        foreach ($serial_rows as $row) {
            if (!in_array((int)$row['equipment_id'], $allowed_equipment_ids, true)) {
                $errors[] = 'รหัสครุภัณฑ์บางรายการไม่อยู่ในเครื่องที่เลือก';
                break;
            }
            $serials_by_selected_equipment[(int)$row['equipment_id']][] = $row;
        }
    }

    if (empty($errors) && !empty($allowed_equipment_ids) && !empty($borrow_date) && !empty($return_date_planned)) {
        $eq_ph = implode(',', array_fill(0, count($allowed_equipment_ids), '?'));
        $eq_stmt = $pdo->prepare("SELECT DISTINCT e.name FROM borrowings b JOIN equipment e ON b.equipment_id = e.id
            WHERE b.equipment_id IN ($eq_ph) AND b.status NOT IN ('returned') AND (b.borrow_date <= ? AND b.return_date_planned >= ?)");
        $eq_stmt->execute(array_merge($allowed_equipment_ids, [$return_date_planned, $borrow_date]));
        $conflicts = $eq_stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($conflicts)) $errors[] = 'ครุภัณฑ์มีการจองในช่วงเวลาที่เลือกแล้ว: ' . implode(', ', $conflicts);
    }

    if (empty($errors) && !empty($serial_ids_int)) {
        $ph = implode(',', array_fill(0, count($serial_ids_int), '?'));
        $c_stmt = $pdo->prepare("SELECT es.serial_code FROM borrow_serials bs JOIN equipment_serials es ON bs.serial_id = es.id
            JOIN borrowings b ON bs.borrowing_id = b.id WHERE bs.serial_id IN ($ph) AND b.status NOT IN ('returned')
            AND (b.borrow_date <= ? AND b.return_date_planned >= ?)");
        $c_stmt->execute(array_merge($serial_ids_int, [$return_date_planned, $borrow_date]));
        $conflicts = $c_stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($conflicts)) $errors[] = 'รหัสครุภัณฑ์มีการจองแล้ว: ' . implode(', ', $conflicts);
    }

    if (!empty($errors)) {
        $swal_msg = "Swal.fire({title:'ข้อมูลไม่ครบถ้วน',html:'" . addslashes(implode('<br>', $errors)) . "',icon:'error',confirmButtonColor:'$confirm_color'});";
    } else {
        $pdo->beginTransaction();
        try {
            $sheet_payloads = [];
            $stmt = $pdo->prepare("INSERT INTO borrowings (user_id,equipment_id,quantity,borrow_quantity,borrow_date,return_date_planned,
                borrower_name,borrower_student_id,borrower_position,borrower_unit,borrower_phone,borrower_email,
                equipment_type,equipment_type_other,purpose,use_location,it_install,status)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'borrowing')");
            $ins_serial = $pdo->prepare("INSERT INTO borrow_serials (borrowing_id,serial_id,serial_code) VALUES (?,?,?)");
            $upd_serial = $pdo->prepare("UPDATE equipment_serials SET status='borrowed' WHERE id=?");

            foreach ($serials_by_selected_equipment as $eq_id => $rows) {
                $qty = count($rows);
                $stmt->execute([
                    $_SESSION['user_id'], $eq_id, $qty, $qty, $borrow_date, $return_date_planned,
                    $borrower_name, $borrower_student_id, $borrower_position, $borrower_unit,
                    $borrower_phone, $borrower_email, $equipment_type, $equipment_type_other,
                    $purpose, $use_location, $it_install
                ]);
                $new_id = $pdo->lastInsertId();
                $sheet_payloads[] = [
                    'borrower_name' => $borrower_name, 'borrower_position' => $borrower_position,
                    'borrower_unit' => $borrower_unit, 'borrower_phone' => $borrower_phone,
                    'borrower_email' => $borrower_email,
                    'equipment_type' => $equipment_type === 'other' && $equipment_type_other ? $equipment_type_other : ($equipment_type === 'notebook' ? 'Notebook' : $equipment_type),
                    'borrow_quantity' => $qty, 'purpose' => $purpose, 'borrow_date' => $borrow_date,
                    'return_date_planned' => $return_date_planned,
                    'borrow_days' => borrow_days_count($borrow_date, $return_date_planned),
                    'it_install' => $it_install ? 'ต้องการ' : 'ไม่ต้องการ',
                    'location' => $use_location,
                    'asset_code' => implode(', ', array_column($rows, 'serial_code')),
                ];
                foreach ($rows as $row) {
                    $ins_serial->execute([$new_id, $row['id'], $row['serial_code']]);
                    $upd_serial->execute([$row['id']]);
                }
            }
            $pdo->commit();
            foreach ($sheet_payloads as $payload) post_borrow_to_apps_script($payload);
            $swal_msg = "Swal.fire({title:'ส่งคำขอสำเร็จ!',html:'บันทึกรายการยืมครุภัณฑ์เรียบร้อยแล้ว<br>สถานะ: <b>กำลังยืม</b>',icon:'success',confirmButtonColor:'$confirm_color'}).then(function(){window.spaNavigate('borrow_history');});";
        } catch (Exception $e) {
            $pdo->rollBack();
            $swal_msg = "Swal.fire('เกิดข้อผิดพลาด','" . addslashes($e->getMessage()) . "','error');";
        }
    }
}

if (isset($_GET['return_borrowing'])) {
    $borrowing_id = (int)$_GET['return_borrowing'];
    try {
        $stmt = $pdo->prepare("UPDATE borrowings SET status='waiting_return_approval', returned_request_at=NOW()
            WHERE id=? AND user_id=? AND status IN ('borrowing','borrowed','pending')");
        $stmt->execute([$borrowing_id, $_SESSION['user_id']]);
        if ($stmt->rowCount() === 0) throw new Exception('ไม่พบรายการ หรือไม่สามารถแจ้งคืนได้');
        $swal_msg = "Swal.fire({title:'แจ้งคืนสำเร็จ!',html:'รายการอยู่ในสถานะ <b>รอเจ้าหน้าที่ตรวจสอบ</b>',icon:'info',confirmButtonColor:'$confirm_color'});";
    } catch (Exception $e) {
        $swal_msg = "Swal.fire('เกิดข้อผิดพลาด','" . addslashes($e->getMessage()) . "','error');";
    }
}

function load_borrow_equipment_data(PDO $pdo): array {
    $equipment = $pdo->query("SELECT e.*, c.name AS category_name FROM equipment e JOIN categories c ON e.category_id = c.id WHERE e.quantity > 0")->fetchAll();
    $serials_by_equip = [];
    foreach ($pdo->query("SELECT id, equipment_id, serial_code, COALESCE(status,'available') as status FROM equipment_serials ORDER BY id")->fetchAll() as $row) {
        $serials_by_equip[$row['equipment_id']][] = ['id' => $row['id'], 'code' => $row['serial_code'], 'status' => $row['status']];
    }
    $busy_by_serial = [];
    foreach ($pdo->query("SELECT bs.serial_id, b.borrow_date, b.return_date_planned FROM borrow_serials bs JOIN borrowings b ON bs.borrowing_id = b.id WHERE b.status NOT IN ('returned')")->fetchAll() as $row) {
        $busy_by_serial[$row['serial_id']][] = ['start' => $row['borrow_date'], 'end' => $row['return_date_planned']];
    }
    return compact('equipment', 'serials_by_equip', 'busy_by_serial');
}

function load_user_borrowings(PDO $pdo, int $userId): array {
    return $pdo->query("SELECT b.*, e.name AS equip_name, COALESCE(b.borrow_quantity, b.quantity, 1) AS display_qty
        FROM borrowings b JOIN equipment e ON b.equipment_id = e.id
        WHERE b.user_id = $userId ORDER BY b.created_at DESC")->fetchAll();
}
