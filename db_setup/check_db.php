<?php
require 'config.php';

echo "<h2>Database Content Check</h2>";

// Check if roles exist
echo "<h3>Roles:</h3>";
$stmt = $pdo->query("SELECT * FROM roles");
$roles = $stmt->fetchAll();
if ($roles) {
    foreach ($roles as $role) {
        echo "ID: " . $role['id'] . " | Name: " . $role['name'] . " | Description: " . $role['description'] . "<br>";
    }
} else {
    echo "No roles found<br>";
}

// Check if users exist
echo "<h3>Users:</h3>";
$stmt = $pdo->query("SELECT * FROM users");
$users = $stmt->fetchAll();
if ($users) {
    foreach ($users as $user) {
        echo "ID: " . $user['id'] . " | Username: " . $user['username'] . " | Email: " . $user['email'] . " | Active: " . $user['is_active'] . "<br>";
    }
} else {
    echo "No users found<br>";
}

// Check if student profiles exist
echo "<h3>Student Profiles:</h3>";
$stmt = $pdo->query("SELECT sp.*, u.username FROM student_profile sp JOIN users u ON sp.user_id = u.id");
$students = $stmt->fetchAll();
if ($students) {
    foreach ($students as $student) {
        echo "User ID: " . $student['user_id'] . " | Username: " . $student['username'] . " | Student Number: " . $student['student_number'] . " | Full Name: " . $student['full_name'] . "<br>";
    }
} else {
    echo "No student profiles found<br>";
}

// Check user roles
echo "<h3>User Roles:</h3>";
$stmt = $pdo->query("SELECT ur.user_id, ur.role_id, u.username, r.name as role_name FROM user_roles ur JOIN users u ON ur.user_id = u.id JOIN roles r ON ur.role_id = r.id");
$userRoles = $stmt->fetchAll();
if ($userRoles) {
    foreach ($userRoles as $userRole) {
        echo "User ID: " . $userRole['user_id'] . " | Username: " . $userRole['username'] . " | Role ID: " . $userRole['role_id'] . " | Role: " . $userRole['role_name'] . "<br>";
    }
} else {
    echo "No user roles found<br>";
}

echo "<h3>Test Student Login Query:</h3>";
$studnum = 'LSC000001';
$stmt = $pdo->prepare("SELECT u.id, u.password_hash FROM users u 
                       JOIN student_profile s ON u.id = s.user_id 
                       WHERE s.student_number = ? LIMIT 1");
$stmt->execute([$studnum]);
$user = $stmt->fetch();

if ($user) {
    echo "Found user with student number $studnum:<br>";
    echo "User ID: " . $user['id'] . "<br>";
    echo "Password hash: " . $user['password_hash'] . "<br>";
    
    // Test password verification
    $testPassword = 'LSC000001';
    if (empty($user['password_hash']) || password_verify($testPassword, $user['password_hash'])) {
        echo "Password verification: SUCCESS<br>";
    } else {
        echo "Password verification: FAILED<br>";
    }
} else {
    echo "No user found with student number $studnum<br>";
}
?>