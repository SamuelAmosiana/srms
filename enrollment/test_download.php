<?php
// Test the download handler with a known file
$test_file = '1768550345_grade12results_5595ced2928e5c3e8726fa9a2beea4d4.webp';

echo "Testing download for file: $test_file<br>";

// Simulate what the download handler does
$sanitized_file = basename($test_file);
$full_path = __DIR__ . '/../uploads/' . $sanitized_file;

echo "Sanitized file: $sanitized_file<br>";
echo "Full path: $full_path<br>";
echo "File exists: " . (file_exists($full_path) ? 'YES' : 'NO') . "<br>";

if (file_exists($full_path)) {
    echo "File size: " . filesize($full_path) . " bytes<br>";
    echo "<a href='/srms/enrollment/download_document.php?file=" . urlencode($test_file) . "'>Try Download</a><br>";
} else {
    echo "File not found!<br>";
}
?>