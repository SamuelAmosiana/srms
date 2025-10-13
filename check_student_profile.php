<?php
require_once 'config.php';

try {
    $stmt = $pdo->query("DESCRIBE student_profile");
    $columns = $stmt->fetchAll();
    
    echo "student_profile table structure:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>