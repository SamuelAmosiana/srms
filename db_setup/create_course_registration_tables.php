<?php
require_once '../config.php';

try {
    echo "Creating course_registration table...\n";
    
    // Create course_registration table
    $pdo->exec("CREATE TABLE IF NOT EXISTS course_registration (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        course_id INT NOT NULL,
        term VARCHAR(50) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        rejection_reason TEXT,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES course(id) ON DELETE CASCADE
    )");
    
    echo "✅ course_registration table created successfully\n";
    
    echo "Creating intake_courses table...\n";
    
    // Create intake_courses table
    $pdo->exec("CREATE TABLE IF NOT EXISTS intake_courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        intake_id INT NOT NULL,
        term VARCHAR(50) NOT NULL,
        course_id INT NOT NULL,
        FOREIGN KEY (intake_id) REFERENCES intake(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES course(id) ON DELETE CASCADE
    )");
    
    echo "✅ intake_courses table created successfully\n";
    
    echo "\n🎉 All course registration tables created successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error creating tables: " . $e->getMessage() . "\n";
}
?>