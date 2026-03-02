<?php
/**
 * Secure document download handler for Enrollment Officers
 * This script serves documents uploaded by applicants and system-generated acceptance letters
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

// Check if user is logged in
if (!currentUserId()) {
    http_response_code(401);
    exit('Unauthorized - Please log in');
}

// Verify user has Enrollment Officer role
requireRole('Enrollment Officer', $pdo);

// Get parameters
$file_type = $_GET['file_type'] ?? '';
$application_id = $_GET['application_id'] ?? 0;
$view_mode = isset($_GET['view']) ? true : false;
$download_mode = isset($_GET['download']) ? true : false;

// Validate application ID
if (!$application_id || !is_numeric($application_id)) {
    http_response_code(400);
    exit('Invalid application ID');
}

// Validate file type
$allowed_types = ['nrc', 'academic_results', 'previous_school', 'acceptance_letter'];
if (!in_array($file_type, $allowed_types)) {
    http_response_code(400);
    exit('Invalid file type');
}

try {
    // Get application details to verify access
    $stmt = $pdo->prepare("
        SELECT a.*, p.name as programme_name, i.name as intake_name
        FROM applications a
        LEFT JOIN programme p ON a.programme_id = p.id
        LEFT JOIN intake i ON a.intake_id = i.id
        WHERE a.id = ?
    ");
    $stmt->execute([$application_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        http_response_code(404);
        exit('Application not found');
    }
    
    $file_path = null;
    $file_name = null;
    
    // Handle acceptance letter separately
    if ($file_type === 'acceptance_letter') {
        $file_path = __DIR__ . '/../letters_reports/letters/acceptance_letter_' . $application_id . '.pdf';
        $file_name = 'acceptance_letter_' . $application_id . '.pdf';
        
        if (!file_exists($file_path)) {
            http_response_code(404);
            exit('Acceptance letter not found. It may not have been generated yet.');
        }
    } else {
        // Handle uploaded documents from applications table
        if (empty($application['documents'])) {
            http_response_code(404);
            exit('No documents found for this application');
        }
        
        $documents = json_decode($application['documents'], true);
        
        if (!is_array($documents)) {
            http_response_code(404);
            exit('Invalid document data format');
        }
        
        // Find the requested document
        foreach ($documents as $doc) {
            if (!is_array($doc) || !isset($doc['name']) || !isset($doc['path'])) {
                continue;
            }
            
            $found = false;
            
            switch ($file_type) {
                case 'nrc':
                    if (stripos($doc['name'], 'nrc') !== false || stripos($doc['name'], 'national') !== false) {
                        $found = true;
                    }
                    break;
                    
                case 'academic_results':
                    if (stripos($doc['name'], 'grade12') !== false || 
                        stripos($doc['name'], 'results') !== false || 
                        stripos($doc['name'], 'academic') !== false) {
                        $found = true;
                    }
                    break;
                    
                case 'previous_school':
                    if (stripos($doc['name'], 'previous') !== false || stripos($doc['name'], 'school') !== false) {
                        $found = true;
                    }
                    break;
            }
            
            if ($found) {
                $file_path = __DIR__ . '/../' . $doc['path'];
                $file_name = $doc['name'];
                break;
            }
        }
        
        if (!$file_path || !file_exists($file_path)) {
            http_response_code(404);
            exit('Document not found');
        }
    }
    
    // Security check: ensure the resolved path is within allowed directories
    $real_path = realpath($file_path);
    $base_upload_path = realpath(__DIR__ . '/../uploads');
    $base_letters_path = realpath(__DIR__ . '/../letters_reports/letters');
    
    if (!$real_path) {
        http_response_code(404);
        exit('File not found');
    }
    
    // Verify the file is in an allowed directory
    if (strpos($real_path, $base_upload_path) !== 0 && strpos($real_path, $base_letters_path) !== 0) {
        http_response_code(403);
        exit('Access denied - Invalid file location');
    }
    
    // Determine MIME type based on file extension
    $extension = strtolower(pathinfo($real_path, PATHINFO_EXTENSION));
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
    
    // Set headers based on mode (view or download)
    if ($view_mode) {
        // View mode - display inline
        header('Content-Type: ' . $mimetype);
        header('Content-Disposition: inline; filename="' . basename($file_name) . '"');
    } else {
        // Download mode - force download
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mimetype);
        header('Content-Disposition: attachment; filename="' . basename($file_name) . '"');
    }
    
    header('Content-Length: ' . filesize($real_path));
    header('Cache-Control: no-cache');
    header('Pragma: public');
    header('Expires: 0');
    header('Accept-Ranges: none');
    
    // Clear output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Send file content
    readfile($real_path);
    exit;
    
} catch (Exception $e) {
    error_log('Document access error: ' . $e->getMessage());
    http_response_code(500);
    exit('An error occurred while accessing the document');
}
?>
