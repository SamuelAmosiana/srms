<?php
require_once __DIR__ . '/config.php';

/**
 * Generate an acceptance letter in PDF format using HTML to PDF conversion
 * This function uses a direct approach without requiring external libraries
 * 
 * @param array $application Application data including programme_id, intake_id
 * @param PDO $pdo Database connection
 * @return string Path to the generated letter
 */
function generateAcceptanceLetterDOMPDF($application, $pdo) {
    // Get programme fees
    $stmt = $pdo->prepare("
        SELECT pf.*
        FROM programme_fees pf 
        WHERE pf.programme_id = ? AND pf.is_active = 1
        ORDER BY pf.fee_type, pf.fee_name
    ");
    $stmt->execute([$application['programme_id']]);
    $programme_fees = $stmt->fetchAll();
    
    // Group fees by type for better presentation
    $grouped_fees = [
        'one_time' => [],
        'per_term' => [],
        'per_year' => []
    ];
    
    foreach ($programme_fees as $fee) {
        $grouped_fees[$fee['fee_type']][] = $fee;
    }
    
    // Calculate totals
    $total_one_time = 0;
    $total_per_term = 0;
    $total_per_year = 0;
    
    foreach ($grouped_fees['one_time'] as $fee) {
        $total_one_time += $fee['fee_amount'];
    }
    
    foreach ($grouped_fees['per_term'] as $fee) {
        $total_per_term += $fee['fee_amount'];
    }
    
    foreach ($grouped_fees['per_year'] as $fee) {
        $total_per_year += $fee['fee_amount'];
    }
    
    // Calculate total fees
    $total_fees = $total_one_time + $total_per_term + $total_per_year;
    
    // Prepare template variables
    $logo_path = __DIR__ . '/assets/images/lsc-logo.png';
    if (!file_exists($logo_path)) {
        // Use a relative path for the template
        $logo_path = './assets/images/lsc-logo.png'; // This will be replaced with actual path in the template
    }
    
    $signature_path = __DIR__ . '/assets/images/signature.png';
    if (!file_exists($signature_path)) {
        // Use a relative path for the template
        $signature_path = './assets/images/signature.png'; // This will be replaced with actual path in the template
    }
    
    // Start output buffering to capture the HTML template
    ob_start();
    
    // Define variables that will be used in the template
    $application = $application;
    $grouped_fees = $grouped_fees;
    $total_fees = $total_fees;
    $logo_path = $logo_path;
    $signature_path = $signature_path;
    
    // Include the template with the variables
    include __DIR__ . '/templates/acceptance_letter_template.php';
    
    // Get the HTML content
    $html = ob_get_contents();
    ob_end_clean();
    
    // Generate output filename
    $output_filename = 'acceptance_letter_' . $application['id'] . '.pdf';
    $output_path = __DIR__ . '/letters/' . $output_filename;
    
    // Ensure the letters directory exists
    if (!file_exists(__DIR__ . '/letters')) {
        mkdir(__DIR__ . '/letters', 0777, true);
    }
    
    // For this implementation, we'll save the HTML to a temporary file
    // and then use a command line tool to convert it to PDF if available
    $temp_html_path = __DIR__ . '/temp/temp_letter_' . $application['id'] . '.html';
    
    // Create temp directory if it doesn't exist
    if (!file_exists(__DIR__ . '/temp')) {
        mkdir(__DIR__ . '/temp', 0777, true);
    }
    
    // Save HTML to temporary file
    file_put_contents($temp_html_path, $html);
    
    // Try to convert HTML to PDF using wkhtmltopdf if available
    $wkhtmltopdf_path = 'wkhtmltopdf'; // Common installation path
    $command = "$wkhtmltopdf_path \"$temp_html_path\" \"$output_path\" 2>&1";
    
    // Try common installation paths on Windows
    $possible_paths = [
        'C:/Program Files/wkhtmltopdf/bin/wkhtmltopdf.exe',
        'C:/Program Files (x86)/wkhtmltopdf/bin/wkhtmltopdf.exe',
        'wkhtmltopdf.exe'
    ];
    
    $converted = false;
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $command = "\"$path\" \"$temp_html_path\" \"$output_path\" 2>&1";
            $output = [];
            $return_var = 0;
            exec($command, $output, $return_var);
            if ($return_var === 0) {
                $converted = true;
                break;
            }
        }
    }
    
    // If wkhtmltopdf is not available, we'll create a basic PDF using FPDF
    if (!$converted) {
        // Use FPDF as fallback to create a PDF from the HTML content
        if (!class_exists('FPDF')) {
            require_once __DIR__ . '/lib/fpdf/fpdf.php';
        }
        
        // Create a basic PDF from the HTML content
        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        
        // Since FPDF can't render complex HTML, we'll create a simplified version
        // This is just a fallback implementation
        $pdf->Cell(0, 10, 'LUSAKA SOUTH COLLEGE', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, 'LETTER OF CONDITIONAL ACCEPTANCE', 0, 1, 'C');
        $pdf->Ln(10);
        
        $pdf->Cell(0, 10, 'To: ' . $application['full_name'], 0, 1);
        $pdf->Cell(0, 10, 'Date: ' . date('d/m/Y'), 0, 1);
        $pdf->Ln(5);
        
        $pdf->MultiCell(0, 10, 'Dear ' . $application['full_name'] . ',');
        $pdf->Ln(5);
        
        $pdf->MultiCell(0, 10, 'Congratulations! We are pleased to inform you that your application for admission to the following programme has been accepted:');
        $pdf->Ln(5);
        
        $pdf->Cell(0, 10, 'Programme: ' . $application['programme_name'], 0, 1);
        $pdf->Cell(0, 10, 'Intake: ' . $application['intake_name'], 0, 1);
        $pdf->Ln(5);
        
        $pdf->Cell(0, 10, 'FEE STRUCTURE', 0, 1);
        $pdf->Cell(0, 10, 'Total Fees: K' . number_format($total_fees, 2), 0, 1);
        $pdf->Ln(5);
        
        $pdf->Output('F', $output_path);
    }
    
    // Clean up temporary HTML file
    if (file_exists($temp_html_path)) {
        unlink($temp_html_path);
    }
    
    return $output_path;
}

// Example usage (for testing)
if (basename($_SERVER['SCRIPT_NAME']) == 'generate_acceptance_letter_dompdf.php') {
    // This is for testing purposes only
    echo "This script is meant to be included in other files, not run directly.";
}