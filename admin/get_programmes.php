<?php
session_start();
require_once '../config.php';
require_once '../auth/auth.php';

// Check if user is logged in and has permission
if (!currentUserId()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Check if user has admin role or course_registrations permission
if (!currentUserHasRole('Super Admin', $pdo) && !currentUserHasPermission('course_registrations', $pdo)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit();
}

// Get programmes for the dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM programme ORDER BY name");
    $programmes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $options = '<option value="">All Programmes</option>';
    foreach ($programmes as $programme) {
        $options .= '<option value="' . htmlspecialchars($programme['id']) . '">' . htmlspecialchars($programme['name']) . '</option>';
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'options' => $options
    ]);
    
} catch (Exception $e) {
    // If programme table doesn't exist, return empty options
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'options' => '<option value="">All Programmes</option>'
    ]);
}
?>