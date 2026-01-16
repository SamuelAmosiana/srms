<?php
require '../config.php';

try {
    // Get recent applications with documents
    $stmt = $pdo->query("
        SELECT id, full_name, email, documents, created_at 
        FROM applications 
        WHERE documents IS NOT NULL AND documents != ''
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $applications = $stmt->fetchAll();
    
    echo "<h2>Recent Applications with Documents:</h2>";
    
    foreach ($applications as $app) {
        echo "<h3>Application ID: " . $app['id'] . " - " . $app['full_name'] . "</h3>";
        echo "<p>Email: " . $app['email'] . "</p>";
        echo "<p>Created: " . $app['created_at'] . "</p>";
        echo "<p>Documents JSON: " . htmlspecialchars($app['documents']) . "</p>";
        
        // Try to parse the documents
        $documents = json_decode($app['documents'], true);
        if ($documents) {
            echo "<h4>Parsed Documents:</h4>";
            echo "<ul>";
            if (is_array($documents)) {
                foreach ($documents as $index => $doc) {
                    if (is_array($doc) && isset($doc['path']) && isset($doc['name'])) {
                        echo "<li><strong>File " . ($index + 1) . ":</strong><br>";
                        echo "Name: " . htmlspecialchars($doc['name']) . "<br>";
                        echo "Path: " . htmlspecialchars($doc['path']) . "<br>";
                        echo "Filename: " . htmlspecialchars(basename($doc['path'])) . "<br>";
                        
                        // Check if file exists
                        $full_path = __DIR__ . '/../uploads/' . basename($doc['path']);
                        if (file_exists($full_path)) {
                            echo "<span style='color: green;'>✓ File exists (" . filesize($full_path) . " bytes)</span><br>";
                            echo "<a href='../uploads/" . basename($doc['path']) . "' target='_blank'>Direct Link</a><br>";
                        } else {
                            echo "<span style='color: red;'>✗ File NOT found</span><br>";
                        }
                        echo "</li>";
                    } else {
                        echo "<li>" . htmlspecialchars(json_encode($doc)) . "</li>";
                    }
                }
            } else {
                // Handle object format
                foreach ($documents as $key => $value) {
                    if (is_array($value) && isset($value['path'])) {
                        echo "<li><strong>" . htmlspecialchars($key) . ":</strong><br>";
                        echo "Name: " . htmlspecialchars($value['name']) . "<br>";
                        echo "Path: " . htmlspecialchars($value['path']) . "<br>";
                        echo "Filename: " . htmlspecialchars(basename($value['path'])) . "<br>";
                        
                        // Check if file exists
                        $full_path = __DIR__ . '/../uploads/' . basename($value['path']);
                        if (file_exists($full_path)) {
                            echo "<span style='color: green;'>✓ File exists (" . filesize($full_path) . " bytes)</span><br>";
                            echo "<a href='../uploads/" . basename($value['path']) . "' target='_blank'>Direct Link</a><br>";
                        } else {
                            echo "<span style='color: red;'>✗ File NOT found</span><br>";
                        }
                        echo "</li>";
                    } else {
                        echo "<li><strong>" . htmlspecialchars($key) . ":</strong> " . htmlspecialchars($value) . "</li>";
                    }
                }
            }
            echo "</ul>";
        } else {
            echo "<p style='color: red;'>Could not parse documents JSON</p>";
        }
        echo "<hr>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>