<?php
require '../config.php';

echo "Checking course_enrollment table structure:\n";

try {
    $stmt = $pdo->query('DESCRIBE course_enrollment');
    $columns = $stmt->fetchAll();
    
    echo "Columns in course_enrollment table:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>