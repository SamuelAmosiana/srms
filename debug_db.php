<?php
require 'config.php';

echo "Database connection status: ";
try {
    $pdo->query('SELECT 1');
    echo "Connected successfully\n";
} catch (Exception $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
    exit;
}

// Check if course table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'course'");
    if ($stmt->rowCount() > 0) {
        echo "Course table exists\n";
        
        // Get table structure
        $stmt = $pdo->query('DESCRIBE course');
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Course table columns:\n";
        foreach ($columns as $column) {
            echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
    } else {
        echo "Course table does not exist\n";
    }
} catch (Exception $e) {
    echo "Error checking course table: " . $e->getMessage() . "\n";
}

// Check if course_assignment table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'course_assignment'");
    if ($stmt->rowCount() > 0) {
        echo "\nCourse assignment table exists\n";
    } else {
        echo "\nCourse assignment table does not exist\n";
    }
} catch (Exception $e) {
    echo "Error checking course_assignment table: " . $e->getMessage() . "\n";
}
?>