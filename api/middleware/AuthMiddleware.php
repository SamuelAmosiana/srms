<?php
require_once '../security/Auth.php';

/**
 * Authentication Middleware
 */
class AuthMiddleware {
    
    /**
     * Require authentication
     */
    public static function requireAuth() {
        if (!Auth::isAuthenticated()) {
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'Authentication required'
            ]);
            exit();
        }
    }
    
    /**
     * Require specific role
     */
    public static function requireRole($roles) {
        if (!Auth::isAuthenticated()) {
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'Authentication required'
            ]);
            exit();
        }
        
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        
        $userRole = Auth::getUserRole();
        if (!in_array($userRole, $roles)) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Insufficient permissions'
            ]);
            exit();
        }
    }
    
    /**
     * Require specific permission
     */
    public static function requirePermission($resource, $action = 'read') {
        if (!Auth::isAuthenticated()) {
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'Authentication required'
            ]);
            exit();
        }
        
        if (!Auth::hasPermission($resource, $action)) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Insufficient permissions for ' . $resource . ' ' . $action
            ]);
            exit();
        }
    }
    
    /**
     * Check if user can access specific resource
     */
    public static function canAccess($resource, $action = 'read', $userId = null) {
        if (!Auth::isAuthenticated()) {
            return false;
        }
        
        // For student data, only allow access to own data
        if ($resource === 'students' && $action === 'read' && $userId) {
            $currentUser = Auth::getCurrentUser();
            if ($currentUser['role'] === 'student') {
                return $currentUser['id'] == $userId;
            }
        }
        
        return Auth::hasPermission($resource, $action);
    }
    
    /**
     * Get current user info for response
     */
    public static function getCurrentUserInfo() {
        return Auth::getCurrentUser();
    }
    
    /**
     * Validate request for security
     */
    public static function validateRequest() {
        // Check for potential security threats
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
            // Check for CSRF token in POST/PUT requests
            $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            
            if (!$csrfToken || !Auth::validateCSRF($csrfToken)) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid CSRF token'
                ]);
                exit();
            }
        }
    }
}
?>