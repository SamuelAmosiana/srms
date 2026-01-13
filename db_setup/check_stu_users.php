<?php
require 'config.php';

try {
    // Check if there's a user with username or email matching the STU- format
    $stmt = $pdo->query("SELECT u.*, sp.student_number FROM users u LEFT JOIN student_profile sp ON u.id = sp.user_id WHERE u.username LIKE 'STU-%' OR u.email LIKE 'STU-%' OR sp.student_number LIKE 'STU-%'");
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "No users with STU- format found.\n";
    } else {
        echo "Found users with STU- format:\n";
        foreach ($users as $user) {
            echo "- User ID: " . $user['id'] . " | Username: " . $user['username'] . " | Email: " . $user['email'] . " | Student Number: " . ($user['student_number'] ?? 'N/A') . "\n";
        }
    }
    
    // Also check if there are any applications with STU- format
    $stmt = $pdo->query("SELECT * FROM applications WHERE full_name LIKE '%STU-%' OR email LIKE '%STU-%'");
    $applications = $stmt->fetchAll();
    
    if (!empty($applications)) {
        echo "\nFound applications that might be related:\n";
        foreach ($applications as $app) {
            echo "- App ID: " . $app['id'] . " | Name: " . $app['full_name'] . " | Email: " . $app['email'] . " | Status: " . $app['status'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>