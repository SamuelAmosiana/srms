<?php
require 'config.php';

function currentUserId() {
    return $_SESSION['user_id'] ?? null;
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
function currentUserHasPermission($permissionName, $pdo) {
    if (!currentUserId()) return false;
    
    // Check for user-specific permission override first
    $stmt = $pdo->prepare("SELECT granted FROM user_permissions WHERE user_id = ? AND permission_id = (SELECT id FROM permissions WHERE name = ?)");
    $stmt->execute([currentUserId(), $permissionName]);
    $userPermission = $stmt->fetchColumn();
    
    if ($userPermission !== false) {
        return (bool)$userPermission;
    }
    
    // Fall back to role-based permissions
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM role_permissions rp 
        JOIN user_roles ur ON rp.role_id = ur.role_id 
        JOIN permissions p ON rp.permission_id = p.id 
        WHERE ur.user_id = ? AND p.name = ?
    ");
    $stmt->execute([currentUserId(), $permissionName]);
    
    return $stmt->fetchColumn() > 0;
}

function requirePermission($permissionName, $pdo) {
    if (!currentUserHasPermission($permissionName, $pdo)) {
        http_response_code(403);
        die('Forbidden - insufficient permissions to access this resource');
    }
}

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
    
    // Get user-specific permission overrides
    $stmt = $pdo->prepare("
        SELECT p.name, up.granted FROM permissions p
        JOIN user_permissions up ON p.id = up.permission_id
        WHERE up.user_id = ?
    ");
    $stmt->execute([currentUserId()]);
    $userOverrides = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Apply user overrides to role permissions
    $finalPermissions = [];
    foreach ($rolePermissions as $permission) {
        if (isset($userOverrides[$permission])) {
            if ($userOverrides[$permission]) {
                $finalPermissions[] = $permission;
            }
        } else {
            $finalPermissions[] = $permission;
        }
    }
    
    // Add user-specific granted permissions
    foreach ($userOverrides as $permission => $granted) {
        if ($granted && !in_array($permission, $finalPermissions)) {
            $finalPermissions[] = $permission;
        }
    }
    
    return $finalPermissions;
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
