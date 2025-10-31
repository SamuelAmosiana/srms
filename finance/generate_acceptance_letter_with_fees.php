<?php
require_once __DIR__ . '/../config.php';

/**
 * Generate an acceptance letter with programme fees information
 * 
 * @param array $application Application data including programme_id
 * @param PDO $pdo Database connection
 * @return string Path to the generated letter
 */
function generateAcceptanceLetterWithFees($application, $pdo) {
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
    
    // Generate letter content
    $letter_content = "LSC SRMS
123 University Road, City, Country
Email: admissions@lsc.edu

Date: " . date('F d, Y') . "

To:
{$application['full_name']}
{$application['email']}

Subject: Admission Acceptance Letter with Fee Structure

Dear {$application['full_name']},

Congratulations! We are pleased to inform you that your application for admission to the following programme has been accepted:

Programme: {$application['programme_name']}
Intake: {$application['intake_name']}

FEE STRUCTURE
=============

";
    
    // Add fees to letter content
    $total_one_time = 0;
    $total_per_term = 0;
    $total_per_year = 0;
    
    if (!empty($grouped_fees['one_time'])) {
        $letter_content .= "ONE-TIME FEES:\n";
        foreach ($grouped_fees['one_time'] as $fee) {
            $letter_content .= sprintf("- %s: K%s\n", $fee['fee_name'], number_format($fee['fee_amount'], 2));
            $total_one_time += $fee['fee_amount'];
        }
        $letter_content .= sprintf("Total One-Time Fees: K%s\n\n", number_format($total_one_time, 2));
    }
    
    if (!empty($grouped_fees['per_term'])) {
        $letter_content .= "PER TERM FEES:\n";
        foreach ($grouped_fees['per_term'] as $fee) {
            $letter_content .= sprintf("- %s: K%s\n", $fee['fee_name'], number_format($fee['fee_amount'], 2));
            $total_per_term += $fee['fee_amount'];
        }
        $letter_content .= sprintf("Total Per Term Fees: K%s\n\n", number_format($total_per_term, 2));
    }
    
    if (!empty($grouped_fees['per_year'])) {
        $letter_content .= "PER YEAR FEES:\n";
        foreach ($grouped_fees['per_year'] as $fee) {
            $letter_content .= sprintf("- %s: K%s\n", $fee['fee_name'], number_format($fee['fee_amount'], 2));
            $total_per_year += $fee['fee_amount'];
        }
        $letter_content .= sprintf("Total Per Year Fees: K%s\n\n", number_format($total_per_year, 2));
    }
    
    // Calculate total fees
    $total_fees = $total_one_time + $total_per_term + $total_per_year;
    $letter_content .= sprintf("TOTAL FEES: K%s\n\n", number_format($total_fees, 2));
    
    // Add payment instructions
    $letter_content .= "PAYMENT INSTRUCTIONS
==================

Please proceed with the registration process as outlined in the student portal. 
Payment can be made at the Finance Office or through our online payment portal.

For any queries regarding fees, please contact the Finance Office at finance@lsc.edu.

We look forward to welcoming you to LSC.

Best regards,
Admissions Office
LSC SRMS";
    
    // Ensure letters directory exists
    if (!is_dir('../letters')) {
        mkdir('../letters', 0755, true);
    }
    
    // Save as text file
    $letter_path = '../letters/acceptance_with_fees_' . $application['id'] . '.txt';
    file_put_contents($letter_path, $letter_content);
    return $letter_path;
}

// Example usage (for testing)
if (basename($_SERVER['SCRIPT_NAME']) == 'generate_acceptance_letter_with_fees.php') {
    // This is for testing purposes only
    echo "This script is meant to be included in other files, not run directly.";
}
?>