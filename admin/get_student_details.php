<?php
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is admin
// Note: In a real implementation, you would check proper authentication
// For now, we'll just ensure this script is only accessed by the admin panel

// Get student ID from request
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($student_id <= 0) {
    echo json_encode(['error' => 'Invalid student ID']);
    exit;
}

try {
    // Fetch student details
    $stmt = $pdo->prepare("
        SELECT ps.*, p.name as programme_name, i.name as intake_name
        FROM pending_students ps
        LEFT JOIN programme p ON ps.programme_id = p.id
        LEFT JOIN intake i ON ps.intake_id = i.id
        WHERE ps.id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        echo json_encode(['error' => 'Student not found']);
        exit;
    }
    
    // Fetch courses for this programme and term 1
    $courses = [];
    if ($student['programme_id']) {
        $stmt = $pdo->prepare("
            SELECT ic.*, c.name as course_name, c.code as course_code, c.credits
            FROM intake_courses ic
            JOIN course c ON ic.course_id = c.id
            WHERE ic.programme_id = ? AND ic.term = ?
            ORDER BY c.name
        ");
        $stmt->execute([$student['programme_id'], '1']);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Add courses to student data
    $student['courses'] = $courses;
    
    echo json_encode($student);
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>