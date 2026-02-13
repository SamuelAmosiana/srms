<?php
require_once '../config.php';

header('Content-Type: application/json');

// Get parameters from request
$programme_id = isset($_GET['programme_id']) ? intval($_GET['programme_id']) : 0;

if ($programme_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid programme ID']);
    exit;
}

try {
    // Fetch programme fee for this programme (session_id not available in table)
    $stmt = $pdo->prepare("
        SELECT pf.fee_amount, pf.fee_name, pf.description
        FROM programme_fees pf
        WHERE pf.programme_id = ?
        AND pf.is_active = 1
        ORDER BY pf.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$programme_id]);
    $fee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($fee) {
        echo json_encode([
            'success' => true,
            'amount' => $fee['fee_amount'],
            'description' => $fee['fee_name'] . ' - ' . $fee['description']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No fee information found for this programme'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>