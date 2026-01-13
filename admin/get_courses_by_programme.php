<?php
session_start();
require_once '../config.php';
require_once '../auth/auth.php';

// Check if user is logged in and has permission
if (!currentUserId()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

// Check if user has admin role or course_registrations permission
if (!currentUserHasRole('Super Admin', $pdo) && !currentUserHasPermission('course_registrations', $pdo)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Insufficient permissions']);
    exit();
}

// Get programme ID from GET request
$programme_id = isset($_GET['programme_id']) ? (int)$_GET['programme_id'] : 0;

if ($programme_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid programme ID: ' . $programme_id]);
    exit();
}

try {
    // Check if programme exists
    $programme_check = $pdo->prepare("SELECT id FROM programme WHERE id = ?");
    $programme_check->execute([$programme_id]);
    if (!$programme_check->fetch()) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Programme not found: ' . $programme_id]);
        exit();
    }
    
    // Fetch courses for the specified programme
    $stmt = $pdo->prepare("
        SELECT id, name, code 
        FROM course 
        WHERE programme_id = ? 
        ORDER BY name
    ");
    $stmt->execute([$programme_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode(['courses' => $courses]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>