<?php
require '../config.php';
require '../auth/auth.php';

// Check if user is logged in and has enrollment officer role
if (!currentUserId()) {
    echo "Not logged in";
    exit;
}

requireRole('Enrollment Officer', $pdo);

// Test with the problematic filename from the error
$test_filename = '1768553887_grade12results_b2f6f6f5dcc06c5af5867b55e3d6179d.jpeg';
$original_name = 'images.jpeg';

echo "<h2>Improved Download Test</h2>";
echo "<p>Testing actual filename: $test_filename</p>";
echo "<p>Testing original name: $original_name</p>";

// Test the download handler logic
$sanitized_file = basename($test_filename);
$full_path = __DIR__ . '/../uploads/' . $sanitized_file;

echo "<p>Sanitized filename: $sanitized_file</p>";
echo "<p>Full path: $full_path</p>";
echo "<p>File exists: " . (file_exists($full_path) ? '<span style="color: green;">YES</span>' : '<span style="color: red;">NO</span>') . "</p>";

if (file_exists($full_path)) {
    echo "<p>File size: " . filesize($full_path) . " bytes</p>";
    echo "<p><a href='download_document.php?file=" . urlencode($test_filename) . "&original_name=" . urlencode($original_name) . "' target='_blank'>Click here to test improved download</a></p>";
    echo "<p>This test passes both the actual filename and the original name to the download handler.</p>";
} else {
    echo "<p style='color: red;'>File not found - there might be a path resolution issue.</p>";
}
?>