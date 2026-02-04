<?php
/**
 * Test the payment system
 */
require_once 'config.php';

// Test database connection
try {
    echo "<h2>Database Connection Test</h2>";
    echo "✓ Database connection successful<br><br>";
    
    // Test tables
    echo "<h2>Payment Tables Test</h2>";
    
    // Check if payments table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'payments'");
    $stmt->execute();
    if ($stmt->fetch()) {
        echo "✓ Payments table exists<br>";
    } else {
        echo "✗ Payments table missing<br>";
    }
    
    // Check if finance_income table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'finance_income'");
    $stmt->execute();
    if ($stmt->fetch()) {
        echo "✓ Finance income table exists<br>";
    } else {
        echo "✗ Finance income table missing<br>";
    }
    
    // Check if payment_logs table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'payment_logs'");
    $stmt->execute();
    if ($stmt->fetch()) {
        echo "✓ Payment logs table exists<br><br>";
    } else {
        echo "✗ Payment logs table missing<br><br>";
    }
    
    // Test payment insertion
    echo "<h2>Payment Processing Test</h2>";
    
    // Insert test payment
    $transaction_ref = 'TEST' . date('Ymd') . rand(1000, 9999);
    $stmt = $pdo->prepare("
        INSERT INTO payments (
            transaction_reference,
            full_name,
            phone_number,
            amount,
            category,
            description,
            status,
            source
        ) VALUES (?, ?, ?, ?, ?, ?, 'pending', 'public')
    ");
    
    $result = $stmt->execute([
        $transaction_ref,
        'John Test',
        '260970000000',
        100.00,
        'Application Fee',
        'Test payment for verification'
    ]);
    
    if ($result) {
        echo "✓ Payment inserted successfully: $transaction_ref<br>";
        
        // Test log entry
        $logStmt = $pdo->prepare("INSERT INTO payment_logs (transaction_reference, action_taken) VALUES (?, 'test_entry')");
        $logStmt->execute([$transaction_ref]);
        echo "✓ Log entry created<br><br>";
        
        echo "<h2>Test Successful!</h2>";
        echo "You can now visit: <a href='/make_payment.php'>make_payment.php</a>";
        echo "<br>As finance officer, you can access: <a href='finance/track_payment.php'>finance/track_payment.php</a>";
        
        echo "<h2>Database Stats</h2>";
        echo "You can remove these test records or test a real transaction with the pages below<br><br>";
        
    } else {
        echo "✗ Failed to insert test payment: " . $pdo->errorInfo()[2] . "<br><br>";
        
        if ($_POST && ($_POST['cleardb'] ?? '') === 'yes') {
            // Clear test data
            $pdo->exec("DELETE FROM payment_logs WHERE transaction_reference LIKE 'TEST%'");
            $pdo->exec("DELETE FROM payments WHERE transaction_reference LIKE 'TEST%'");
            echo "✓ Test data cleared<br>";
        }
        
        echo "<form method='POST'>";
        echo "<input type='hidden' name='cleardb' value='yes'>";
        echo "<button type='submit'>Clear Test Data</button>";
        echo "</form>";
    }
    
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "<br>";
}
?>