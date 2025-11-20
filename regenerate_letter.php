<?php
require_once 'config.php';
require_once 'generate_acceptance_letter_docx.php';

// This script regenerates an acceptance letter for a specific application ID
// Usage: http://yoursite.com/srms/regenerate_letter.php?application_id=39

if (!isset($_GET['application_id']) || empty($_GET['application_id'])) {
    die('Application ID is required.');
}

$application_id = (int)$_GET['application_id'];

try {
    // Get application details
    $stmt = $pdo->prepare("
        SELECT a.*, p.name as programme_name, i.name as intake_name, p.id as programme_id
        FROM applications a
        LEFT JOIN programme p ON a.programme_id = p.id
        LEFT JOIN intake i ON a.intake_id = i.id
        WHERE a.id = ?
    ");
    $stmt->execute([$application_id]);
    $application = $stmt->fetch();
    
    if (!$application) {
        die('Application not found.');
    }
    
    // Generate the acceptance letter
    $letter_path = generateAcceptanceLetterDOCX($application, $pdo);
    
    echo "Letter regenerated successfully!<br>";
    echo "File path: " . $letter_path . "<br>";
    echo "File exists: " . (file_exists($letter_path) ? 'Yes' : 'No') . "<br>";
    echo "<a href='download_letter.php?file=" . basename($letter_path) . "'>Download Letter</a>";
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>