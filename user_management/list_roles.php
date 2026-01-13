<?php
require '../config.php';

try {
    $stmt = $pdo->query("SELECT id, name, description FROM roles ORDER BY name");
    $roles = $stmt->fetchAll();
    
    echo "Current Roles in the System:\n";
    echo "============================\n";
    foreach ($roles as $role) {
        echo "ID: " . $role['id'] . ", Name: " . $role['name'] . ", Description: " . $role['description'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>