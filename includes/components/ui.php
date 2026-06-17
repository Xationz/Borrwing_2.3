<?php
/**
 * Reusable UI components for partials
 */

function render_breadcrumb(array $items): void {
    if (empty($items)) return;
    echo '<nav class="app-breadcrumb" aria-label="breadcrumb">';
    $last = count($items) - 1;
    foreach ($items as $i => $item) {
        if ($i > 0) echo '<span class="app-breadcrumb__sep" aria-hidden="true">/</span>';
        if ($i === $last) {
            echo '<span class="app-breadcrumb__current">' . htmlspecialchars($item['label']) . '</span>';
        } else {
            $href = $item['page'] ?? '#';
            echo '<a href="?page=' . htmlspecialchars($href) . '" class="spa-link" data-page="' . htmlspecialchars($href) . '">'
               . htmlspecialchars($item['label']) . '</a>';
        }
    }
    echo '</nav>';
}

function render_page_header(string $title, string $subtitle = '', string $actionsHtml = ''): void {
    $hasActions = trim($actionsHtml) !== '';
    echo '<div class="page-header' . ($hasActions ? ' page-header--row' : '') . '">';
    echo '<div>';
    echo '<h1 class="page-header__title">' . $title . '</h1>';
    if ($subtitle) {
        echo '<p class="page-header__subtitle">' . htmlspecialchars($subtitle) . '</p>';
    }
    echo '</div>';
    if ($hasActions) {
        echo '<div class="page-header__actions">' . $actionsHtml . '</div>';
    }
    echo '</div>';
}

function render_kpi_card(string $label, $value, string $icon, string $variant = 'primary', string $change = ''): void {
    echo '<div class="kpi-card">';
    echo '<div class="kpi-card__icon kpi-card__icon--' . htmlspecialchars($variant) . '">';
    echo '<i class="bi ' . htmlspecialchars($icon) . '" aria-hidden="true"></i></div>';
    echo '<div class="kpi-card__content">';
    echo '<div class="kpi-card__label">' . htmlspecialchars($label) . '</div>';
    echo '<div class="kpi-card__value">' . htmlspecialchars((string)$value) . '</div>';
    if ($change) {
        echo '<div class="kpi-card__change text-secondary">' . htmlspecialchars($change) . '</div>';
    }
    echo '</div></div>';
}

function render_status_badge(string $status): void {
    if ($status === 'borrowed' || $status === 'pending') $status = 'borrowing';
    $map = [
        'borrowing' => ['status-badge--borrowing', 'bi-clock', 'กำลังยืม'],
        'waiting_return_approval' => ['status-badge--waiting', 'bi-hourglass-split', 'รอตรวจสอบ'],
        'returned' => ['status-badge--returned', 'bi-check-circle', 'คืนสำเร็จ'],
    ];
    $s = $map[$status] ?? $map['borrowing'];
    echo '<span class="status-badge ' . $s[0] . '"><i class="bi ' . $s[1] . '"></i> ' . $s[2] . '</span>';
}

function render_empty_state(string $icon, string $title, string $desc = '', string $actionHtml = ''): void {
    echo '<div class="empty-state">';
    echo '<div class="empty-state__icon"><i class="bi ' . htmlspecialchars($icon) . '"></i></div>';
    echo '<div class="empty-state__title">' . htmlspecialchars($title) . '</div>';
    if ($desc) echo '<p class="empty-state__desc">' . htmlspecialchars($desc) . '</p>';
    if ($actionHtml) echo $actionHtml;
    echo '</div>';
}

function normalize_borrow_status(string $status): string {
    if ($status === 'borrowed' || $status === 'pending') return 'borrowing';
    return $status;
}

function swal_script(string $config): string {
    return '<script data-page-init="swal">' . $config . '</script>';
}
