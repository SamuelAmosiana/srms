<?php
require 'config.php';

echo "Fields in employees table:<br>";

try {
    $stmt = $pdo->query('DESCRIBE employees');
    $fields = $stmt->fetchAll();
    
    foreach($fields as $field) {
        echo '- ' . $field['Field'] . '<br>';
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>