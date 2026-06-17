<?php
/**
 * Navigation configuration per role
 */
return [
    'admin' => [
        ['page' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2'],
        ['page' => 'equipment', 'label' => 'ครุภัณฑ์', 'icon' => 'bi-laptop'],
        ['page' => 'calendar', 'label' => 'ขอยืมครุภัณฑ์', 'icon' => 'bi-calendar3'],
        ['page' => 'return_approval', 'label' => 'ประวัติการยืม', 'icon' => 'bi-clock-history'],
        ['page' => 'return_approval', 'label' => 'จัดการคำขอ', 'icon' => 'bi-patch-check', 'alias' => 'requests'],
        ['page' => 'borrowing_dashboard', 'label' => 'รายงาน', 'icon' => 'bi-bar-chart-line'],
        ['page' => 'settings', 'label' => 'ตั้งค่า', 'icon' => 'bi-gear'],
    ],
    'user' => [
        ['page' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2'],
        ['page' => 'equipment_browse', 'label' => 'ครุภัณฑ์', 'icon' => 'bi-laptop'],
        ['page' => 'borrow', 'label' => 'ขอยืมครุภัณฑ์', 'icon' => 'bi-clipboard-plus'],
        ['page' => 'borrow_history', 'label' => 'ประวัติการยืม', 'icon' => 'bi-clock-history'],
    ],
];
