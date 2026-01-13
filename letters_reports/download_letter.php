<?php
require '../config.php';
require '../auth/auth.php';

// Resolve requested filename from multiple URL styles
$requested = '';
if (isset($_GET['file']) && $_GET['file'] !== '') {
    $requested = (string)$_GET['file'];
} elseif (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) {
    // Support links like /download_letter.php?id=66
    $requested = 'acceptance_letter_' . $_GET['id'] . '.pdf';
} else {
    // Support path-style: /download_letter.php/acceptance_letter_66.pdf or /download_letter/acceptance_letter_66.pdf
    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    if (!$pathInfo) {
        $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if ($uriPath) {
            if (preg_match('#/download_letter(?:\\.php)?/(.+)$#', $uriPath, $m)) {
                $pathInfo = '/' . $m[1];
            }
        }
    }
    if ($pathInfo) {
        $requested = ltrim($pathInfo, '/');
    }
}

if ($requested === '') {
    die('No file specified.');
}

$filename = basename($requested);
// Default to PDF if no extension provided
if (pathinfo($filename, PATHINFO_EXTENSION) === '') {
    $filename .= '.pdf';
}
$filepath = __DIR__ . '/letters/' . $filename;

// Check if file exists
if (!file_exists($filepath)) {
    // Try to regenerate the letter if it doesn't exist
    $file_parts = explode('_', $filename);
    if (count($file_parts) >= 3 && $file_parts[0] === 'acceptance' && $file_parts[1] === 'letter') {
        // Remove both .pdf and .docx extensions
        $app_id_part = str_replace(['.pdf', '.docx'], '', $file_parts[2]);
        $application_id = (int)$app_id_part;
        if ($application_id > 0) {
            // Redirect to regenerate script
            header('Location: regenerate_letter.php?application_id=' . $application_id);
            exit;
        }
    }
    die('File not found. Please contact support.');
}

// Check file extension for security
$allowed_extensions = ['docx', 'txt', 'pdf'];
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
