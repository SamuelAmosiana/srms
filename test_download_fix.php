<?php
/**
 * Test file to verify the download functionality fix
 */
require_once 'includes/SecureDownload.php';

// Check if test parameters are provided
$test_file = $_GET['test_file'] ?? '';
$test_name = $_GET['test_name'] ?? '';

if (empty($test_file)) {
    echo "<h2>Download Functionality Test</h2>";
    echo "<p>This script tests the secure download functionality.</p>";
    
    // List some files in the uploads directory for testing
    $upload_dir = __DIR__ . '/uploads';
    if (is_dir($upload_dir)) {
        $files = array_slice(scandir($upload_dir), 2); // Skip . and ..
        echo "<h3>Available files in uploads directory:</h3>";
        echo "<ul>";
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $file_path = urlencode($file);
                $file_url = $_SERVER['PHP_SELF'] . "?test_file={$file_path}&test_name={$file_path}";
                echo "<li><a href='{$file_url}'>{$file}</a></li>";
            }
        }
        echo "</ul>";
    } else {
        echo "<p>Uploads directory not found!</p>";
    }
    
    echo "<h3>Manual test:</h3>";
    echo "<form method='get'>";
    echo "<label>File to test: <input type='text' name='test_file' placeholder='filename.ext'></label><br><br>";
    echo "<label>Download name (optional): <input type='text' name='test_name' placeholder='download_name.ext'></label><br><br>";
    echo "<button type='submit'>Test Download</button>";
    echo "</form>";
} else {
    // Validate the file path to prevent directory traversal
    $upload_dir = __DIR__ . '/uploads';
    $full_path = SecureDownload::validateFilePath($test_file, $upload_dir);
    
    if ($full_path === false || !file_exists($full_path)) {
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['error' => 'File not found or invalid path']);
        exit;
    }
    
    // Use provided name or default to original name
    $download_name = !empty($test_name) ? urldecode($test_name) : basename($full_path);
    
    // Attempt to download the file
    if (!SecureDownload::downloadFile($full_path, $download_name)) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Failed to download file']);
        exit;
    }
}
?>