<?php
// Test the actual get_session_courses.php API endpoint with a browser-like request
session_start();
$_SESSION['user_id'] = 39; // Test with our known student
$_GET['session_id'] = 1;   // Test with known session

// Capture the output from the actual file
ob_start();
include 'student/get_session_courses.php';
$output = ob_get_clean();

echo "Raw output from get_session_courses.php:\n";
echo $output;
echo "\n\nEnd of output.\n";

// Also try to decode the JSON to see if it's valid
$data = json_decode($output, true);
if ($data) {
    echo "\nDecoded JSON:\n";
    print_r($data);
} else {
    echo "\nCould not decode JSON response.\n";
}
?>