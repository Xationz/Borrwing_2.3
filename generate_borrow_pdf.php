<?php
/**
 * generate_borrow_pdf.php
 * สร้าง PDF แบบฟอร์มการยืมครุภัณฑ์ โดยวางข้อมูลทับบน Template PDF
 *
 * รับ POST parameters:
 *   borrower_name, borrower_position, borrower_position_other,
 *   borrower_unit, borrower_phone, equipment_type, equipment_type_other,
 *   borrow_quantity, purpose, borrow_date, return_date_planned
 *   (หรือรับ borrowing_id เพื่อดึงข้อมูลจาก DB)
 */

session_start();
require_once __DIR__ . '/config.php';

// ── Security: ต้อง login เท่านั้น ──────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

// ── รับข้อมูล ───────────────────────────────────────────────────────────────
$data = [];

// ถ้าส่ง borrowing_id มา ให้ดึงจาก DB
if (!empty($_REQUEST['borrowing_id'])) {
    $bid = (int)$_REQUEST['borrowing_id'];
    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

    if ($isAdmin) {
        // Admin สามารถดึงข้อมูลการยืมของผู้ใช้คนใดก็ได้
        $stmt = $pdo->prepare(
            "SELECT b.*, e.name AS equip_name, u.username
               FROM borrowings b
               JOIN equipment e ON b.equipment_id = e.id
               JOIN users u ON b.user_id = u.id
              WHERE b.id = ?"
        );
        $stmt->execute([$bid]);
    } else {
        // User ทั่วไปดึงได้เฉพาะรายการของตัวเอง
        $stmt = $pdo->prepare(
            "SELECT b.*, e.name AS equip_name, u.username
               FROM borrowings b
               JOIN equipment e ON b.equipment_id = e.id
               JOIN users u ON b.user_id = u.id
              WHERE b.id = ? AND b.user_id = ?"
        );
        $stmt->execute([$bid, $_SESSION['user_id']]);
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        exit('Borrowing not found');
    }
    $data['borrower_name']          = $row['borrower_name']          ?? '';
    $data['borrower_position']      = $row['borrower_position']      ?? '';
    $data['borrower_position_other']= $row['borrower_position_other']?? '';
    $data['borrower_unit']          = $row['borrower_unit']          ?? '';
    $data['borrower_phone']         = $row['borrower_phone']         ?? '';
    $data['borrower_email']         = $row['borrower_email']         ?? '';
    $data['equipment_type']         = $row['equipment_type']         ?? '';
    $data['equipment_type_other']   = $row['equipment_type_other']   ?? '';
    $data['borrow_quantity']        = $row['borrow_quantity']        ?? 1;
    $data['purpose']                = $row['purpose']                ?? '';
    $data['borrow_date']            = $row['borrow_date']            ?? '';
    $data['return_date_planned']    = $row['return_date_planned']    ?? '';
} else {
    // รับจาก POST โดยตรง (form submit)
    $data['borrower_name']          = trim($_POST['borrower_name']          ?? '');
    $data['borrower_position']      = $_POST['borrower_position']           ?? '';
    $data['borrower_position_other']= trim($_POST['borrower_position_other']?? '');
    $data['borrower_unit']          = trim($_POST['borrower_unit']          ?? '');
    $data['borrower_phone']         = trim($_POST['borrower_phone']         ?? '');
    $data['borrower_email']         = trim($_POST['borrower_email']         ?? '');
    $data['equipment_type']         = $_POST['equipment_type']              ?? '';
    $data['equipment_type_other']   = trim($_POST['equipment_type_other']   ?? '');
    $data['borrow_quantity']        = (int)($_POST['borrow_quantity']       ?? 1);
    $data['purpose']                = $_POST['purpose']                     ?? '';
    $data['borrow_date']            = $_POST['borrow_date']                 ?? '';
    $data['return_date_planned']    = $_POST['return_date_planned']         ?? '';
    $data['email']                  = $_SESSION['email']                    ?? '';
}

// ── Helper functions ─────────────────────────────────────────────────────────
function thaiMonth(int $m): string {
    $months = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
               'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
    return $months[$m] ?? '';
}

function parseThaiDate(string $dateStr): array {
    // Input: YYYY-MM-DD (CE)  Output: [day, monthName, buddhistYear]
    if (empty($dateStr)) return ['', '', ''];
    try {
        [$y, $m, $d] = explode('-', $dateStr);
        $y = (int)$y;
        $m = (int)$m;
        $d = (int)$d;
        if ($y < 2500) $y += 543; // Convert CE → BE
        return [(string)$d, thaiMonth($m), (string)$y];
    } catch (Exception $e) {
        return ['', '', ''];
    }
}

function thaiDateFull(string $dateStr): string {
    [$d, $mn, $y] = parseThaiDate($dateStr);
    if (!$d) return '';
    return "$d $mn $y";
}

function positionLabel(string $pos, string $other = ''): string {
    $map = [
        'doctor'       => 'แพทย์',
        'professional' => 'บุคลากรสายวิชาชีพ',
        'support'      => 'บุคลากรสายสนับสนุน',
        'student'      => 'นิสิต',
        'external'     => 'บุคคลภายนอก',
        'other'        => $other ?: 'อื่น ๆ',
    ];
    return $map[$pos] ?? $pos;
}

