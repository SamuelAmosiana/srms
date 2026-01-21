<?php
// Minimal raw download script for enrollment
while (ob_get_level()) ob_end_clean();

require '../config.php';
require '../auth/auth.php';

// Check if user is logged in and has enrollment officer role
if (!currentUserId()) {
    http_response_code(403);
    exit('Access denied');
}

requireRole('Enrollment Officer', $pdo);

// DEBUG: Log incoming parameters and request information for troubleshooting
$requestInfo = [
    'GET_PARAMS' => $_GET,
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'N/A',
    'HTTP_REFERER' => $_SERVER['HTTP_REFERER'] ?? 'N/A',
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
    'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? 'N/A',
    'TIMESTAMP' => date('Y-m-d H:i:s'),
];
file_put_contents(__DIR__ . '/debug.log', print_r($requestInfo, true) . "\n\n", FILE_APPEND);

// Get the requested file parameters
$requested_file = trim($_GET['file'] ?? '');
$original_name = trim($_GET['original_name'] ?? '');

if (empty($requested_file)) {
    http_response_code(400);
    $errorInfo = [
        'ERROR' => 'File parameter is required',
        'GET_PARAMS' => $_GET,
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'N/A',
        'HTTP_REFERER' => $_SERVER['HTTP_REFERER'] ?? 'N/A',
        'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? 'N/A',
        'TIMESTAMP' => date('Y-m-d H:i:s'),
    ];
    file_put_contents(__DIR__ . '/error_debug.log', print_r($errorInfo, true) . "\n\n", FILE_APPEND);
    exit('File parameter is required');
}

// Sanitize the file path to prevent directory traversal
$sanitized_file = basename($requested_file);

// Build the path to the file
$path = __DIR__ . '/../uploads/' . $sanitized_file;

// Validate the file exists
if (!file_exists($path)) {
    http_response_code(404);
    exit('File not found');
}

// Validate the file is readable
if (!is_readable($path)) {
    http_response_code(403);
    exit('File not readable');
}

// Determine MIME type using mime_content_type for better accuracy
$mimetype = mime_content_type($path);

// Use original name if provided, otherwise use the sanitized file name
$download_filename = !empty($original_name) ? $original_name : basename($path);

// Send appropriate headers - using inline to open in browser rather than download
header('Content-Type: ' . $mimetype);
header('Content-Disposition: inline; filename="' . $download_filename . '"');
header('Content-Length: ' . filesize($path));

// Output the file content
readfile($path);
exit;