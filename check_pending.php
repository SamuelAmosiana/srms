<?php
require 'config.php';

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pending_students");
    $result = $stmt->fetch();
    echo "Pending students: " . $result['count'] . "\n";
    
    if ($result['count'] > 0) {
        echo "\nPending students list:\n";
        $stmt = $pdo->query("SELECT * FROM pending_students");
        $students = $stmt->fetchAll();
        foreach ($students as $student) {
            echo "- " . $student['full_name'] . " (" . $student['email'] . ")\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>