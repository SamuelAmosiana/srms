<?php
require_once 'config.php';

try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Existing tables in the database:\n";
    foreach ($tables as $table) {
        echo "- " . $table . "\n";
    }
    
    // Check for the specific tables we need
    echo "\nChecking for course registration tables:\n";
    $needed_tables = ['course_registration', 'intake_courses'];
    foreach ($needed_tables as $table) {
        if (in_array($table, $tables)) {
            echo "✅ $table table exists\n";
        } else {
            echo "❌ $table table does not exist\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>