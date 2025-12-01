<?php
require_once 'config.php';

echo "Updating database schema to add finance clearance columns...\n";

try {
    // Add finance clearance columns to course_registration table
    echo "Adding finance clearance columns to course_registration table...\n";
    $pdo->exec("ALTER TABLE course_registration ADD COLUMN IF NOT EXISTS finance_cleared TINYINT(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE course_registration ADD COLUMN IF NOT EXISTS finance_cleared_at TIMESTAMP NULL");
    $pdo->exec("ALTER TABLE course_registration ADD COLUMN IF NOT EXISTS finance_cleared_by INT NULL");
    echo "✅ Finance clearance columns added to course_registration table\n";
    
    // Add finance clearance columns to pending_students table
    echo "Adding finance clearance columns to pending_students table...\n";
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS finance_cleared TINYINT(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS finance_cleared_at TIMESTAMP NULL");
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS finance_cleared_by INT NULL");
    echo "✅ Finance clearance columns added to pending_students table\n";
    
    // Add registration_status column to pending_students if it doesn't exist
    echo "Ensuring registration_status column exists in pending_students table...\n";
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS registration_status ENUM('pending','pending_approval','approved','rejected') DEFAULT 'pending'");
    echo "✅ registration_status column ensured in pending_students table\n";
    
    // Add student_number column to pending_students if it doesn't exist
    echo "Ensuring student_number column exists in pending_students table...\n";
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS student_number VARCHAR(50)");
    echo "✅ student_number column ensured in pending_students table\n";
    
    echo "\n🎉 Database schema updated successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error updating database schema: " . $e->getMessage() . "\n";
}
?>