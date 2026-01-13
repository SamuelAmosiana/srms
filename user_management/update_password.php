<?php
require 'config.php';

$hash = password_hash('LSC000002', PASSWORD_DEFAULT);
$stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = 12');
$stmt->execute([$hash]);
echo 'Password updated successfully';
?>