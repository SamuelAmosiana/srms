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
// Debug: Log raw input to understand what's being received
error_log('get_courses_by_programme.php - Raw REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
error_log('get_courses_by_programme.php - GET data: ' . print_r($_GET, true));

// Try multiple methods to get the programme_id
$programme_id = 0;

// First try GET parameter
if (isset($_GET['programme_id'])) {
    $programme_id = (int)$_GET['programme_id'];
}

// If still 0, check if it might be sent via POST
if ($programme_id <= 0 && isset($_POST['programme_id'])) {
    $programme_id = (int)$_POST['programme_id'];
}

// If still 0, check if it's in the request body
if ($programme_id <= 0) {
    $input = file_get_contents('php://input');
    if (!empty($input)) {
        $data = json_decode($input, true);
        if (isset($data['programme_id'])) {
            $programme_id = (int)$data['programme_id'];
        }
    }
}

error_log('get_courses_by_programme.php - Final programme_id: ' . $programme_id);

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
    // Try to get courses associated with the programme via intake_courses table
    $stmt = $pdo->prepare(
        "SELECT DISTINCT c.id, c.name, c.code 
        FROM course c
        INNER JOIN intake_courses ic ON c.id = ic.course_id
        WHERE ic.programme_id = ?
        ORDER BY c.name"
    );
    $stmt->execute([$programme_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    // If no courses found in intake_courses, try direct relationship
    if (empty($courses)) {
        $stmt = $pdo->prepare(
            "SELECT id, name, code 
            FROM course 
            WHERE programme_id = ? 
            ORDER BY name"
        );
        $stmt->execute([$programme_id]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    header('Content-Type: application/json');
    echo json_encode(['courses' => $courses]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>