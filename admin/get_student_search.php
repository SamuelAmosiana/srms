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

$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Query must be at least 2 characters']);
    exit();
}

try {
    // Search for students by student number or full name
    $sql = "SELECT student_number, full_name 
            FROM student_profile 
            WHERE student_number LIKE ? OR full_name LIKE ?
            ORDER BY full_name
            LIMIT 10";
    
    $search_param = '%' . $query . '%';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$search_param, $search_param]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'students' => $students
    ]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error searching students: ' . $e->getMessage()
    ]);
}
?>