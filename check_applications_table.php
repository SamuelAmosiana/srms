<?php
require 'config.php';

try {
    $stmt = $pdo->query('DESCRIBE applications');
    $columns = $stmt->fetchAll();
    
    echo "Applications table structure:\n";
    echo "================================\n";
    foreach ($columns as $column) {
        echo $column['Field'] . ' - ' . $column['Type'] . ' (' . $column['Null'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>