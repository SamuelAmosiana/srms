<?php
require_once '../config.php';

echo "Dropping existing tables if they exist...\n";

// Drop existing tables
$pdo->exec('DROP TABLE IF EXISTS payments');
$pdo->exec('DROP TABLE IF EXISTS finance_income');
$pdo->exec('DROP TABLE IF EXISTS payment_logs');

echo "Creating new payment system tables...\n";

try {
    // Create payments table
    $sql = "
    CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_reference VARCHAR(50) UNIQUE NOT NULL,
        full_name VARCHAR(255) NOT NULL,
        phone_number VARCHAR(20) NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        category ENUM('Application Fee', 'Tuition / School Fees', 'Other') NOT NULL,
        description TEXT,
        student_or_application_id VARCHAR(50),
        status ENUM('pending', 'confirmed', 'rejected') DEFAULT 'pending',
        source ENUM('public', 'srms') DEFAULT 'public',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    echo "✓ Payments table created\n";
    
    // Create finance_income table
    $sql = "
    CREATE TABLE IF NOT EXISTS finance_income (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_reference VARCHAR(50) NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        category ENUM('Application Fee', 'Tuition / School Fees', 'Other') NOT NULL,
        recorded_by INT,
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    echo "✓ Finance income table created\n";
    
    // Create payment_logs table (optional but recommended)
    $sql = "
    CREATE TABLE IF NOT EXISTS payment_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_reference VARCHAR(50) NOT NULL,
        raw_response TEXT,
        action_taken ENUM('initiated', 'confirmed', 'rejected', 'failed', 'callback_received', 'unknown_status', 'error', 'test_entry'),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    echo "✓ Payment logs table created\n";
    
    echo "\nDatabase migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>