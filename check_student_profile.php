<?php
require 'config.php';

$stmt = $pdo->prepare('SELECT user_id FROM student_profile WHERE student_number = ?');
$stmt->execute(['LSC000002']);
$profile = $stmt->fetch();
echo 'Current user_id in student_profile: ' . $profile['user_id'];
?>
<?php
require_once 'config.php';

try {
    $stmt = $pdo->query("DESCRIBE student_profile");
    $columns = $stmt->fetchAll();
    
    echo "student_profile table structure:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>