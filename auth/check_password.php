<?php
require '../config.php';

$stmt = $pdo->prepare("SELECT u.id, u.username, u.password_hash, s.student_number, s.full_name FROM users u JOIN student_profile s ON u.id = s.user_id WHERE s.student_number = ?");
$stmt->execute(['LSC000002']);
$user = $stmt->fetch();

echo "User data:\n";
print_r($user);

if (!empty($user['password_hash'])) {
    if (password_verify('LSC000002', $user['password_hash'])) {
        echo "Password matches!\n";
    } else {
        echo "Password does not match\n";
    }
} else {
    echo "No password set\n";
}
?>