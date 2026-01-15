<?php
require_once __DIR__ . '/../config.php';

// Load the enhanced FPDF library
require_once __DIR__ . '/lib/fpdf/fpdf.php';

/**
 * Generate an acceptance letter in PDF format
 * 
 * @param array $application Application data including programme_id, intake_id
 * @param PDO $pdo Database connection
 * @return string Path to the generated letter
 */
function generateAcceptanceLetterPDF($application, $pdo) {
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
    
    // Create PDF
    $pdf = new FPDF();
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('Arial', 'B', 16);
    
    // College Header
    $pdf->Cell(0, 10, 'LUSAKA SOUTH COLLEGE', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, '123 University Road, Lusaka, Zambia', 0, 1, 'C');
    $pdf->Cell(0, 8, 'Email: admissions@lsuczm.com', 0, 1, 'C');
    $pdf->Ln(10);
    
    // Date
    $pdf->Cell(0, 8, 'Date: ' . date('F j, Y'), 0, 1, 'R');
    $pdf->Ln(10);
    
    // Recipient Address
    $pdf->Cell(0, 8, 'To:', 0, 1);
    $pdf->Cell(0, 8, $application['full_name'], 0, 1);
    $pdf->Cell(0, 8, $application['email'], 0, 1);
    if (!empty($application['phone'])) {
        $pdf->Cell(0, 8, 'Phone: ' . $application['phone'], 0, 1);
    }
    $pdf->Ln(10);
    
    // Subject
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 8, 'Subject: Admission Acceptance Letter', 0, 1);
    $pdf->Ln(5);
    
    // Salutation
    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 8, "Dear " . $application['full_name'] . ",");
    $pdf->Ln(5);
    
    // Body
    $pdf->MultiCell(0, 8, "Congratulations! We are pleased to inform you that your application for admission to the following programme has been accepted:");
    $pdf->Ln(5);
    
    // Programme Details
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 8, 'Programme:', 0);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $application['programme_name'], 0, 1);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 8, 'Intake:', 0);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $application['intake_name'], 0, 1);
    
    if (!empty($application['mode_of_learning'])) {
        $mode_text = $application['mode_of_learning'] === 'online' ? 'Online' : 'Physical';
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(40, 8, 'Mode of Study:', 0);
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 8, $mode_text, 0, 1);
    }
    
    $pdf->Ln(10);
    
    // Fee Structure Header
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 8, 'FEE STRUCTURE', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, str_repeat('=', 50), 0, 1);
    $pdf->Ln(5);
    
    // Fee Details
    if (!empty($grouped_fees['one_time'])) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'ONE-TIME FEES:', 0, 1);
        $pdf->SetFont('Arial', '', 12);
        
        foreach ($grouped_fees['one_time'] as $fee) {
            $pdf->Cell(80, 8, '- ' . $fee['fee_name'], 0);
            $pdf->Cell(0, 8, 'K' . number_format($fee['fee_amount'], 2), 0, 1);
        }
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(80, 8, 'Total One-Time Fees:', 0);
        $pdf->Cell(0, 8, 'K' . number_format($total_one_time, 2), 0, 1);
        $pdf->Ln(5);
    }
    
    if (!empty($grouped_fees['per_term'])) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'PER TERM FEES:', 0, 1);
        $pdf->SetFont('Arial', '', 12);
        
        foreach ($grouped_fees['per_term'] as $fee) {
            $pdf->Cell(80, 8, '- ' . $fee['fee_name'], 0);
            $pdf->Cell(0, 8, 'K' . number_format($fee['fee_amount'], 2), 0, 1);
        }
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(80, 8, 'Total Per Term Fees:', 0);
        $pdf->Cell(0, 8, 'K' . number_format($total_per_term, 2), 0, 1);
        $pdf->Ln(5);
    }
    
    if (!empty($grouped_fees['per_year'])) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'PER YEAR FEES:', 0, 1);
        $pdf->SetFont('Arial', '', 12);
        
        foreach ($grouped_fees['per_year'] as $fee) {
            $pdf->Cell(80, 8, '- ' . $fee['fee_name'], 0);
            $pdf->Cell(0, 8, 'K' . number_format($fee['fee_amount'], 2), 0, 1);
        }
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(80, 8, 'Total Per Year Fees:', 0);
        $pdf->Cell(0, 8, 'K' . number_format($total_per_year, 2), 0, 1);
        $pdf->Ln(5);
    }
    
    // Total Fees
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(80, 10, 'TOTAL FEES:', 0);
    $pdf->Cell(0, 10, 'K' . number_format($total_fees, 2), 0, 1);
    $pdf->Ln(10);
    
    // Payment Instructions
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'PAYMENT INSTRUCTIONS', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, str_repeat('=', 50), 0, 1);
    $pdf->Ln(5);
    
    $pdf->MultiCell(0, 8, "Please proceed with the registration process as outlined in the student portal. Payment can be made at the Finance Office or through our online payment portal.");
    $pdf->Ln(5);
    
    $pdf->MultiCell(0, 8, "For any queries regarding fees, please contact the Finance Office at finance@lsuczm.com.");
    $pdf->Ln(10);
    
    // Closing
    $pdf->MultiCell(0, 8, "We look forward to welcoming you to Lusaka South College.");
    $pdf->Ln(10);
    
    $pdf->MultiCell(0, 8, "Best regards,");
    $pdf->Ln(5);
    $pdf->MultiCell(0, 8, "Admissions Office");
    $pdf->MultiCell(0, 8, "Lusaka South College");
    
    // Save PDF
    $output_filename = 'acceptance_letter_' . $application['id'] . '.pdf';
    $output_path = __DIR__ . '/letters/' . $output_filename;
    
    // Ensure the letters directory exists
    if (!file_exists(__DIR__ . '/letters')) {
        mkdir(__DIR__ . '/letters', 0777, true);
    }
    
    $pdf->Output('F', $output_path);
    
    return $output_path;
}

// Example usage (for testing)
if (basename($_SERVER['SCRIPT_NAME']) == 'generate_acceptance_letter_pdf.php') {
    // This is for testing purposes only
    echo "This script is meant to be included in other files, not run directly.";
}
?>