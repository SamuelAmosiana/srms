<?php
require 'config.php';

echo "Checking for employees in the database:\n";

try {
    $stmt = $pdo->query('SELECT employee_id, first_name, last_name FROM employees LIMIT 5');
    $employees = $stmt->fetchAll();
    
    if (count($employees) > 0) {
        echo "Found " . count($employees) . " employees:\n";
        foreach($employees as $employee) {
            echo '- ID: ' . $employee['employee_id'] . ', Name: ' . $employee['first_name'] . ' ' . $employee['last_name'] . "\n";
        }
    } else {
        echo "No employees found in the database.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>