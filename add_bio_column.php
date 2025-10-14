<?php
require_once 'config.php';

try {
    // Try to add bio column to admin_profile table
    $pdo->exec("ALTER TABLE admin_profile ADD COLUMN bio TEXT");
    echo "Successfully added bio column to admin_profile table.\n";
} catch (Exception $e) {
    echo "Column might already exist or there was an error: " . $e->getMessage() . "\n";
}

// Verify the column exists
try {
    $stmt = $pdo->prepare("DESCRIBE admin_profile");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Current columns in admin_profile table:\n";
    foreach ($columns as $column) {
        echo "- " . $column . "\n";
    }
    
    if (in_array('bio', $columns)) {
        echo "\nBio column successfully added!\n";
    } else {
        echo "\nBio column was not added.\n";
    }
} catch (Exception $e) {
    echo "Error checking table structure: " . $e->getMessage() . "\n";
}
?>