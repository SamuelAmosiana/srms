<?php
/**
 * Security Configuration for API
 */
class SecurityConfig {
    
    // Session security settings
    public static function configureSessionSecurity() {
        // Only allow cookies for session storage
        ini_set('session.use_only_cookies', 1);
        
        // Prevent session ID in URLs
        ini_set('session.use_trans_sid', 0);
        
        // Enable strict session mode
        ini_set('session.use_strict_mode', 1);
        
        // Set secure cookie flag (should be 1 in production with HTTPS)
        ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
        
        // Prevent XSS attacks by setting HttpOnly flag
        ini_set('session.cookie_httponly', 1);
        
        // Prevent CSRF with SameSite attribute (if supported)
        ini_set('session.cookie_samesite', 'Strict');
        
        // Session timeout (30 minutes)
        ini_set('session.gc_maxlifetime', 1800);
    }
    
    // Password hashing settings
    public static function getPasswordHashOptions() {
        return [
            'cost' => 12, // Adjust as needed for performance vs security
        ];
    }
    
    // Rate limiting settings
    public static function getRateLimitSettings() {
        return [
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
            'block_duration_minutes' => 15
        ];
    }
    
    // Input validation rules
    public static function getValidationRules() {
        return [
            'username' => [
                'pattern' => '/^[a-zA-Z0-9_]{3,20}$/',
                'message' => 'Username must be 3-20 characters, alphanumeric and underscore only'
            ],
            'email' => [
                'pattern' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
                'message' => 'Invalid email format'
            ],
            'password' => [
                'min_length' => 8,
                'require_uppercase' => true,
                'require_lowercase' => true,
                'require_numbers' => true,
                'require_special_chars' => false,
                'message' => 'Password must be at least 8 characters'
            ]
        ];
    }
    
    // Security headers
    public static function setSecurityHeaders() {
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Prevent loading in iframes (clickjacking protection)
        header('X-Frame-Options: DENY');
        
        // Basic XSS protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Strict transport security (only if using HTTPS)
        // header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    // Initialize security measures
    public static function initializeSecurity() {
        self::configureSessionSecurity();
        self::setSecurityHeaders();
    }
}

// Initialize security when file is included
SecurityConfig::initializeSecurity();
?>