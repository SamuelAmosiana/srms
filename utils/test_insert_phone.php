<?php
require 'config.php';

try {
    // Test inserting a sample application with phone number
    $stmt = $pdo->prepare("INSERT INTO applications (full_name, email, documents) VALUES (?, ?, ?)");
    $stmt->execute([
        'Test User',
        'test@example.com',
        json_encode([
            'phone' => '123-456-7890',
            'occupation' => 'Developer',
            'schedule' => 'weekdays',
            'experience' => '5 years',
            'goals' => 'Career advancement'
        ])
    ]);
    
    $application_id = $pdo->lastInsertId();
    echo "Successfully inserted test application with ID: " . $application_id . "\n";
    
    // Retrieve and display the inserted record
    $stmt = $pdo->prepare("SELECT id, full_name, email, documents FROM applications WHERE id = ?");
    $stmt->execute([$application_id]);
    $app = $stmt->fetch();
    
    echo "Retrieved application:\n";
    echo "ID: " . $app['id'] . "\n";
    echo "Name: " . $app['full_name'] . "\n";
    echo "Email: " . $app['email'] . "\n";
    echo "Documents JSON: " . $app['documents'] . "\n";
    
    // Parse and display the documents
    $documents = json_decode($app['documents'], true);
    if ($documents) {
        echo "Parsed documents:\n";
        foreach ($documents as $key => $value) {
            echo "  " . $key . ": " . $value . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>