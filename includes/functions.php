<?php
/**
 * Common Utility Functions
 * Helper functions used across the application
 * Following SDLC best practices for code reusability
 */

/**
 * Sanitize input data
 * @param string $data Input data to sanitize
 * @return string Sanitized data
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate email format
 * @param string $email Email to validate
 * @return bool True if valid, false otherwise
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate CSRF token
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool True if valid, false otherwise
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Redirect with message
 * @param string $url URL to redirect to
 * @param string $message Message to display
 * @param string $type Message type (success, error, warning, info)
 */
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit;
}

/**
 * Display flash message
 * @return string HTML for flash message or empty string
 */
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        return "<div class=\"alert alert-{$type} alert-dismissible fade show\" role=\"alert\">
                    {$message}
                    <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button>
                </div>";
    }
    return '';
}

/**
 * Check if user is logged in
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user has specific role
 * @param string $role Role to check
 * @return bool True if user has role, false otherwise
 */
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Require authentication
 * Redirects to login if not logged in
 */
function requireAuth() {
    if (!isLoggedIn()) {
        redirectWithMessage('login.php', 'Please log in to access this page', 'warning');
    }
}

/**
 * Require specific role
 * @param string|array $roles Required role(s)
 */
function requireRole($roles) {
    requireAuth();
    $roles = is_array($roles) ? $roles : [$roles];
    if (!in_array($_SESSION['role'], $roles)) {
        redirectWithMessage('login.php', 'Access denied. Insufficient permissions.', 'error');
    }
}

/**
 * Format date for display
 * @param string $date Date to format
 * @param string $format Format string (default: Y-m-d H:i:s)
 * @return string Formatted date
 */
function formatDate($date, $format = 'Y-m-d H:i:s') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

/**
 * Upload file
 * @param array $file File from $_FILES
 * @param string $targetDir Target directory
 * @param array $allowedTypes Allowed file extensions
 * @return array ['success' => bool, 'filename' => string|null, 'error' => string|null]
 */
function uploadFile($file, $targetDir = 'Uploads/', $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf']) {
    $result = ['success' => false, 'filename' => null, 'error' => null];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = 'File upload error: ' . $file['error'];
        return $result;
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowedTypes)) {
        $result['error'] = 'File type not allowed. Allowed: ' . implode(', ', $allowedTypes);
        return $result;
    }
    
    // Create directory if it doesn't exist
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . basename($file['name']);
    $targetPath = $targetDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $result['success'] = true;
        $result['filename'] = $filename;
    } else {
        $result['error'] = 'Failed to move uploaded file';
    }
    
    return $result;
}

/**
 * Paginate results
 * @param int $total Total number of records
 * @param int $perPage Records per page
 * @param int $currentPage Current page number
 * @return array ['offset' => int, 'limit' => int, 'totalPages' => int, 'currentPage' => int]
 */
function paginate($total, $perPage = 10, $currentPage = 1) {
    $currentPage = max(1, (int)$currentPage);
    $totalPages = ceil($total / $perPage);
    $offset = ($currentPage - 1) * $perPage;
    
    return [
        'offset' => $offset,
        'limit' => $perPage,
        'totalPages' => $totalPages,
        'currentPage' => $currentPage,
        'total' => $total
    ];
}

/**
 * Log activity
 * @param string $action Action performed
 * @param int $userId User ID
 * @param string $details Additional details
 */
function logActivity($action, $userId, $details = '') {
    $db = getDB();
    try {
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$userId, $action, $details]);
    } catch (PDOException $e) {
        error_log("Activity logging failed: " . $e->getMessage());
    }
}

/**
 * Convert position code to Thai label
 * @param string $pos Position code
 * @param string $other Custom position text
 * @return string Thai label
 */
function positionLabel($pos, $other = '') {
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

/**
 * Calculate number of days between two dates
 * @param string $startDate Start date (Y-m-d)
 * @param string $endDate End date (Y-m-d)
 * @return int Number of days
 */
function calculateDays($startDate, $endDate) {
    if (empty($startDate) || empty($endDate)) return 0;
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $interval = $start->diff($end);
    return (int)$interval->days + 1; // Include both start and end day
}
