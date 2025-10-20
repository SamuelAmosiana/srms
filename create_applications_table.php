<?php
require 'config.php';

try {
    // Create applications table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        programme_id INT,
        intake_id INT,
        documents TEXT,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        rejection_reason TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (programme_id) REFERENCES programme(id) ON DELETE SET NULL,
        FOREIGN KEY (intake_id) REFERENCES intake(id) ON DELETE SET NULL
    )");
    
    echo "Applications table created or already exists.\n";
    
    // Create pending_students table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS pending_students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        programme_id INT,
        intake_id INT,
        documents TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    echo "Pending students table created or already exists.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>