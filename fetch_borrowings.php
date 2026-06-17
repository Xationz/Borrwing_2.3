<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config.php';

// This endpoint is shared by the admin calendar and the user borrow-request
// wizard calendar (both read the same live data so bookings stay in sync in
// real time) — require any authenticated session before returning data.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$equipmentIds = [];
if (isset($_GET['equipment_id'])) {
    $equipmentIds[] = (int)$_GET['equipment_id'];
}
if (isset($_GET['equipment_ids'])) {
    $rawIds = is_array($_GET['equipment_ids'])
        ? $_GET['equipment_ids']
        : explode(',', (string)$_GET['equipment_ids']);
    foreach ($rawIds as $id) {
        $id = (int)$id;
        if ($id > 0) $equipmentIds[] = $id;
    }
}
$equipmentIds = array_values(array_unique(array_filter($equipmentIds)));

$where = [];
$params = [];
if (!empty($equipmentIds)) {
    $where[] = 'b.equipment_id IN (' . implode(',', array_fill(0, count($equipmentIds), '?')) . ')';
    $params = array_merge($params, $equipmentIds);
}
if (isset($_GET['include_returned']) && $_GET['include_returned'] === '0') {
    $where[] = "b.status NOT IN ('returned')";
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT b.equipment_id, b.borrow_date, b.return_date_planned, e.name AS equipment_name, e.image, u.username,
           b.status, b.borrower_name,
           GROUP_CONCAT(bs.serial_code SEPARATOR ', ') AS serials,
           COUNT(bs.id) AS serial_count
    FROM borrowings b
    JOIN equipment e ON b.equipment_id = e.id
    JOIN users u ON b.user_id = u.id
    LEFT JOIN borrow_serials bs ON bs.borrowing_id = b.id
    $whereSql
    GROUP BY b.id
");
$stmt->execute($params);
$borrowings = $stmt->fetchAll();

$events = [];
foreach ($borrowings as $b) {
    $status = $b['status'];
    // Normalise 'borrowed' → 'borrowing' for display
    if ($status === 'borrowed') $status = 'borrowing';

    $colorMap = [
        'pending'                 => '#10b981',
        'approved'                => '#7c3aed',
        'borrowing'               => '#10b981',
        'waiting_return_approval' => '#3b82f6',
        'returned'                => '#9ca3af',
    ];
    $color = $colorMap[$status] ?? '#6b7280';

    // FullCalendar end is exclusive → add 1 day
    $endDate = $b['return_date_planned']
        ? date('Y-m-d', strtotime($b['return_date_planned'] . ' +1 day'))
        : null;

    $events[] = [
        'title' => $b['equipment_name'],
        'equipment_id' => $b['equipment_id'] ?? null,
        'start' => $b['borrow_date'],
        'end'   => $endDate,
        'color' => $color,
        'extendedProps' => [
            'equipment_id'  => $b['equipment_id'] ?? null,
            'username'     => $b['username'],
            'borrowerName' => $b['borrower_name'] ?? $b['username'],
            'serials'      => $b['serials'] ?? '-',
            'serialCount'  => (int)($b['serial_count'] ?? 0),
            'status'       => $status,
            'image'        => $b['image'] ? $b['image'] : 'default.jpg',
        ]
    ];
}

header('Content-Type: application/json');
echo json_encode($events);
