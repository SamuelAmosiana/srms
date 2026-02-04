<?php
/**
 * Swish Payment Processing Endpoint
 * Handles the Swish payment initiation and callback processing
 */

require_once '../config.php';

// Set content type to JSON for API responses
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get the transaction reference from the request
$transaction_ref = $_POST['transaction_reference'] ?? $_GET['transaction_reference'] ?? '';

if (empty($transaction_ref)) {
    http_response_code(400);
    echo json_encode(['error' => 'Transaction reference is required']);
    exit;
}

try {
    // Get payment details from the database
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE transaction_reference = ?");
    $stmt->execute([$transaction_ref]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        http_response_code(404);
        echo json_encode(['error' => 'Payment not found']);
        exit;
    }
    
    // In a real implementation, this would initiate the Swish payment
    // For now, we'll simulate the process
    
    // Log the Swish initiation
    $logStmt = $pdo->prepare("INSERT INTO payment_logs (transaction_reference, raw_response, action_taken) VALUES (?, ?, 'initiated')");
    $logStmt->execute([
        $transaction_ref,
        json_encode([
            'status' => 'initiated',
            'timestamp' => date('Y-m-d H:i:s'),
            'phone_number' => $payment['phone_number'],
            'amount' => $payment['amount']
        ])
    ]);
    
    // In a real implementation, this would trigger the STK Push/Sim Toolkit
    // For now, we'll return a simulated response
    $response = [
        'success' => true,
        'transaction_reference' => $transaction_ref,
        'message' => 'Swish payment initiated successfully',
        'instructions' => 'Please complete the payment on your mobile device',
        'estimated_completion_time' => '2 minutes'
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>