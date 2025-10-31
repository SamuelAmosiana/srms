<?php
require_once __DIR__ . '/../config.php';

try {
    // Check if fee_types table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'fee_types'");
    if ($stmt->rowCount() > 0) {
        echo "Table 'fee_types' exists.\n\n";
        
        // Get table structure
        $stmt = $pdo->query("DESCRIBE fee_types");
        $columns = $stmt->fetchAll();
        
        echo "Table Structure:\n";
        foreach ($columns as $column) {
            echo $column['Field'] . " (" . $column['Type'] . ")\n";
        }
        
        echo "\nFee Types Data:\n";
        $stmt = $pdo->query("SELECT * FROM fee_types ORDER BY name");
        $fee_types = $stmt->fetchAll();
        
        if (empty($fee_types)) {
            echo "No data found in fee_types table.\n";
        } else {
            foreach ($fee_types as $fee_type) {
                echo $fee_type['id'] . " - " . $fee_type['name'] . " (" . ($fee_type['is_active'] ? 'Active' : 'Inactive') . ")\n";
                if (!empty($fee_type['description'])) {
                    echo "  Description: " . $fee_type['description'] . "\n";
                }
            }
        }
    } else {
        echo "Table 'fee_types' does not exist.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>