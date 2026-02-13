<?php
require_once '../config.php';

header('Content-Type: application/json');

// Get parameters from request
$programme_id = isset($_GET['programme_id']) ? intval($_GET['programme_id']) : 0;
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

if ($programme_id <= 0) {
    echo json_encode(['error' => 'Invalid programme ID']);
    exit;
}

if ($session_id <= 0) {
    echo json_encode(['error' => 'Invalid session ID']);
    exit;
}

try {
    // Fetch programme fee for this programme and session
    $stmt = $pdo->prepare("
        SELECT pf.amount, pf.fee_description
        FROM programme_fees pf
        WHERE pf.programme_id = ? AND pf.session_id = ?
        AND pf.is_active = 1
        ORDER BY pf.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$programme_id, $session_id]);
    $fee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($fee) {
        echo json_encode(['amount' => $fee['amount'], 'description' => $fee['fee_description']]);
    } else {
        echo json_encode(['message' => 'No fee information found for this programme and session']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>