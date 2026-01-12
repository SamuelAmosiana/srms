<?php
require_once '../config.php';

// Test to see recent transactions
echo "Checking recent transactions in finance_transactions table:\n";

try {
    $stmt = $pdo->query("SELECT * FROM finance_transactions ORDER BY created_at DESC LIMIT 10");
    $transactions = $stmt->fetchAll();
    
    if (empty($transactions)) {
        echo "No transactions found in the database.\n";
    } else {
        echo "Found " . count($transactions) . " recent transactions:\n";
        foreach ($transactions as $trans) {
            echo "  - " . $trans['type'] . ": " . $trans['description'] . " - K" . $trans['amount'] . " on " . $trans['created_at'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error accessing finance_transactions table: " . $e->getMessage() . "\n";
}

// Clean up - delete this test file
unlink(__FILE__);
?>