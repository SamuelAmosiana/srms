<?php
require 'config.php';

try {
    $stmt = $pdo->query('DESCRIBE course');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Course table structure:\n";
    foreach ($columns as $column) {
        echo "Column: " . $column['Field'] . " | Type: " . $column['Type'] . "\n";
    }
    
    echo "\nTesting the query that was causing the error:\n";
    $stmt = $pdo->prepare("
        SELECT c.id, c.code, c.name 
        FROM course c 
        JOIN course_assignment ca ON c.id = ca.course_id 
        WHERE ca.lecturer_id = ?
    ");
    echo "Query prepared successfully.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>