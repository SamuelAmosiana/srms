<?php
session_start();
require_once '../config.php';
require_once '../auth/auth.php';

// Check if user is logged in and has permission
if (!currentUserId()) {
    header('Location: ../auth/login.php');
    exit();
}

// Check if user has admin role or manage_academic_structure permission
if (!currentUserHasRole('Super Admin', $pdo) && !currentUserHasPermission('manage_academic_structure', $pdo)) {
    header('Location: ../auth/login.php');
    exit();
}

try {
    // Get all intakes ordered by name
    $stmt = $pdo->query("SELECT id, name FROM intake ORDER BY name");
    $intakes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $options_html = '';
    foreach ($intakes as $intake) {
        $options_html .= '<option value="' . htmlspecialchars($intake['id']) . '">' . htmlspecialchars($intake['name']) . '</option>';
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'options' => $options_html
    ]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching intakes: ' . $e->getMessage()
    ]);
}
?>