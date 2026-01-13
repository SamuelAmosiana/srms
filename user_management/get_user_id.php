<?php
require 'config.php';

$stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
$stmt->execute(['LSC000002']);
$user = $stmt->fetch();
echo 'New user ID: ' . $user['id'];
?>