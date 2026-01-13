<?php
require '../config.php';

echo "Adding new fields to employees table...\n";

try {
    // Check if nrc_number column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'nrc_number'");
    $nrc_exists = $stmt->fetch();
    
    if (!$nrc_exists) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN nrc_number VARCHAR(50) DEFAULT NULL");
        echo "Added nrc_number column\n";
    } else {
        echo "nrc_number column already exists\n";
    }
    
    // Check if tax_pin column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'tax_pin'");
    $tax_pin_exists = $stmt->fetch();
    
    if (!$tax_pin_exists) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN tax_pin VARCHAR(50) DEFAULT NULL");
        echo "Added tax_pin column\n";
    } else {
        echo "tax_pin column already exists\n";
    }
    
    // Check if cv_path column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'cv_path'");
    $cv_exists = $stmt->fetch();
    
    if (!$cv_exists) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN cv_path VARCHAR(255) DEFAULT NULL");
        echo "Added cv_path column\n";
    } else {
        echo "cv_path column already exists\n";
    }
    
    echo "All required fields have been added to the employees table.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>