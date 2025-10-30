<?php
// Debug script to understand the user list issue
require 'config.php';

echo "Debugging User List Issue\n";
echo "========================\n\n";

// Test the user list query
echo "1. Testing user list query:\n";
try {
    $query = "SELECT u.id as user_id, u.username, u.is_active, u.created_at, r.name as role_name,
              COALESCE(ap.full_name, sp.full_name, stp.full_name) as full_name,
              u.email,
              COALESCE(ap.staff_id, sp.student_number, stp.staff_id) as identifier
              FROM users u 
              JOIN user_roles ur ON u.id = ur.user_id
              JOIN roles r ON ur.role_id = r.id 
              LEFT JOIN admin_profile ap ON u.id = ap.user_id
              LEFT JOIN student_profile sp ON u.id = sp.user_id
              LEFT JOIN staff_profile stp ON u.id = stp.user_id
              WHERE 1=1
              ORDER BY u.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    echo "Users found: " . count($users) . "\n\n";
    
    foreach ($users as $user) {
        echo "User ID: " . $user['user_id'] . "\n";
        echo "Username: " . $user['username'] . "\n";
        echo "Full Name: " . ($user['full_name'] ?? 'NULL') . "\n";
        echo "Role: " . $user['role_name'] . "\n";
        echo "Identifier: " . ($user['identifier'] ?? 'NULL') . "\n";
        echo "---\n";
    }
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\n2. Testing individual profile tables:\n";
try {
    echo "Admin profiles:\n";
    $stmt = $pdo->query("SELECT user_id, full_name, staff_id FROM admin_profile");
    $admins = $stmt->fetchAll();
    foreach ($admins as $admin) {
        echo "- User ID: " . $admin['user_id'] . ", Name: " . ($admin['full_name'] ?? 'NULL') . ", Staff ID: " . ($admin['staff_id'] ?? 'NULL') . "\n";
    }
    
    echo "\nStudent profiles:\n";
    $stmt = $pdo->query("SELECT user_id, full_name, student_number FROM student_profile");
    $students = $stmt->fetchAll();
    foreach ($students as $student) {
        echo "- User ID: " . $student['user_id'] . ", Name: " . ($student['full_name'] ?? 'NULL') . ", Student #: " . ($student['student_number'] ?? 'NULL') . "\n";
    }
    
    echo "\nStaff profiles:\n";
    $stmt = $pdo->query("SELECT user_id, full_name, staff_id FROM staff_profile");
    $staff = $stmt->fetchAll();
    foreach ($staff as $member) {
        echo "- User ID: " . $member['user_id'] . ", Name: " . ($member['full_name'] ?? 'NULL') . ", Staff ID: " . ($member['staff_id'] ?? 'NULL') . "\n";
    }
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\n3. Testing user with specific ID:\n";
$testUserId = 1;
try {
    echo "Testing user ID: $testUserId\n";
    
    // Check user roles
    $stmt = $pdo->prepare("SELECT r.name FROM roles r JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ?");
    $stmt->execute([$testUserId]);
    $roleName = $stmt->fetchColumn();
    echo "Role: " . ($roleName ?? 'NULL') . "\n";
    
    // Check each profile table for this user
    $stmt = $pdo->prepare("SELECT full_name, staff_id FROM admin_profile WHERE user_id = ?");
    $stmt->execute([$testUserId]);
    $adminProfile = $stmt->fetch();
    echo "Admin profile: " . print_r($adminProfile, true) . "\n";
    
    $stmt = $pdo->prepare("SELECT full_name, student_number FROM student_profile WHERE user_id = ?");
    $stmt->execute([$testUserId]);
    $studentProfile = $stmt->fetch();
    echo "Student profile: " . print_r($studentProfile, true) . "\n";
    
    $stmt = $pdo->prepare("SELECT full_name, staff_id FROM staff_profile WHERE user_id = ?");
    $stmt->execute([$testUserId]);
    $staffProfile = $stmt->fetch();
    echo "Staff profile: " . print_r($staffProfile, true) . "\n";
    
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>