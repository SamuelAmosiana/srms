<?php
require '../config.php';

try {
    $hash = password_hash('LSC000002', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
    $stmt->execute(['LSC000002', $hash]);
    echo 'User with student number as username created successfully';
} catch (Exception $e) {
    echo 'Error creating user: ' . $e->getMessage();
}
?>