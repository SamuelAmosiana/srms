<?php
require 'config.php';

try {
    // Get recent applications with documents to see the actual path format
    $stmt = $pdo->query("
        SELECT id, full_name, email, documents, created_at 
        FROM applications 
        WHERE documents IS NOT NULL AND documents != ''
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $applications = $stmt->fetchAll();
    
    echo "<h2>Document Path Analysis</h2>";
    
    foreach ($applications as $app) {
        echo "<h3>Application ID: " . $app['id'] . " - " . $app['full_name'] . "</h3>";
        
        // Parse documents
        $documents = json_decode($app['documents'], true);
        if ($documents) {
            echo "<h4>Documents:</h4>";
            if (is_array($documents)) {
                foreach ($documents as $index => $doc) {
                    if (is_array($doc) && isset($doc['path']) && isset($doc['name'])) {
                        echo "<p><strong>File " . ($index + 1) . ":</strong></p>";
                        echo "<ul>";
                        echo "<li>Name: " . htmlspecialchars($doc['name']) . "</li>";
                        echo "<li>Path: " . htmlspecialchars($doc['path']) . "</li>";
                        echo "<li>Extracted filename: " . htmlspecialchars(basename($doc['path'])) . "</li>";
                        echo "<li>Raw path parts: " . htmlspecialchars(print_r(explode('/', $doc['path']), true)) . "</li>";
                        echo "</ul>";
                        
                        // Test the actual JavaScript logic
                        $path_parts = explode('/', $doc['path']);
                        $extracted = end($path_parts);
                        echo "<p>PHP equivalent of JS split('/').pop(): " . htmlspecialchars($extracted) . "</p>";
                    }
                }
            } else {
                foreach ($documents as $key => $value) {
                    if (is_array($value) && isset($value['path'])) {
                        echo "<p><strong>" . htmlspecialchars($key) . ":</strong></p>";
                        echo "<ul>";
                        echo "<li>Name: " . htmlspecialchars($value['name']) . "</li>";
                        echo "<li>Path: " . htmlspecialchars($value['path']) . "</li>";
                        echo "<li>Extracted filename: " . htmlspecialchars(basename($value['path'])) . "</li>";
                        
                        // Test the actual JavaScript logic
                        $path_parts = explode('/', $value['path']);
                        $extracted = end($path_parts);
                        echo "<li>PHP equivalent of JS split('/').pop(): " . htmlspecialchars($extracted) . "</li>";
                        echo "</ul>";
                    }
                }
            }
        }
        echo "<hr>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>