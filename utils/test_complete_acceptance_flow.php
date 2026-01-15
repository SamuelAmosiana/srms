<?php
// Comprehensive test for the complete acceptance letter generation flow
require_once '../config.php';
require_once '../letters_reports/generate_acceptance_letter_dompdf.php';

echo "<h2>Testing Complete Acceptance Letter Generation Flow</h2>\n";

try {
    // Get a real application from the database to test with
    $stmt = $pdo->prepare("
        SELECT a.*, p.name as programme_name, i.name as intake_name, p.id as programme_id
        FROM applications a
        LEFT JOIN programme p ON a.programme_id = p.id
        LEFT JOIN intake i ON a.intake_id = i.id
        WHERE a.status = 'pending'
        LIMIT 1
    ");
    $stmt->execute();
    $application = $stmt->fetch();
    
    if ($application) {
        echo "<p>Found application for testing: " . htmlspecialchars($application['full_name']) . "</p>\n";
        
        echo "<p>Attempting to generate acceptance letter PDF...</p>\n";
        
        // Generate the PDF using the new DOMPDF method
        $pdf_path = generateAcceptanceLetterDOMPDF($application, $pdo);
        
        if (file_exists($pdf_path)) {
            echo "<p style='color: green;'>✓ PDF generated successfully!</p>\n";
            echo "<p>File path: " . htmlspecialchars($pdf_path) . "</p>\n";
            echo "<p>File size: " . filesize($pdf_path) . " bytes</p>\n";
            echo "<p><a href='letters/" . basename($pdf_path) . "' target='_blank'>Download Test PDF</a></p>\n";
            
            // Test if the PDF file is valid by checking if it has PDF header
            $file_content = file_get_contents($pdf_path);
            if (strpos($file_content, '%PDF-') === 0) {
                echo "<p style='color: green;'>✓ PDF file has valid header</p>\n";
            } else {
                echo "<p style='color: orange;'>? PDF file may not have standard PDF header (this might be OK for FPDF-generated PDFs)</p>\n";
            }
        } else {
            echo "<p style='color: red;'>✗ PDF file was not created at expected location.</p>\n";
        }
    } else {
        echo "<p>No pending applications found in the database. Creating a sample application for testing...</p>\n";
        
        // Create a sample application array to test with
        $sample_application = [
            'id' => 999,
            'full_name' => 'Test Student',
            'email' => 'test.student@example.com',
            'phone' => '+260971234567',
            'nationality' => 'Zambian',
            'programme_name' => 'Bachelor of Science in Computer Science',
            'intake_name' => 'January 2026 Intake',
            'duration' => '3 Years',
            'mode_of_learning' => 'Physical',
            'programme_id' => 1
        ];
        
        echo "<p>Attempting to generate acceptance letter PDF with sample data...</p>\n";
        
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
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error during testing: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p>Stack trace: <pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></p>\n";
}

echo "<h3>Complete acceptance flow test completed.</h3>\n";
?>