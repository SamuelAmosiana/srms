<?php
require '../config.php';

$stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
$stmt->execute(['LSC000002']);
$user = $stmt->fetch();

echo "User with student number as username:\n";
print_r($user);

if (!$user) {
    echo "No user found with username LSC000002\n";
}
?>