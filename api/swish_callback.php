<?php
/**
 * Swish Payment Callback Handler
 * Processes callbacks from Swish payment provider
 */

require_once '../config.php';

// Set content type to JSON for API responses
header('Content-Type: application/json');

// Only accept POST requests (typical for payment callbacks)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get raw POST data
$raw_post_data = file_get_contents('php://input');
$post_data = json_decode($raw_post_data, true);

// Validate the callback data
if (!$post_data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON data']);
    exit;
}

// Extract transaction details from callback
$transaction_ref = $post_data['transaction_reference'] ?? $post_data['reference'] ?? '';
$status = $post_data['status'] ?? $post_data['payment_status'] ?? '';
$amount = $post_data['amount'] ?? 0;
$phone_number = $post_data['phone_number'] ?? $post_data['customer_phone'] ?? '';

if (empty($transaction_ref)) {
    http_response_code(400);
    echo json_encode(['error' => 'Transaction reference is required']);
    exit;
}

try {
    // Verify the transaction exists in our system
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE transaction_reference = ?");
    $stmt->execute([$transaction_ref]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        http_response_code(404);
        echo json_encode(['error' => 'Payment not found']);
        exit;
    }
    
    // Log the callback received
    $logStmt = $pdo->prepare("INSERT INTO payment_logs (transaction_reference, raw_response, action_taken) VALUES (?, ?, 'callback_received')");
    $logStmt->execute([
        $transaction_ref,
        json_encode($post_data)
    ]);
    
    // Process the callback based on status
    if ($status === 'completed' || $status === 'successful' || $status === 'paid') {
        // Update payment status to confirmed
        $updateStmt = $pdo->prepare("UPDATE payments SET status = 'confirmed', updated_at = NOW() WHERE transaction_reference = ?");
        $updateResult = $updateStmt->execute([$transaction_ref]);
        
        if ($updateResult) {
            // Record in finance income
            $incomeStmt = $pdo->prepare("
                INSERT INTO finance_income (
                    transaction_reference,
                    amount,
                    category,
                    recorded_by,
                    recorded_at
                ) VALUES (?, ?, ?, NULL, NOW())
            ");
            
            $incomeStmt->execute([
                $transaction_ref,
                $payment['amount'], // Use the original amount to prevent tampering
                $payment['category']
            ]);
            
            // Log the confirmation
            $logStmt = $pdo->prepare("INSERT INTO payment_logs (transaction_reference, raw_response, action_taken) VALUES (?, ?, 'confirmed')");
            $logStmt->execute([
                $transaction_ref,
                json_encode(['status' => 'confirmed', 'callback_data' => $post_data])
            ]);
            
            // Return success response
            $response = [
                'success' => true,
                'transaction_reference' => $transaction_ref,
                'message' => 'Payment confirmed successfully',
                'status' => 'confirmed'
            ];
        } else {
            // Log failure
            $logStmt = $pdo->prepare("INSERT INTO payment_logs (transaction_reference, raw_response, action_taken) VALUES (?, ?, 'confirmation_failed')");
            $logStmt->execute([
                $transaction_ref,
                json_encode(['error' => 'Failed to update payment status'])
            ]);
            
            $response = [
                'success' => false,
                'transaction_reference' => $transaction_ref,
                'message' => 'Failed to confirm payment',
                'error' => 'Database update failed'
            ];
            http_response_code(500);
        }
    } elseif ($status === 'failed' || $status === 'cancelled' || $status === 'rejected') {
        // Update payment status to rejected
        $updateStmt = $pdo->prepare("UPDATE payments SET status = 'rejected', updated_at = NOW() WHERE transaction_reference = ?");
        $updateResult = $updateStmt->execute([$transaction_ref]);
        
        if ($updateResult) {
            // Log the rejection
            $logStmt = $pdo->prepare("INSERT INTO payment_logs (transaction_reference, raw_response, action_taken) VALUES (?, ?, 'rejected')");
            $logStmt->execute([
                $transaction_ref,
                json_encode(['status' => 'rejected', 'reason' => $post_data['reason'] ?? 'Payment failed'])
            ]);
            
            $response = [
                'success' => true,
                'transaction_reference' => $transaction_ref,
                'message' => 'Payment rejected as per provider callback',
                'status' => 'rejected'
            ];
        } else {
            $response = [
                'success' => false,
                'transaction_reference' => $transaction_ref,
                'message' => 'Failed to reject payment',
                'error' => 'Database update failed'
            ];
            http_response_code(500);
        }
    } else {
        // Unknown status - log for review
        $logStmt = $pdo->prepare("INSERT INTO payment_logs (transaction_reference, raw_response, action_taken) VALUES (?, ?, 'unknown_status')");
        $logStmt->execute([
            $transaction_ref,
            json_encode(['status' => $status, 'data' => $post_data])
        ]);
        
        $response = [
            'success' => false,
            'transaction_reference' => $transaction_ref,
            'message' => 'Unknown payment status received',
            'status' => $status
        ];
        http_response_code(400);
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log the error
    if (!empty($transaction_ref)) {
        $logStmt = $pdo->prepare("INSERT INTO payment_logs (transaction_reference, raw_response, action_taken) VALUES (?, ?, 'error')");
        $logStmt->execute([
            $transaction_ref,
            json_encode(['error' => $e->getMessage()])
        ]);
    }
    
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>