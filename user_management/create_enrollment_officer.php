<?php
require '../config.php';

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Create a new user for the enrollment officer
    $username = 'enrollment@lsc.ac.zm';
    $email = 'enrollment@lsc.ac.zm';
    $password = password_hash('enrollment123', PASSWORD_DEFAULT); // Default password
    
    // Check if user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        echo "User already exists with ID: " . $existingUser['id'] . "\n";
        $userId = $existingUser['id'];
    } else {
        // Insert new user
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, is_active) VALUES (?, ?, ?, 1)");
        $stmt->execute([$username, $email, $password]);
        $userId = $pdo->lastInsertId();
        echo "Created new user with ID: " . $userId . "\n";
    }
    
    // Get the Enrollment Officer role ID
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
    $stmt->execute(['Enrollment Officer']);
    $role = $stmt->fetch();
    
    if (!$role) {
        throw new Exception("Enrollment Officer role not found!");
    }
    
    $roleId = $role['id'];
    echo "Found Enrollment Officer role with ID: " . $roleId . "\n";
    
    // Assign role to user
    $stmt = $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)");
    $stmt->execute([$userId, $roleId]);
    echo "Assigned Enrollment Officer role to user\n";
    
    // Create staff profile if it doesn't exist
    $stmt = $pdo->prepare("SELECT user_id FROM staff_profile WHERE user_id = ?");
    $stmt->execute([$userId]);
    $existingProfile = $stmt->fetch();
    
    if (!$existingProfile) {
        $stmt = $pdo->prepare("INSERT INTO staff_profile (user_id, full_name, staff_id, NRC, gender, qualification) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, 'Enrollment Officer', 'ENR001', '123456/78/90', 'Male', 'Bachelor of Education']);
        echo "Created staff profile for enrollment officer\n";
    } else {
        echo "Staff profile already exists\n";
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo "Enrollment Officer user created successfully!\n";
    echo "Username: " . $username . "\n";
    echo "Password: enrollment123\n";
    echo "Role: Enrollment Officer\n";
    
} catch (Exception $e) {
    $pdo->rollback();
    echo "Error: " . $e->getMessage() . "\n";
}
?>