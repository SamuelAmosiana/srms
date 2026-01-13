<?php
// Test script to verify the new DOMPDF functionality
require_once 'config.php';
require_once 'generate_acceptance_letter_dompdf.php';

echo "<h2>Testing DOMPDF Acceptance Letter Generation</h2>\n";

// Create a sample application array to test with
$sample_application = [
    'id' => 999,
    'full_name' => 'John Doe',
    'email' => 'john.doe@example.com',
    'phone' => '+260971234567',
    'nationality' => 'Zambian',
    'programme_name' => 'Bachelor of Science in Computer Science',
    'intake_name' => 'January 2026 Intake',
    'duration' => '3 Years',
    'mode_of_learning' => 'Physical',
    'programme_id' => 1
];

try {
    echo "<p>Attempting to generate acceptance letter PDF...</p>\n";
    
    // Generate the PDF
    $pdf_path = generateAcceptanceLetterDOMPDF($sample_application, $pdo);
    
    if (file_exists($pdf_path)) {
        echo "<p style='color: green;'>✓ PDF generated successfully!</p>\n";
        echo "<p>File path: " . htmlspecialchars($pdf_path) . "</p>\n";
        echo "<p>File size: " . filesize($pdf_path) . " bytes</p>\n";
        echo "<p><a href='letters/" . basename($pdf_path) . "' target='_blank'>Download Test PDF</a></p>\n";
    } else {
        echo "<p style='color: red;'>✗ PDF file was not created at expected location.</p>\n";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error generating PDF: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p>Stack trace: <pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></p>\n";
}

echo "<h3>Test completed.</h3>\n";
?>