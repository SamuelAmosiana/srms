<?php
require_once 'config.php';

header('Content-Type: application/json');

// Get programme_id and term from request
$programme_id = isset($_GET['programme_id']) ? intval($_GET['programme_id']) : 0;
$term = isset($_GET['term']) ? trim($_GET['term']) : '1';

if ($programme_id <= 0) {
    echo json_encode(['error' => 'Invalid programme ID']);
    exit;
}

try {
    // Fetch defined courses for this programme and term
    $stmt = $pdo->prepare("
        SELECT ic.*, c.name as course_name, c.code as course_code, c.credits
        FROM intake_courses ic
        JOIN course c ON ic.course_id = c.id
        WHERE ic.programme_id = ? AND ic.term = ?
        ORDER BY c.name
    ");
    $stmt->execute([$programme_id, $term]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($courses)) {
        echo json_encode(['message' => 'No courses defined for this programme yet.']);
    } else {
        echo json_encode(['courses' => $courses]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>