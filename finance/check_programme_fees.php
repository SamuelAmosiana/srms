<?php
require_once __DIR__ . '/../config.php';

try {
    // Check if programme_fees table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'programme_fees'");
    if ($stmt->rowCount() > 0) {
        echo "Table 'programme_fees' exists.\n\n";
        
        // Get table structure
        $stmt = $pdo->query("DESCRIBE programme_fees");
        $columns = $stmt->fetchAll();
        
        echo "Table Structure:\n";
        foreach ($columns as $column) {
            echo $column['Field'] . " (" . $column['Type'] . ")\n";
        }
        
        echo "\nSample Data (first 5 rows):\n";
        $stmt = $pdo->query("SELECT * FROM programme_fees LIMIT 5");
        $fees = $stmt->fetchAll();
        
        if (empty($fees)) {
            echo "No data found in programme_fees table.\n";
        } else {
            foreach ($fees as $fee) {
                echo "ID: " . $fee['id'] . " | Programme ID: " . $fee['programme_id'] . " | Name: " . $fee['fee_name'] . " | Amount: " . $fee['fee_amount'] . " | Type: " . $fee['fee_type'] . "\n";
            }
        }
    } else {
        echo "Table 'programme_fees' does not exist.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>