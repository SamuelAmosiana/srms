<?php
require '../config.php';

try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO roles (name, description) VALUES (?, ?)");
    $stmt->execute(['Enrollment Officer', 'Handles student enrollment applications']);
    
    // Check if the role was added
    $stmt = $pdo->prepare("SELECT id, name FROM roles WHERE name = ?");
    $stmt->execute(['Enrollment Officer']);
    $role = $stmt->fetch();
    
    if ($role) {
        echo "Enrollment Officer role added successfully with ID: " . $role['id'];
    } else {
        echo "Failed to add Enrollment Officer role";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>