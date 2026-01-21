<?php
/**
 * Final test for download functionality
 */
require_once 'includes/SecureDownload.php';

echo "<h2>Final Download Functionality Test</h2>";

// Test with a specific problematic file
$problematic_file = '1768550345_grade12results_5595ced2928e5c3e8726fa9a2beea4d4.webp';
$upload_dir = __DIR__ . '/uploads';
$full_path = $upload_dir . '/' . $problematic_file;

if (file_exists($full_path)) {
    echo "<p>✅ File exists: {$problematic_file}</p>";
    echo "<p>File size: " . filesize($full_path) . " bytes</p>";
    
    // Test validation
    $validated_path = SecureDownload::validateFilePath($problematic_file, $upload_dir);
    if ($validated_path) {
        echo "<p>✅ File path validation passed</p>";
    } else {
        echo "<p>❌ File path validation failed</p>";
    }
    
    // Show download link for manual testing
    echo "<p><a href='/srms/enrollment/download_document.php?file=" . urlencode($problematic_file) . "&original_name=test_file.webp' target='_blank'>Test Download Link</a> (Requires Enrollment Officer role)</p>";
    echo "<p><a href='/srms/admin/download_document.php?file=" . urlencode($problematic_file) . "&original_name=test_file.webp' target='_blank'>Test Admin Download Link</a> (Requires Admin role)</p>";
} else {
    echo "<p>❌ File does not exist: {$problematic_file}</p>";
    
    // List some available files
    $files = array_filter(scandir($upload_dir), function($file) {
        return $file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'webp';
    });
    
    if (!empty($files)) {
        echo "<p>Available WEBP files:</p><ul>";
        foreach ($files as $file) {
            echo "<li>{$file}</li>";
        }
        echo "</ul>";
    }
}

// Test utility function
echo "<h3>Testing SecureDownload Utility</h3>";
echo "<p>Utility class loaded: " . (class_exists('SecureDownload') ? '✅' : '❌') . "</p>";
echo "<p>Methods available: " . (method_exists('SecureDownload', 'downloadFile') ? '✅' : '❌') . "</p>";
echo "<p>Validation method: " . (method_exists('SecureDownload', 'validateFilePath') ? '✅' : '❌') . "</p>";
echo "<p>JSON response method: " . (method_exists('SecureDownload', 'sendJsonResponse') ? '✅' : '❌') . "</p>";
?>