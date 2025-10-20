<?php
require 'config.php';

try {
    echo "Fixing Application Programme Links\n";
    echo "==================================\n\n";
    
    // Get all programmes with their IDs
    $stmt = $pdo->query("SELECT id, name FROM programme");
    $programmes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    echo "Available Programmes:\n";
    foreach ($programmes as $id => $name) {
        echo "ID: $id | Name: $name\n";
    }
    echo "\n";
    
    // For applications without programme_id, try to match based on programme name patterns
    foreach ($programmes as $id => $name) {
        $pattern = '';
        if (strpos($name, 'Business') !== false || strpos($name, 'Admin') !== false) {
            $pattern = '%Business%';
        } elseif (strpos($name, 'Computer') !== false || strpos($name, 'IT') !== false) {
            $pattern = '%Computer%';
        } elseif (strpos($name, 'Certificate') !== false) {
            $pattern = '%Certificate%';
        }
        
        if ($pattern) {
            $stmt = $pdo->prepare("
                UPDATE applications 
                SET programme_id = ? 
                WHERE programme_id IS NULL 
                AND full_name IN (
                    SELECT full_name 
                    FROM (
                        SELECT full_name 
                        FROM applications 
                        WHERE programme_id IS NULL
                    ) AS sub
                )
                LIMIT 5
            ");
            $stmt->execute([$id]);
            echo "Updated applications with programme: $name (ID: $id)\n";
        }
    }
    
    echo "\nApplication programme links updated successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>