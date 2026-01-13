<?php
require_once '../config.php';
try {
    echo "Course Table Structure:\n";
    $stmt = $pdo->query('DESCRIBE course');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo '- ' . $column['Field'] . ' (' . $column['Type'] . ') - ' . $column['Null'] . ' - ' . ($column['Key'] ?: 'None') . ' - ' . ($column['Extra'] ?: 'None') . "\n";
    }
    
    echo "\nSample Course Data with Programme Categories:\n";
    $stmt = $pdo->query('SELECT c.id, c.name, c.code, c.programme_id, p.name as programme_name, p.category as programme_category FROM course c LEFT JOIN programme p ON c.programme_id = p.id LIMIT 10');
    $courses = $stmt->fetchAll();
    foreach ($courses as $course) {
        echo '- Course ID: ' . $course['id'] . ', Name: ' . $course['name'] . ', Programme: ' . $course['programme_name'] . ', Category: ' . $course['programme_category'] . "\n";
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>