<?php
require_once __DIR__ . '/../config.php';

echo "Testing Complete Fee Management System\n";
echo "=====================================\n\n";

// Test 1: Check if fee_types table exists and has data
echo "1. Testing fee_types table...\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM fee_types WHERE is_active = 1");
    $count = $stmt->fetch()['count'];
    echo "   ✓ fee_types table exists with $count active fee types\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 2: Check if programme_fees table exists
echo "\n2. Testing programme_fees table...\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM programme_fees");
    $count = $stmt->fetch()['count'];
    echo "   ✓ programme_fees table exists with $count fees\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 3: Test the generateAcceptanceLetterWithFees function
echo "\n3. Testing acceptance letter generation...\n";
try {
    // Include the function
    require_once 'generate_acceptance_letter_with_fees.php';
    
    // Create a sample application
    $sample_application = [
        'id' => 999,
        'full_name' => 'Test Student',
        'email' => 'test.student@lsc.ac.zm',
        'programme_name' => 'Diploma in Business Administration',
        'intake_name' => 'July 2025',
        'programme_id' => 1
    ];
    
    // Generate the letter
    $letter_path = generateAcceptanceLetterWithFees($sample_application, $pdo);
    
    if (file_exists($letter_path)) {
        echo "   ✓ Acceptance letter generated successfully\n";
        echo "   ✓ Letter saved at: $letter_path\n";
        
        // Check if the letter contains expected content
        $letter_content = file_get_contents($letter_path);
        if (strpos($letter_content, 'FEE STRUCTURE') !== false) {
            echo "   ✓ Letter contains fee structure\n";
        } else {
            echo "   ✗ Letter missing fee structure\n";
        }
    } else {
        echo "   ✗ Failed to generate acceptance letter\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 4: Test adding a new fee type
echo "\n4. Testing fee type management...\n";
try {
    // Add a new fee type
    $stmt = $pdo->prepare("INSERT INTO fee_types (name, description) VALUES (?, ?)");
    $stmt->execute(['Test Fee Type', 'This is a test fee type for system verification']);
    
    // Get the ID of the inserted fee type
    $fee_type_id = $pdo->lastInsertId();
    echo "   ✓ Added test fee type with ID: $fee_type_id\n";
    
    // Verify it was added
    $stmt = $pdo->prepare("SELECT * FROM fee_types WHERE id = ?");
    $stmt->execute([$fee_type_id]);
    $fee_type = $stmt->fetch();
    
    if ($fee_type) {
        echo "   ✓ Verified fee type exists: " . $fee_type['name'] . "\n";
    } else {
        echo "   ✗ Could not verify fee type was added\n";
    }
    
    // Clean up - delete the test fee type
    $stmt = $pdo->prepare("DELETE FROM fee_types WHERE id = ?");
    $stmt->execute([$fee_type_id]);
    echo "   ✓ Cleaned up test fee type\n";
    
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

echo "\nTest completed!\n";
?>