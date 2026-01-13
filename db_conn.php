<?php
// create_user.php (example)
require 'config.php'; // Note: This file is in root directory, so path is correct

$username = $_POST['username']; // e.g. email
$password = $_POST['password'];

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (username,password_hash,email,contact) VALUES (?, ?, ?, ?)");
$stmt->execute([$username, $hash, $_POST['email'] ?? null, $_POST['contact'] ?? null]);
$userId = $pdo->lastInsertId();

// assign role(s)
$roleId = 1; // e.g. Super Admin role id
$pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$userId, $roleId]);
