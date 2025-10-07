<?php
require 'config.php';

function currentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function currentUserHasRole($roleName, $pdo) {
    if (!isset($_SESSION['roles'])) {
        if (!currentUserId()) return false;
        $stmt = $pdo->prepare("SELECT r.name FROM roles r JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ?");
        $stmt->execute([currentUserId()]);
        $_SESSION['roles'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    return in_array($roleName, $_SESSION['roles']);
}

function requireRole($roleName, $pdo) {
    if (!currentUserHasRole($roleName, $pdo)) {
        http_response_code(403);
        die('Forbidden - insufficient permissions');
    }
}
