<!DOCTYPE html>
<html>
<head>
    <title>Table Check</title>
</head>
<body>
<?php
require 'config.php';

echo "<h2>Database Tables Check</h2>";

// Get all tables
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll();

if ($tables) {
    echo "<h3>Tables in database:</h3>";
    foreach ($tables as $table) {
        echo array_values($table)[0] . "<br>";
    }
} else {
    echo "No tables found<br>";
}

// Check specific tables that are critical for login
$requiredTables = ['users', 'roles', 'user_roles', 'student_profile'];

foreach ($requiredTables as $tableName) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $tableName");
        $result = $stmt->fetch();
        echo "<h4>Table '$tableName': " . $result['count'] . " records</h4>";
        
        // Show sample data for users table
        if ($tableName == 'users' && $result['count'] > 0) {
            $stmt = $pdo->query("SELECT * FROM users LIMIT 5");
            $users = $stmt->fetchAll();
            echo "<ul>";
            foreach ($users as $user) {
                echo "<li>ID: " . $user['id'] . " | Username: " . $user['username'] . " | Email: " . $user['email'] . "</li>";
            }
            echo "</ul>";
        }
        
        // Show sample data for student_profile table
        if ($tableName == 'student_profile' && $result['count'] > 0) {
            $stmt = $pdo->query("SELECT sp.*, u.username FROM student_profile sp JOIN users u ON sp.user_id = u.id LIMIT 5");
            $students = $stmt->fetchAll();
            echo "<ul>";
            foreach ($students as $student) {
                echo "<li>User ID: " . $student['user_id'] . " | Username: " . $student['username'] . " | Student Number: " . $student['student_number'] . " | Full Name: " . $student['full_name'] . "</li>";
            }
            echo "</ul>";
        }
    } catch (Exception $e) {
        echo "<h4>Table '$tableName': ERROR - " . $e->getMessage() . "</h4>";
    }
}
?>
</body>
</html>