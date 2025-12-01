<?php
require 'config.php';

echo "Checking course_registration table structure:\n";

try {
    $stmt = $pdo->query('DESCRIBE course_registration');
    $columns = $stmt->fetchAll();
    
    echo "Finance-related columns in course_registration:\n";
    foreach ($columns as $column) {
        if (strpos($column['Field'], 'finance_cleared') !== false) {
            echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
    }
    
    echo "\nChecking pending_students table structure:\n";
    $stmt = $pdo->query('DESCRIBE pending_students');
    $columns = $stmt->fetchAll();
    
    echo "Finance-related columns in pending_students:\n";
    foreach ($columns as $column) {
        if (strpos($column['Field'], 'finance_cleared') !== false) {
            echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>