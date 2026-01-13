<?php
require 'config.php';

try {
    // Add mode_of_learning column to applications table
    $sql = "ALTER TABLE applications ADD COLUMN mode_of_learning ENUM('online', 'physical') DEFAULT NULL AFTER intake_id";
    $pdo->exec($sql);
    
    echo "Successfully added mode_of_learning column to applications table!\n";
    echo "Column details:\n";
    echo "- Name: mode_of_learning\n";
    echo "- Type: ENUM('online', 'physical')\n";
    echo "- Default: NULL\n";
    echo "- Position: After intake_id\n";
    
} catch (Exception $e) {
    echo "Error adding mode_of_learning column: " . $e->getMessage() . "\n";
}
?>