<?php
require_once 'lib/fpdf/fpdf.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Simple test
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, 'Test PDF Generation');
    $pdf->Output('S'); // Return as string instead of outputting
    echo "PDF generated successfully";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>