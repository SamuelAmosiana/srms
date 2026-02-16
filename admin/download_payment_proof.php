<?php
// Secure download script for payment proofs in admin section
while (ob_get_level()) ob_end_clean();

require '../config.php';
require '../auth/auth.php';

// Check if user is logged in and has admin role
if (!currentUserId()) {
    header('Location: ../auth/login.php');
    exit;
}

requireRole('Super Admin', $pdo);

// Get the requested file parameters
$requested_file = trim($_GET['file'] ?? '');
$original_name = trim($_GET['original_name'] ?? '');

if (empty($requested_file)) {
    http_response_code(400);
    exit('File parameter is required');
}

// Sanitize the file path to prevent directory traversal
$sanitized_file = basename($requested_file);

// Build the path to the file - database stores full path like uploads/payment_proofs/filename
$path = __DIR__ . '/../' . $sanitized_file;

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

// Determine MIME type based on file extension
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime_types = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp'
];

$mimetype = $mime_types[$extension] ?? 'application/octet-stream';

// Use original name if provided, otherwise use the sanitized file name
$download_filename = !empty($original_name) ? $original_name : basename($path);

// Send appropriate headers - using inline to open in browser rather than download
header('Content-Description: File Transfer');
header('Content-Type: ' . $mimetype);
header('Content-Disposition: inline; filename="' . $download_filename . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-cache');
header('Pragma: public');
header('Expires: 0');
header('Accept-Ranges: none');

// Flush headers
flush();

// Output the file content
readfile($path);
exit;
?>