<?php
require 'config.php';

try {
    // Get all programmes
    $stmt = $pdo->query("SELECT id, name FROM programme ORDER BY name");
    $programmes = $stmt->fetchAll();
    
    echo "Existing Programmes in Database:\n";
    echo "================================\n";
    foreach ($programmes as $prog) {
        echo "ID: " . $prog['id'] . " | Name: " . $prog['name'] . "\n";
    }
    
    echo "\n";
    
    // Get all applications
    $stmt = $pdo->query("SELECT a.id, a.full_name, a.email, a.programme_id, p.name as programme_name FROM applications a LEFT JOIN programme p ON a.programme_id = p.id");
    $applications = $stmt->fetchAll();
    
    echo "Applications in Database:\n";
    echo "=========================\n";
    foreach ($applications as $app) {
        echo "ID: " . $app['id'] . " | Name: " . $app['full_name'] . " | Email: " . $app['email'] . " | Programme ID: " . $app['programme_id'] . " | Programme: " . ($app['programme_name'] ?? 'N/A') . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>