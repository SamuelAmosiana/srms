<?php
// Simple test to check what format is being received
header('Content-Type: text/plain');

echo "Format parameter: " . ($_GET['format'] ?? 'NOT SET') . "\n";
echo "All GET parameters: " . print_r($_GET, true) . "\n";

$format = strtolower(trim($_GET['format'] ?? 'pdf'));
echo "Processed format: " . $format . "\n";

if ($format === 'excel' || $format === 'csv') {
    echo "Would export CSV\n";
} elseif ($format === 'pdf') {
    echo "Would export PDF\n";
} else {
    echo "Unknown format\n";
}
?>