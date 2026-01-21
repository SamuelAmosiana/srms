<?php
// Simple direct test - this should work if the download handler is correct
$test_filename = '1768550345_grade12results_5595ced2928e5c3e8726fa9a2beea4d4.webp';

echo "<h2>Simple Direct Download Test</h2>";
echo "<p>Testing with filename: {$test_filename}</p>";

// Check if file exists
$upload_path = __DIR__ . '/uploads/' . $test_filename;
if (file_exists($upload_path)) {
    echo "<p style='color: green;'>✅ File exists at: {$upload_path}</p>";
    echo "<p>File size: " . filesize($upload_path) . " bytes</p>";
    
    // Test the download URL
    $download_url = "/srms/enrollment/download_document.php?file=" . urlencode($test_filename);
    echo "<p><a href='{$download_url}' target='_blank' style='font-size: 18px; padding: 10px; background: #007cba; color: white; text-decoration: none; border-radius: 5px;'>Click to Test Download</a></p>";
    
    echo "<p>If this link works, the download handler is functioning correctly.</p>";
    echo "<p>If it fails, there's an issue with the download handler script.</p>";
    
} else {
    echo "<p style='color: red;'>❌ File does not exist at: {$upload_path}</p>";
    
    // List available webp files
    $webp_files = glob(__DIR__ . '/uploads/*.webp');
    if (!empty($webp_files)) {
        echo "<h3>Available WEBP files:</h3><ul>";
        foreach ($webp_files as $file) {
            $basename = basename($file);
            $test_url = "/srms/enrollment/download_document.php?file=" . urlencode($basename);
            echo "<li><a href='{$test_url}' target='_blank'>{$basename}</a></li>";
        }
        echo "</ul>";
    }
}
?>