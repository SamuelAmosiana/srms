<?php
require 'config.php';
require 'auth.php';

// Check if user is logged in
if (!currentUserId()) {
    header('Location: login.php');
    exit;
}

// Get the file parameter
if (!isset($_GET['file']) || empty($_GET['file'])) {
    die('No file specified.');
}

$filename = basename($_GET['file']);
$filepath = __DIR__ . '/letters/' . $filename;

// Check if file exists
if (!file_exists($filepath)) {
    die('File not found.');
}

// Check file extension for security
$allowed_extensions = ['docx', 'txt'];
$file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_extensions)) {
    die('Invalid file type.');
}

// Set headers for download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));

// Clear output buffer
ob_clean();
flush();

// Read the file and output it
readfile($filepath);
exit;
?>