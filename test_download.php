<?php
// Minimal raw test script for download functionality
while (ob_get_level()) ob_end_clean();

// Get the filename from GET parameter
$file = basename($_GET['file'] ?? '');

// Validate that a file was provided
if (empty($file)) {
    http_response_code(400);
    exit("File parameter is required");
}

// Build the path to the file
$path = __DIR__ . '/uploads/' . $file;

// Validate the file exists
if (!file_exists($path)) {
    http_response_code(404);
    exit("File not found");
}

// Validate the file is readable
if (!is_readable($path)) {
    http_response_code(403);
    exit("File not readable");
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

// Send appropriate headers
header('Content-Description: File Transfer');
header('Content-Type: ' . $mimetype);
header('Content-Disposition: attachment; filename="' . basename($path) . '"');
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