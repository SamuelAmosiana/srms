<?php
require 'config.php';

$stmt = $pdo->prepare("SELECT u.id, u.username, u.password_hash, s.student_number, s.full_name FROM users u JOIN student_profile s ON u.id = s.user_id WHERE s.student_number = ?");
$stmt->execute(['LSC000002']);
$user = $stmt->fetch();

echo "Student record:\n";
print_r($user);

echo "\nPassword hash: " . ($user['password_hash'] ?? 'NULL') . "\n";

// Check if password is empty
if (empty($user['password_hash'])) {
    echo "Password hash is empty - this might be the issue\n";
} else {
    echo "Password hash exists\n";
}
?>