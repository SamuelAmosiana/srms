<?php
require 'config.php';

try {
    // Get all tables
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll();
    
    echo "All database tables:\n";
    foreach ($tables as $table) {
        $tableName = array_values($table)[0];
        echo "- " . $tableName . "\n";
    }
    
    // Check if any fee-related tables exist
    echo "\nChecking for fee-related tables:\n";
    $feeTables = [];
    foreach ($tables as $table) {
        $tableName = array_values($table)[0];
        if (stripos($tableName, 'fee') !== false) {
            $feeTables[] = $tableName;
        }
    }
    
    if (empty($feeTables)) {
        echo "No fee-related tables found.\n";
    } else {
        foreach ($feeTables as $table) {
            echo "- " . $table . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>