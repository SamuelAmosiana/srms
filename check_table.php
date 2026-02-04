<?php
require_once 'config.php';

echo "Checking payments table structure:\n";

$stmt = $pdo->query('DESCRIBE payments');
while($row = $stmt->fetch()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
?>