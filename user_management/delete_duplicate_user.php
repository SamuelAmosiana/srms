<?php
require 'config.php';

$stmt = $pdo->prepare('DELETE FROM users WHERE username = ? AND id != 12');
$stmt->execute(['LSC000002']);
echo 'Duplicate user deleted';
?>