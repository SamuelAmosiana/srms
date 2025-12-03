<?php
require 'config.php';

try {
    // Test inserting a sample application with mode_of_learning
    $stmt = $pdo->prepare("INSERT INTO applications (full_name, email, mode_of_learning) VALUES (?, ?, ?)");
    $stmt->execute([
        'Test User',
        'test@example.com',
        'online'
    ]);
    
    $application_id = $pdo->lastInsertId();
    echo "Successfully inserted test application with ID: " . $application_id . "\n";
    
    // Retrieve and display the inserted record
    $stmt = $pdo->prepare("SELECT id, full_name, email, mode_of_learning FROM applications WHERE id = ?");
    $stmt->execute([$application_id]);
    $app = $stmt->fetch();
    
    echo "Retrieved application:\n";
    echo "ID: " . $app['id'] . "\n";
    echo "Name: " . $app['full_name'] . "\n";
    echo "Email: " . $app['email'] . "\n";
    echo "Mode of Learning: " . $app['mode_of_learning'] . "\n";
    
    // Clean up - delete the test record
    $stmt = $pdo->prepare("DELETE FROM applications WHERE id = ?");
    $stmt->execute([$application_id]);
    echo "Cleaned up test record.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>