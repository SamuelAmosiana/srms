<?php
require '../config.php';

try {
    // Get a sample application record to see how phone is stored
    $stmt = $pdo->query("SELECT id, full_name, email, documents FROM applications LIMIT 5");
    $applications = $stmt->fetchAll();
    
    echo "<h2>Sample Application Records:</h2>";
    foreach ($applications as $app) {
        echo "<h3>Application ID: " . $app['id'] . "</h3>";
        echo "<p>Name: " . $app['full_name'] . "</p>";
        echo "<p>Email: " . $app['email'] . "</p>";
        echo "<p>Documents JSON: " . $app['documents'] . "</p>";
        
        // Try to parse the documents
        $documents = json_decode($app['documents'], true);
        if ($documents) {
            echo "<p>Parsed Documents:</p>";
            echo "<ul>";
            foreach ($documents as $key => $value) {
                if (is_array($value)) {
                    echo "<li><strong>" . $key . ":</strong> [Array]</li>";
                } else {
                    echo "<li><strong>" . $key . ":</strong> " . $value . "</li>";
                }
            }
            echo "</ul>";
        } else {
            echo "<p>Could not parse documents JSON</p>";
        }
        echo "<hr>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>