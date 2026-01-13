<?php
require '../config.php';

try {
    $stmt = $pdo->query("SELECT * FROM student_profile WHERE student_number LIKE 'STU-%'");
    $students = $stmt->fetchAll();
    
    if (empty($students)) {
        echo "No students with STU- format found.\n";
    } else {
        echo "Found students with STU- format:\n";
        foreach ($students as $student) {
            echo "- " . $student['full_name'] . " - " . $student['student_number'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>