<?php
// Direct test of the download functionality with an actual file
$test_file = '1768550345_grade12results_5595ced2928e5c3e8726fa9a2beea4d4.webp';
$upload_dir = __DIR__ . '/uploads/';
$full_path = $upload_dir . $test_file;

echo "<h2>Direct Download Test</h2>";

if (file_exists($full_path)) {
    echo "<p>✅ File exists: {$test_file}</p>";
    echo "<p>Full path: {$full_path}</p>";
    echo "<p>File size: " . filesize($full_path) . " bytes</p>";
    
    // Test the download URL construction
    $download_url = "enrollment/download_document.php?file=" . urlencode($test_file);
    echo "<p>Download URL: <a href='{$download_url}' target='_blank'>{$download_url}</a></p>";
    
    // Also test with original name parameter
    $download_url_with_name = "enrollment/download_document.php?file=" . urlencode($test_file) . "&original_name=test.webp";
    echo "<p>Download URL with name: <a href='{$download_url_with_name}' target='_blank'>{$download_url_with_name}</a></p>";
    
} else {
    echo "<p>❌ File does not exist: {$test_file}</p>";
}

// Also test the raw file access
$raw_file_url = "uploads/{$test_file}";
echo "<p>Raw file URL: <a href='{$raw_file_url}' target='_blank'>{$raw_file_url}</a></p>";
?>