<?php
require '../config.php';
require 'auth.php';

echo "Testing authentication functions:\n";

$user_id = currentUserId();
if ($user_id) {
    echo "User is logged in with ID: " . $user_id . "\n";
} else {
    echo "User is NOT logged in\n";
}

// Check if user has HR Manager role
if ($user_id) {
    $stmt = $pdo->prepare("
        SELECT r.name 
        FROM users u 
        JOIN user_roles ur ON u.id = ur.user_id 
        JOIN roles r ON ur.role_id = r.id 
        WHERE u.id = ? AND r.name = 'HR Manager'
    ");
    $stmt->execute([$user_id]);
    $hrRole = $stmt->fetch();

    if ($hrRole) {
        echo "User has HR Manager role: " . $hrRole['name'] . "\n";
    } else {
        echo "User does NOT have HR Manager role\n";
        
        // Let's see what roles the user does have
        $stmt = $pdo->prepare("
            SELECT r.name 
            FROM users u 
            JOIN user_roles ur ON u.id = ur.user_id 
            JOIN roles r ON ur.role_id = r.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        $allRoles = $stmt->fetchAll();
        
        if (count($allRoles) > 0) {
            echo "User roles: ";
            foreach($allRoles as $role) {
                echo $role['name'] . ", ";
            }
            echo "\n";
        } else {
            echo "User has no roles\n";
        }
    }
}
?>