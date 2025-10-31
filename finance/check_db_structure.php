<?php
require_once __DIR__ . '/../config.php';

echo "Checking Database Structure\n";
echo "========================\n\n";

// Check fee_types table
echo "1. Fee Types Table:\n";
try {
    $stmt = $pdo->query("SELECT * FROM fee_types LIMIT 3");
    $fee_types = $stmt->fetchAll();
    foreach ($fee_types as $fee_type) {
        echo "   - " . $fee_type['name'] . " (" . ($fee_type['is_active'] ? 'Active' : 'Inactive') . ")\n";
    }
    echo "   Total fee types: " . count($fee_types) . " (showing first 3)\n\n";
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n\n";
}

// Check programme_fees table
echo "2. Programme Fees Table:\n";
try {
    $stmt = $pdo->query("SELECT pf.*, p.name as programme_name FROM programme_fees pf JOIN programme p ON pf.programme_id = p.id LIMIT 3");
    $programme_fees = $stmt->fetchAll();
    foreach ($programme_fees as $fee) {
        echo "   - " . $fee['fee_name'] . " for " . $fee['programme_name'] . " (" . $fee['fee_type'] . "): K" . $fee['fee_amount'] . "\n";
    }
    echo "   Total programme fees: " . count($programme_fees) . " (showing first 3)\n\n";
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n\n";
}

// Check programmes table
echo "3. Programmes Table:\n";
try {
    $stmt = $pdo->query("SELECT * FROM programme LIMIT 3");
    $programmes = $stmt->fetchAll();
    foreach ($programmes as $programme) {
        echo "   - " . $programme['name'] . " (ID: " . $programme['id'] . ")\n";
    }
    echo "   Total programmes: " . count($programmes) . " (showing first 3)\n\n";
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n\n";
}

echo "Database structure check completed!\n";
?>