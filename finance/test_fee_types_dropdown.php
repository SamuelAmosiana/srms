<?php
require_once __DIR__ . '/../config.php';

// Fetch all fee types
$stmt = $pdo->query("SELECT * FROM fee_types WHERE is_active = 1 ORDER BY name");
$fee_types = $stmt->fetchAll();

echo "Active Fee Types:\n";
foreach ($fee_types as $fee_type) {
    echo "- " . $fee_type['name'] . "\n";
}

echo "\nTotal active fee types: " . count($fee_types) . "\n";
?>