<?php
require 'config.php';

$stmt = $pdo->prepare('UPDATE users SET username = ? WHERE id = 12');
$stmt->execute(['LSC000002']);
echo 'Username updated successfully';
?>