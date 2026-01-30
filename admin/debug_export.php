<?php
// Test file to debug the export issue
header('Content-Type: text/plain');

$type = $_GET['type'] ?? 'all';
$programme_id = (int)($_GET['programme_id'] ?? 0);
$format = strtolower(trim($_GET['format'] ?? 'pdf'));

echo "Parameters received:\n";
echo "type: $type\n";
echo "programme_id: $programme_id\n";
echo "format: $format\n";
echo "\n";

echo "Processing logic:\n";
if ($format === 'excel' || $format === 'csv') {
    echo "Would export CSV\n";
    echo "Headers that would be sent:\n";
    echo "Content-Type: text/csv; charset=UTF-8\n";
    echo "Content-Disposition: attachment; filename=\"approved_students_" . date('Y-m-d') . ".csv\"\n";
} elseif ($format === 'pdf') {
    echo "Would export PDF\n";
    echo "Headers that would be sent:\n";
    echo "Content-Type: application/pdf\n";
    echo "Content-Disposition: attachment; filename=\"approved_students_" . date('Y-m-d') . ".pdf\"\n";
} else {
    echo "Unknown format: $format\n";
}
?>