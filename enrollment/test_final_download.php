<?php
require '../config.php';
require '../auth/auth.php';

// Check if user is logged in and has enrollment officer role
if (!currentUserId()) {
    echo "Not logged in";
    exit;
}

requireRole('Enrollment Officer', $pdo);

// Test with the actual problematic filename from the error
$test_filename = '1768550345_grade12results_5595ced2928e5c3e8726fa9a2beea4d4.webp';

echo "<h2>Final Download Test</h2>";
echo "<p>Testing filename: $test_filename</p>";

// Test the download handler logic
$sanitized_file = basename($test_filename);
$full_path = __DIR__ . '/../uploads/' . $sanitized_file;

echo "<p>Sanitized filename: $sanitized_file</p>";
echo "<p>Full path: $full_path</p>";
echo "<p>File exists: " . (file_exists($full_path) ? '<span style="color: green;">YES</span>' : '<span style="color: red;">NO</span>') . "</p>";

if (file_exists($full_path)) {
    echo "<p>File size: " . filesize($full_path) . " bytes</p>";
    echo "<p><a href='download_document.php?file=" . urlencode($test_filename) . "' target='_blank'>Click here to test download</a></p>";
    echo "<p>If the download works, the issue was in the JavaScript filename extraction.</p>";
} else {
    echo "<p style='color: red;'>File not found - there might be a path resolution issue.</p>";
}
?>