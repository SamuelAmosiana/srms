<?php
require 'config.php';

try {
    echo "Testing Application Categorization Logic\n";
    echo "========================================\n\n";
    
    // Test the categorization logic
    $stmt = $pdo->prepare("
        SELECT a.id, a.full_name, a.email, p.name as programme_name,
               CASE 
                   WHEN p.name LIKE '%Business%' OR p.name LIKE '%Admin%' OR p.name LIKE '%Diploma%' THEN 'undergraduate'
                   WHEN p.name LIKE '%Computer%' OR p.name LIKE '%IT%' OR p.name LIKE '%Certificate%' THEN 'short_course'
                   WHEN p.name LIKE '%Corporate%' OR p.name LIKE '%Training%' OR a.documents LIKE '%corporate_training%' THEN 'corporate'
                   ELSE 'other'
               END as category
        FROM applications a
        LEFT JOIN programme p ON a.programme_id = p.id
        WHERE a.status = 'pending'
        ORDER BY a.created_at DESC
    ");
    $stmt->execute();
    $applications = $stmt->fetchAll();
    
    echo "Pending Applications by Category:\n";
    echo "---------------------------------\n";
    foreach ($applications as $app) {
        echo "ID: " . $app['id'] . " | Name: " . $app['full_name'] . " | Programme: " . ($app['programme_name'] ?? 'N/A') . " | Category: " . $app['category'] . "\n";
    }
    
    echo "\n";
    
    // Test undergraduate filter
    $stmt = $pdo->prepare("
        SELECT a.id, a.full_name, a.email, p.name as programme_name
        FROM applications a
        LEFT JOIN programme p ON a.programme_id = p.id
        WHERE a.status = 'pending' 
        AND (p.name LIKE '%Business%' OR p.name LIKE '%Admin%' OR p.name LIKE '%Diploma%')
        ORDER BY a.created_at DESC
    ");
    $stmt->execute();
    $undergraduateApps = $stmt->fetchAll();
    
    echo "Undergraduate Applications:\n";
    echo "---------------------------\n";
    foreach ($undergraduateApps as $app) {
        echo "ID: " . $app['id'] . " | Name: " . $app['full_name'] . " | Programme: " . ($app['programme_name'] ?? 'N/A') . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>