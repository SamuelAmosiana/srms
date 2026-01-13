<?php
require 'config.php';

try {
    // Check student_fees table data
    echo "=== STUDENT_FEES TABLE DATA (First 5 records) ===\n";
    $stmt = $pdo->query('SELECT * FROM student_fees LIMIT 5');
    $fees = $stmt->fetchAll();
    
    if (empty($fees)) {
        echo "No data found in student_fees table.\n";
    } else {
        foreach ($fees as $fee) {
            print_r($fee);
            echo "\n---\n";
        }
    }
    
    // Check if there are any programme fees defined
    echo "\n=== PROGRAMME FEES DATA ===\n";
    // Since there's no programme_fees table, we need to create one
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>