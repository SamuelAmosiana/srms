<?php
require_once '../config.php';
try {
    $stmt = $pdo->query('DESCRIBE student_profile');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "student_profile table structure:\n";
    foreach ($columns as $column) {
        echo '- ' . $column['Field'] . ' (' . $column['Type'] . ') - ' . $column['Null'] . ' - ' . ($column['Key'] ?: 'None') . ' - ' . ($column['Extra'] ?: 'None') . "\n";
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>