function buildSerialText(array $rows): array {
    $lines = [];
    $allSerials = [];
    $totalQty = 0;

    foreach ($rows as $r) {
        $serialText = trim((string)($r['serials'] ?? ''));
        $serials = array_values(array_filter(array_map('trim', explode(',', $serialText))));
        $totalQty += (int)($r['display_qty'] ?? count($serials) ?: 1);

        foreach ($serials as $s) {
            $allSerials[] = $s;
        }

        if ($serialText !== '') {
            $lines[] = trim(($r['equip_name'] ?? 'ครุภัณฑ์') . ': ' . $serialText);
        }
    }

    if (count($rows) === 1 && count($allSerials) === 1) {
        $text = $allSerials[0];
    } elseif (!empty($lines)) {
        $text = implode("\n", $lines);
    } else {
        $text = '';
    }

    return [
        'text' => $text,
        'lines' => $lines,
        'total_qty' => $totalQty > 0 ? $totalQty : 1,
    ];
}

// ── Add serial codes for PDF ────────────────────────────────────────────────
// Multi-equipment requests are saved as separate borrowing rows in the same
// transaction. Rows created at the same time with the same borrower/date fields
// are treated as one print group so one PDF can show every selected serial.
if (!empty($row)) {
    $relatedSql = "
        SELECT b.id, b.equipment_id, e.name AS equip_name,
               COALESCE(b.borrow_quantity, b.quantity, 1) AS display_qty,
               GROUP_CONCAT(bs.serial_code ORDER BY bs.id SEPARATOR ', ') AS serials
          FROM borrowings b
          JOIN equipment e ON b.equipment_id = e.id
          LEFT JOIN borrow_serials bs ON bs.borrowing_id = b.id
         WHERE b.user_id = ?
           AND b.created_at = ?
           AND b.borrow_date <=> ?
           AND b.return_date_planned <=> ?
           AND COALESCE(b.borrower_name, '') = ?
           AND COALESCE(b.borrower_unit, '') = ?
         GROUP BY b.id
         ORDER BY b.id
    ";
    $relatedStmt = $pdo->prepare($relatedSql);
    $relatedStmt->execute([
        $row['user_id'],
        $row['created_at'],
        $row['borrow_date'],
        $row['return_date_planned'],
        $row['borrower_name'] ?? '',
        $row['borrower_unit'] ?? '',
    ]);
    $relatedRows = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($relatedRows)) {
        $relatedRows = [[
            'id' => $row['id'],
            'equipment_id' => $row['equipment_id'],
            'equip_name' => $row['equip_name'] ?? '',
            'display_qty' => $row['borrow_quantity'] ?? $row['quantity'] ?? 1,
            'serials' => '',
        ]];
    }

    $serialPdf = buildSerialText($relatedRows);
    $data['equipment_serial_text'] = $serialPdf['text'];
    $data['equipment_serial_lines'] = $serialPdf['lines'];
    $data['borrow_quantity'] = $serialPdf['total_qty'];
}

// ── Build PDF with Python (reportlab + pypdf) ────────────────────────────────
$templatePath = __DIR__ . '/borrow_template.pdf';
if (!file_exists($templatePath)) {
    http_response_code(500);
    exit('Template PDF not found: borrow_template.pdf');
}

// Encode data as JSON to pass to Python
$jsonData = json_encode([
    'borrower_name'          => $data['borrower_name'],
    'position_text'          => positionLabel($data['borrower_position'], $data['borrower_position_other']),
    'borrower_unit'          => $data['borrower_unit'],
    'borrower_phone'         => $data['borrower_phone'],
    'borrower_email'         => $data['borrower_email'] ?? '',
    'equipment_type'         => $data['equipment_type'],
    'equipment_type_other'   => $data['equipment_type_other'],
    'borrow_quantity'        => (string)$data['borrow_quantity'],
    'equipment_serial_text'  => $data['equipment_serial_text'] ?? '',
    'equipment_serial_lines' => $data['equipment_serial_lines'] ?? [],
    'purpose'                => $data['purpose'],
    'borrow_date'            => $data['borrow_date'],
    'return_date_planned'    => $data['return_date_planned'],
    'borrow_date_text'       => thaiDateFull($data['borrow_date']),
    'template_path'          => $templatePath,
], JSON_UNESCAPED_UNICODE);

// Write JSON to temp file
$tmpJson = tempnam(sys_get_temp_dir(), 'borrow_') . '.json';
file_put_contents($tmpJson, $jsonData);

$pythonScript = __DIR__ . '/pdf_generator.py';
$tmpOutput    = tempnam(sys_get_temp_dir(), 'borrow_pdf_') . '.pdf';

$cmd = escapeshellcmd("python3 $pythonScript") .
       ' ' . escapeshellarg($tmpJson) .
       ' ' . escapeshellarg($tmpOutput);

exec($cmd . ' 2>&1', $output, $retCode);

// Cleanup temp JSON
@unlink($tmpJson);

if ($retCode !== 0 || !file_exists($tmpOutput)) {
    http_response_code(500);
    exit('PDF generation failed: ' . implode("\n", $output));
}

// ── Stream PDF to browser ─────────────────────────────────────────────────────
$pdfContent = file_get_contents($tmpOutput);
@unlink($tmpOutput);

$filename = 'borrow_form_' . date('Ymd_His') . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfContent));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

echo $pdfContent;
exit;
