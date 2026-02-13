<?php
require_once '../config.php';

header('Content-Type: application/json');

// Get parameters from request
$programme_id = isset($_GET['programme_id']) ? intval($_GET['programme_id']) : 0;
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
$term = isset($_GET['term']) ? trim($_GET['term']) : '1';

if ($programme_id <= 0) {
    echo json_encode(['error' => 'Invalid programme ID']);
    exit;
}

if ($session_id <= 0) {
    echo json_encode(['error' => 'Invalid session ID']);
    exit;
}

try {
    // Fetch courses for this programme and session and term
    $stmt = $pdo->prepare("
        SELECT c.name as course_name, c.code as course_code, c.credits, c.description
        FROM session_programme_courses spc
        JOIN course c ON spc.course_id = c.id
        WHERE spc.programme_id = ? AND spc.session_id = ? AND c.term = ?
        ORDER BY c.code
    ");
    $stmt->execute([$programme_id, $session_id, $term]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($courses)) {
        // Try to fetch courses from intake_courses as fallback
        $stmt = $pdo->prepare("
            SELECT c.name as course_name, c.code as course_code, c.credits, c.description
            FROM intake_courses ic
            JOIN course c ON ic.course_id = c.id
            WHERE ic.programme_id = ? AND ic.term = ?
            ORDER BY c.code
        ");
        $stmt->execute([$programme_id, $term]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($courses)) {
            echo json_encode(['message' => 'No courses defined for this programme yet.']);
        } else {
            echo json_encode(['courses' => $courses]);
        }
    } else {
        echo json_encode(['courses' => $courses]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>