<?php
require_once '../config.php';

try {
    // Create academic_sessions table
    $sql = "
    CREATE TABLE IF NOT EXISTS academic_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_name VARCHAR(100) NOT NULL,
        academic_year VARCHAR(20) NOT NULL,
        term VARCHAR(50) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql);
    echo "academic_sessions table created successfully!\n";

    // Create programme_schedule table
    $sql = "
    CREATE TABLE IF NOT EXISTS programme_schedule (
        id INT AUTO_INCREMENT PRIMARY KEY,
        programme_id INT NOT NULL,
        session_id INT NOT NULL,
        intake_id INT NOT NULL,
        year_of_study INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (programme_id) REFERENCES programme(id) ON DELETE CASCADE,
        FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
        FOREIGN KEY (intake_id) REFERENCES intake(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql);
    echo "programme_schedule table created successfully!\n";

    echo "All sessions tables have been created successfully!\n";
    
} catch (PDOException $e) {
    echo "Error creating tables: " . $e->getMessage() . "\n";
}
?>