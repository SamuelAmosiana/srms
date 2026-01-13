<?php
require_once 'SecurityConfig.php';
session_start();

/**
 * Authentication class for API
 */
class Auth {
    
    /**
     * Start session with security settings
     */
    public static function initSession() {
        SecurityConfig::configureSessionSecurity();
        
        // Regenerate session ID periodically to prevent fixation
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
        }
        
        // Store IP and user agent to prevent session hijacking
        if (!isset($_SESSION['ip_address']) || !isset($_SESSION['user_agent'])) {
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
    }
    
    /**
     * Authenticate user
     */
    public static function authenticate($username, $password, $pdo) {
        self::initSession();
        
        try {
            // Check if user exists in different user tables based on role
            $stmt = $pdo->prepare("
                SELECT u.*, 'admin' as role FROM users u WHERE u.username = ?
                UNION ALL
                SELECT u.*, 'student' as role FROM student_users u WHERE u.username = ?
                UNION ALL
                SELECT u.*, 'lecturer' as role FROM lecturer_users u WHERE u.username = ?
            ");
            
            $stmt->execute([$username, $username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Update session with user data
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = time();
                
                // Regenerate session ID after successful login
                session_regenerate_id(true);
                
                return [
                    'success' => true,
                    'user' => $user,
                    'token' => self::generateToken($user['id'])
                ];
            }
            
            return ['success' => false, 'message' => 'Invalid credentials'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Authentication error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generate secure token
     */
    public static function generateToken($userId) {
        $token = bin2hex(random_bytes(32));
        $expires = time() + (60 * 60 * 24); // 24 hours
        
        // Store token in database (you'd need to create a tokens table)
        // For now, we'll store in session
        $_SESSION['auth_token'] = $token;
        $_SESSION['token_expires'] = $expires;
        
        return $token;
    }
    
    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated() {
        self::initSession();
        
        // Check if session exists and is not expired
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
            return false;
        }
        
        // Check if session has been tampered with
        if (($_SERVER['REMOTE_ADDR'] ?? '') !== $_SESSION['ip_address'] ||
            ($_SERVER['HTTP_USER_AGENT'] ?? '') !== $_SESSION['user_agent']) {
            self::logout();
            return false;
        }
        
        // Check if token is valid
        if (!isset($_SESSION['auth_token']) || !isset($_SESSION['token_expires'])) {
            return false;
        }
        
        if (time() > $_SESSION['token_expires']) {
            self::logout();
            return false;
        }
        
        return true;
    }
    
    /**
     * Get current user data
     */
    public static function getCurrentUser() {
        if (!self::isAuthenticated()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role']
        ];
    }
    
    /**
     * Get current user role
     */
    public static function getUserRole() {
        $user = self::getCurrentUser();
        return $user ? $user['role'] : null;
    }
    
    /**
     * Check if user has specific role
     */
    public static function hasRole($role) {
        $userRole = self::getUserRole();
        return $userRole === $role;
    }
    
    /**
     * Check if user has permission for resource
     */
    public static function hasPermission($resource, $action = 'read') {
        $role = self::getUserRole();
        
        if (!$role) {
            return false;
        }
        
        // Define permissions based on role
        $permissions = [
            'admin' => [
                'students' => ['read', 'write', 'update', 'delete'],
                'programmes' => ['read', 'write', 'update', 'delete'],
                'courses' => ['read', 'write', 'update', 'delete'],
                'results' => ['read', 'write', 'update', 'delete'],
                'fees' => ['read', 'write', 'update', 'delete'],
            ],
            'student' => [
                'students' => ['read'], // Can only read own data
                'results' => ['read'],  // Can read own results
                'fees' => ['read'],     // Can read own fees
            ],
            'lecturer' => [
                'courses' => ['read'],
                'results' => ['read', 'write'], // Can read/write results for assigned courses
                'students' => ['read'], // Can read students in assigned courses
            ]
        ];
        
        if (isset($permissions[$role][$resource])) {
            return in_array($action, $permissions[$role][$resource]);
        }
        
        return false;
    }
    
    /**
     * Refresh token
     */
    public static function refreshToken() {
        if (!self::isAuthenticated()) {
            return false;
        }
        
        $expires = time() + (60 * 60 * 24); // 24 hours
        $_SESSION['token_expires'] = $expires;
        
        return true;
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        // Destroy session
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCSRF($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRF() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
}
?>