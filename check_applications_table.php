<?php
require 'config.php';

try {
    // Get table structure
    $stmt = $pdo->query("DESCRIBE applications");
    $columns = $stmt->fetchAll();
    
    echo "<h2>Applications Table Structure:</h2>";
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Get a sample application record
    $stmt = $pdo->query("SELECT * FROM applications LIMIT 1");
    $sample = $stmt->fetch();
    
    if ($sample) {
        echo "<h2>Sample Application Record:</h2>";
        echo "<pre>";
        print_r($sample);
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>