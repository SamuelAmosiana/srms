<?php
// Security configuration
// Only set session ini settings if no session is active
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);   // Prevent XSS attacks via session cookie
    ini_set('session.use_strict_mode', 1);   // Prevent session fixation
    ini_set('session.use_only_cookies', 1);  // Prevent session ID in URLs
    ini_set('session.cookie_secure', 0);     // Set to 1 in production with HTTPS
    ini_set('session.cookie_samesite', 'Strict'); // CSRF protection
}

// Database configuration
$DB_HOST = '127.0.0.1';
$DB_NAME = 'lscrms';
$DB_USER = 'root';
$DB_PASS = '';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,  // Use real prepared statements
];

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, $options);
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Start session with security
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    
    // Regenerate session ID periodically to prevent fixation
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    // Check for session hijacking
    if (($_SERVER['REMOTE_ADDR'] ?? '') !== $_SESSION['ip_address'] ||
        ($_SERVER['HTTP_USER_AGENT'] ?? '') !== $_SESSION['user_agent']) {
        session_destroy();
        die('Session hijacking attempt detected.');
    }
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

/**
 * Helper function to sanitize output
 */
if (!function_exists('h')) {
    function h($str) {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Helper function to validate CSRF tokens
 */
if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

?>