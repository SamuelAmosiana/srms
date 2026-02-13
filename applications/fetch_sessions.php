<?php
require_once '../config.php';

header('Content-Type: application/json');

try {
    // Fetch active sessions
    $stmt = $pdo->prepare("
        SELECT id, session_name, academic_year, term, start_date, end_date
        FROM academic_sessions
        WHERE status = 'active'
        ORDER BY start_date DESC
    ");
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($sessions) {
        echo json_encode(['sessions' => $sessions]);
    } else {
        echo json_encode(['message' => 'No active sessions available']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>