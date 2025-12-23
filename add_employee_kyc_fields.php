<?php
require 'config.php';

echo "Adding KYC fields to employees table...\n";

try {
    // Check if address column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'address'");
    $address_exists = $stmt->fetch();
    
    if (!$address_exists) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN address VARCHAR(255) DEFAULT NULL");
        echo "Added address column\n";
    } else {
        echo "Address column already exists\n";
    }
    
    // Check if city column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'city'");
    $city_exists = $stmt->fetch();
    
    if (!$city_exists) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN city VARCHAR(100) DEFAULT NULL");
        echo "Added city column\n";
    } else {
        echo "City column already exists\n";
    }
    
    // Check if state column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'state'");
    $state_exists = $stmt->fetch();
    
    if (!$state_exists) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN state VARCHAR(100) DEFAULT NULL");
        echo "Added state column\n";
    } else {
        echo "State column already exists\n";
    }
    
    // Check if country column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'country'");
    $country_exists = $stmt->fetch();
    
    if (!$country_exists) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN country VARCHAR(100) DEFAULT NULL");
        echo "Added country column\n";
    } else {
        echo "Country column already exists\n";
    }
    
    // Check if postal_code column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'postal_code'");
    $postal_code_exists = $stmt->fetch();
    
    if (!$postal_code_exists) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN postal_code VARCHAR(20) DEFAULT NULL");
        echo "Added postal_code column\n";
    } else {
        echo "Postal code column already exists\n";
    }
    
    // Check if bank_name column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'bank_name'");
    $bank_name_exists = $stmt->fetch();
    
    if (!$bank_name_exists) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN bank_name VARCHAR(100) DEFAULT NULL");
        echo "Added bank_name column\n";
    } else {
        echo "Bank name column already exists\n";
    }
    
    // Check if account_number column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'account_number'");
    $account_number_exists = $stmt->fetch();
    
    if (!$account_number_exists) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN account_number VARCHAR(50) DEFAULT NULL");
        echo "Added account_number column\n";
    } else {
        echo "Account number column already exists\n";
    }
    
    // Check if account_name column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'account_name'");
    $account_name_exists = $stmt->fetch();
    
    if (!$account_name_exists) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN account_name VARCHAR(100) DEFAULT NULL");
        echo "Added account_name column\n";
    } else {
        echo "Account name column already exists\n";
    }
    
    // Check if branch_code column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'branch_code'");
    $branch_code_exists = $stmt->fetch();
    
    if (!$branch_code_exists) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN branch_code VARCHAR(50) DEFAULT NULL");
        echo "Added branch_code column\n";
    } else {
        echo "Branch code column already exists\n";
    }
    
    echo "\nAll KYC fields have been added successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>