<?php
session_start();

// Base URL - Change this according to your setup
define('BASE_URL', 'http://localhost/posflix');

// App settings
define('APP_NAME', 'POSFlix');
define('APP_VERSION', '1.0.0');

// Session timeout in seconds (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Upload settings
define('UPLOAD_PATH', dirname(__DIR__) . '/uploads');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once 'database.php';

// Helper functions
function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit();
}

function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        redirect('/login.php');
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        redirect('/login.php?msg=timeout');
    }
    $_SESSION['last_activity'] = time();
}

function checkPermission($role) {
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== $role) {
        redirect('/dashboard.php?msg=unauthorized');
    }
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateReference($prefix = '') {
    return $prefix . date('Ymd') . rand(1000, 9999);
}

function formatCurrency($amount) {
    return number_format($amount, 2);
}

function logAudit($userId, $action, $module, $description) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, module, description, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $module, $description, $_SERVER['REMOTE_ADDR']]);
}
?>