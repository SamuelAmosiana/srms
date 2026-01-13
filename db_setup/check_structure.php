<?php
require '../config.php';

echo "Fields in employees table:\n";

try {
    $stmt = $pdo->query('SHOW COLUMNS FROM employees');
    $fields = $stmt->fetchAll();
    
    foreach($fields as $field) {
        echo '- ' . $field['Field'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>