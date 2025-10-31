<?php
require 'config.php';

try {
    // Check student_profile table structure
    echo "=== STUDENT_PROFILE TABLE STRUCTURE ===\n";
    $stmt = $pdo->query('DESCRIBE student_profile');
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $col) {
        echo $col['Field'] . ' | ' . $col['Type'] . ' | ' . $col['Null'] . ' | ' . $col['Key'] . ' | ' . $col['Default'] . ' | ' . $col['Extra'] . "\n";
    }
    
    echo "\n=== FINANCE_TRANSACTIONS TABLE STRUCTURE ===\n";
    $stmt = $pdo->query('DESCRIBE finance_transactions');
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $col) {
        echo $col['Field'] . ' | ' . $col['Type'] . ' | ' . $col['Null'] . ' | ' . $col['Key'] . ' | ' . $col['Default'] . ' | ' . $col['Extra'] . "\n";
    }
    
    echo "\n=== PROGRAMME TABLE STRUCTURE ===\n";
    $stmt = $pdo->query('DESCRIBE programme');
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $col) {
        echo $col['Field'] . ' | ' . $col['Type'] . ' | ' . $col['Null'] . ' | ' . $col['Key'] . ' | ' . $col['Default'] . ' | ' . $col['Extra'] . "\n";
    }
    
    echo "\n=== STUDENT_FEES TABLE STRUCTURE ===\n";
    $stmt = $pdo->query('DESCRIBE student_fees');
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $col) {
        echo $col['Field'] . ' | ' . $col['Type'] . ' | ' . $col['Null'] . ' | ' . $col['Key'] . ' | ' . $col['Default'] . ' | ' . $col['Extra'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>