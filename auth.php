<?php
require 'config.php';

// Create emergency access table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS maintenance_emergency_access (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        granted_by INT NOT NULL,
        granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NULL,
        reason TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_access (user_id)
    )");
} catch (Exception $e) {
    // Table might already exist
}

// Function to check if maintenance mode is enabled
function isMaintenanceModeEnabled($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result && $result['setting_value'] === '1';
    } catch (Exception $e) {
        return false;
    }
}

// Function to check if current user has emergency access during maintenance
function hasEmergencyAccess($pdo, $userId) {
    if (!$userId) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM maintenance_emergency_access WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())");
        $stmt->execute([$userId]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}

// Function to check if current user is an administrator
function isCurrentUserAdmin($pdo) {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return false;
    }
    
    // Check for user-specific permission override first (if table exists)
    try {
        $stmt = $pdo->prepare("SELECT r.name FROM roles r JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return in_array('Super Admin', $roles);
    } catch (Exception $e) {
        return false;
    }
}

// Check maintenance mode and restrict access if needed
function checkMaintenanceMode($pdo) {
    // If maintenance mode is enabled and user is not admin, show maintenance page
    if (isMaintenanceModeEnabled($pdo) && !isCurrentUserAdmin($pdo)) {
        // Check if user has emergency access
        $userId = $_SESSION['user_id'] ?? null;
        if (!hasEmergencyAccess($pdo, $userId)) {
            // Show maintenance mode page
            http_response_code(503);
            echo '<!DOCTYPE html>
<html>
<head>
    <title>Maintenance Mode - LSC SRMS</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
            background-color: #f5f5f5;
        }
        .maintenance-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        p {
            color: #666;
            font-size: 18px;
            line-height: 1.6;
        }
        .icon {
            font-size: 64px;
            color: #ff9800;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="icon">⚙️</div>
        <h1>System Maintenance in Progress</h1>
        <p>The Student Records Management System is currently undergoing maintenance. We apologize for any inconvenience.</p>
        <p>Please try again later.</p>
        <p><em>- Lusaka South College IT Team</em></p>
    </div>
</body>
</html>';
            exit();
        }
    }
}

// Run maintenance mode check on every page load (except for login and settings pages)
$currentScript = basename($_SERVER['SCRIPT_NAME']);
if (!in_array($currentScript, ['login.php', 'settings.php'])) {
    checkMaintenanceMode($pdo);
}

function currentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Get user information
function get_user_info($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Check if user has specific permission
function has_permission($permission_name) {
    global $pdo;
    return currentUserHasPermission($permission_name, $pdo);
}

function currentUserHasRole($roleName, $pdo) {
    if (!isset($_SESSION['roles'])) {
        if (!currentUserId()) return false;
        $stmt = $pdo->prepare("SELECT r.name FROM roles r JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ?");
        $stmt->execute([currentUserId()]);
        $_SESSION['roles'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    return in_array($roleName, $_SESSION['roles']);
}

function requireRole($roleName, $pdo) {
    if (!currentUserHasRole($roleName, $pdo)) {
        http_response_code(403);
        die('Forbidden - insufficient permissions');
    }
}

// Enhanced permission checking functions

// Get all permissions for current user
function getCurrentUserPermissions($pdo) {
    if (!currentUserId()) return [];
    
    // Get role-based permissions
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.name FROM permissions p
        JOIN role_permissions rp ON p.id = rp.permission_id
        JOIN user_roles ur ON rp.role_id = ur.role_id
        WHERE ur.user_id = ?
    ");
    $stmt->execute([currentUserId()]);
    $rolePermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get user-specific permission overrides (if table exists)
    $userOverrides = [];
    try {
        $stmt = $pdo->prepare("
            SELECT p.name, up.granted FROM permissions p
            JOIN user_permissions up ON p.id = up.permission_id
            WHERE up.user_id = ?
        ");
        $stmt->execute([currentUserId()]);
        $userOverrides = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        // user_permissions table might not exist, ignore error
    }
    
    // Merge role permissions with user overrides
    $finalPermissions = [];
    foreach ($rolePermissions as $perm) {
        // If user override exists and it's denied (0), skip this permission
        if (isset($userOverrides[$perm]) && $userOverrides[$perm] == 0) {
            continue;
        }
        $finalPermissions[] = $perm;
    }
    
    // Add any additional permissions that were explicitly granted to the user
    foreach ($userOverrides as $perm => $granted) {
        if ($granted == 1 && !in_array($perm, $rolePermissions)) {
            $finalPermissions[] = $perm;
        }
    }
    
    return array_unique($finalPermissions);
}

// Check if current user has a specific permission
function currentUserHasPermission($permissionName, $pdo) {
    $permissions = getCurrentUserPermissions($pdo);
    return in_array($permissionName, $permissions);
}

function requirePermission($permissionName, $pdo) {
    if (!currentUserHasPermission($permissionName, $pdo)) {
        http_response_code(403);
        die('Forbidden - insufficient permissions to access this resource');
    }
}

// Check if current user can manage a specific user (for part-time/intern restrictions)
function canManageUser($targetUserId, $pdo) {
    if (!currentUserId()) return false;
    
    // Super Admin can manage everyone
    if (currentUserHasRole('Super Admin', $pdo)) {
        return true;
    }
    
    // Users can't manage themselves through this function
    if (currentUserId() == $targetUserId) {
        return false;
    }
    
    // Check if user has manage_users permission
    return currentUserHasPermission('manage_users', $pdo);
}

// Logout function
function logout() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    header('Location: login.php');
    exit;
}
?>