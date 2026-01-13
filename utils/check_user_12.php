<?php
require '../config.php';

$stmt = $pdo->prepare('SELECT username FROM users WHERE id = 12');
$stmt->execute();
$user = $stmt->fetch();
echo 'User 12 username: ' . $user['username'];
?>