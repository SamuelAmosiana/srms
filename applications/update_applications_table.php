<?php
require 'config.php';

try {
    // Add processed_by column to applications table
    $sql = "ALTER TABLE applications ADD COLUMN processed_by INT(11) DEFAULT NULL AFTER rejection_reason";
    $pdo->exec($sql);
    
    // Add foreign key constraint
    $sql = "ALTER TABLE applications ADD CONSTRAINT fk_applications_processed_by FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL";
    $pdo->exec($sql);
    
    // Add updated_at column to track when applications were processed
    $sql = "ALTER TABLE applications ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER processed_by";
    $pdo->exec($sql);
    
    echo "Applications table updated successfully!\n";
    echo "Added columns:\n";
    echo "1. processed_by (INT) - to track which enrollment officer processed the application\n";
    echo "2. updated_at (TIMESTAMP) - to track when the application was processed\n";
    
} catch (Exception $e) {
    echo "Error updating applications table: " . $e->getMessage() . "\n";
}
?>