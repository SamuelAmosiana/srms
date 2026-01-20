<?php
require_once __DIR__ . '/../config.php';

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
    
    // Generate the HTML content directly instead of using the template to avoid output issues
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Letter of Conditional Acceptance</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 40px;
            background-color: #fff;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo {
            width: 100px;
            height: 100px;
            margin-bottom: 10px;
        }
        
        .ref-number {
            text-align: right;
            font-weight: bold;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .date {
            text-align: right;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .address {
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .subject {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 20px;
            font-size: 16px;
        }
        
        .program-details {
            margin: 20px 0;
            padding: 15px;
            background-color: #f5f5f5;
            border-left: 4px solid #2E8B57;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 14px;
        }
        
        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        table th {
            background-color: #f2f2f2;
        }
        
        .fees-table {
            margin: 30px 0;
        }
        
        .total-row {
            font-weight: bold;
            background-color: #e8f5e8;
        }
        
        .terms-section {
            margin-top: 50px;
            page-break-before: always;
        }
        
        .terms-title {
            text-decoration: underline;
            font-weight: bold;
            margin-bottom: 20px;
            font-size: 16px;
        }
        
        .signature {
            margin-top: 100px;
        }
        
        .signature-img {
            width: 150px;
            height: 60px;
        }
        
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 20px;
        }
        
        @media print {
            body {
                font-size: 12pt;
            }
            
            .terms-section {
                page-break-before: always;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="./assets/images/lsc-logo.png" class="logo" alt="LSUC Logo">
        <h2>LUSAKA SOUTH COLLEGE</h2>
        <p>123 University Road, Lusaka, Zambia</p>
    </div>    
    
    <div class="ref-number">
        REF: LSUC/ENROLL/' . str_pad($application['id'], 3, '0', STR_PAD_LEFT) . '
    </div>
    
    <div class="date">
        Date: ' . date('d/m/Y') . '
    </div>    
    
    <div class="address">
        <strong>' . htmlspecialchars($application['full_name']) . '</strong><br>
        ' . (!empty($application['email']) ? htmlspecialchars($application['email']) . '<br>' : '') . '
        ' . (!empty($application['phone']) ? htmlspecialchars($application['phone']) . '<br>' : '') . '
        ' . (!empty($application['nationality']) ? htmlspecialchars($application['nationality']) : '') . '
    </div>    
    
    <p>Dear ' . htmlspecialchars($application['full_name']) . ',</p>
    
    <div class="subject">
        RE: LETTER OF CONDITIONAL ACCEPTANCE OF ENROLMENT ' . (date('Y') + 1) . ' INTAKE
    </div>    
    
    <p>
        Reference is made to your application for enrolment onto the January Intake ' . (date('Y') + 1) . ' at 
        Lusaka South College (LSC). The Council of LSC considered your application and 
        I am pleased to inform you that you have been accepted to pursue the following programme:
    </p>    
    
    <div class="program-details">
        <p><strong>Programme:</strong> ' . htmlspecialchars($application['programme_name']) . '</p>
        ' . (!empty($application['duration']) ? '<p><strong>Duration:</strong> ' . htmlspecialchars($application['duration']) . '</p>' : '') . '
        ' . (!empty($application['mode_of_learning']) ? '<p><strong>Mode of Study:</strong> ' . ($application['mode_of_learning'] === 'online' ? 'Online' : 'Physical') . '</p>' : '') . '
        <p><strong>Intake:</strong> ' . htmlspecialchars($application['intake_name']) . '</p>
    </div>    
    
    <h4>Programme Fees</h4>
    <table class="fees-table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Amount (K)</th>
            </tr>
        </thead>
        <tbody>
            ' . (!empty($grouped_fees['one_time']) ? 
                implode('', array_map(function($fee) {
                    return '<tr><td>' . htmlspecialchars($fee['fee_name']) . '</td><td>' . number_format($fee['fee_amount'], 2) . '</td></tr>';
                }, $grouped_fees['one_time'])) : '') . '
            
            ' . (!empty($grouped_fees['per_term']) ? 
                implode('', array_map(function($fee) {
                    return '<tr><td>' . htmlspecialchars($fee['fee_name']) . '</td><td>' . number_format($fee['fee_amount'], 2) . '</td></tr>';
                }, $grouped_fees['per_term'])) : '') . '
            
            ' . (!empty($grouped_fees['per_year']) ? 
                implode('', array_map(function($fee) {
                    return '<tr><td>' . htmlspecialchars($fee['fee_name']) . '</td><td>' . number_format($fee['fee_amount'], 2) . '</td></tr>';
                }, $grouped_fees['per_year'])) : '') . '
            
            <tr class="total-row">
                <td><strong>Total Fees</strong></td>
                <td><strong>' . number_format($total_fees, 2) . '</strong></td>
            </tr>
        </tbody>
    </table>
    
    <h4>Other Fees</h4>
    <p><em>(Students have an option to purchase on their own)</em></p>
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Amount (K)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Accommodation Per Term (Option)</td>
                <td>3,900.00</td>
            </tr>
        </tbody>
    </table>    
    
    <p>
        <em>
            Note that Identity Cards and Library fees are paid once per year.<br>
            Students\' normal registration shall commence on <strong>5th January ' . (date('Y') + 1) . '</strong> to 
            <strong>31st January ' . (date('Y') + 1) . '</strong> and <strong>late Registration from 1st to 27th February ' . (date('Y') + 1) . '</strong> 
            and will attract a <strong>penalty fee of K500.00</strong>. Classes will commence on 
            <strong>26th January ' . (date('Y') + 1) . '</strong>. Please refer to the academic calendar attached for details. 
            We congratulate you on your conditional acceptance at Lusaka South College. Find attached 
            the Terms and Conditions for your necessary action and seek clarification should the need arise.
        </em>
    </p>
    
    <h4>Bank Details are as follows:</h4>
    <p>
        <strong>Account Name:</strong> LUSAKA SOUTH COLLEGE<br>
        <strong>Account Number:</strong> 5947236500193 (ZMW)<br>
        <strong>Bank:</strong> ZANACO<br>
        <strong>Branch Name:</strong> ACACIA PARK BRANCH<br>
        <strong>Branch Code:</strong> 086<br>
        <strong>Sort Code:</strong> 010086<br>
        <strong>Swift Code:</strong> ZNCOZMLU
    </p>    
    
    <p>Your conditional acceptance is contingent upon the following:</p>
    <ol>
        <li>Receipt by the College of 100% of the total fees plus all other fees as indicated in the registration terms and conditions. <strong>Or</strong> Minimum of 60% Payment.</li>
        <li>Payment of 100% Exemptions Fees</li>
        <li>Attached Proof of National Registration Card or Passport</li>
    </ol>    
    
    <p>
        We extend our best wishes and look forward to having you study with us. Note that registration 
        is in progress and you are advised to register and join the classes currently in session. 
        Attached is the student registration form, academic calendar and catalog of programmes for 
        the courses you will undertake in the programme.
    </p>    
    
    <div class="signature">
        <p>Yours Sincerely</p>
        <br><br><br>
        <img src="./assets/images/signature.png" class="signature-img" alt="Signature">
        <p>
            <strong>Dr Nelly Kunda</strong><br>
            Deputy Registrar - Academic<br>
            <strong>Lusaka South College</strong>
        </p>
    </div>    
    
    <div class="terms-section">
        <div class="terms-title">Terms and Conditions</div>
        <ol>
            <li>All New and Continuing students MUST be registered for the term by completing the semester registration form, prior to the end of week 2.</li>
            <li>Fees Published are fixed per term</li>
            <li>The Annual tuition fees will be split and payable in three terms of a year.</li>
            <li>All new and continuing students must pay a user fee every term upon registration. Students who withdraw from the College may receive a refund of the user fees if the withdrawal is because of failure on the part of the College to provide the student\'s chosen programme of study. In all other cases, the user fee is non-refundable.</li>
            <li>All New and continuing students must pay 100% semester tuition fees before commencement of the semester or according to the standard College payment plan.</li>
            <li>However, in the event that a student is unable to settle the full cost of fees at commencement, they should pay a minimum registration and user fees and then enter into a payment plan. The student will then be required to pay a minimum of 60% of the semester\'s tuition fees by the 2nd Week after commencement of the semester.</li>
            <li>Students who choose a payment plan must pay 60% by the 2nd week, 80% by the 8th week and 100% by the 12th week.</li>
            <li>If a student fails to pay on agreed published payment dates (refer to 8 above) they will be subject to late payment fees or in some cases, will not be allowed to attend classes nor sit for any examinations, nor receive any services from the College.</li>
            <li>In the event of any withdrawal from the College or change of programme, there will be no refund on Tuition, and/or Exemption fee unless; a) The College is not able to run the programme. b) visa refusal c) withdrawal prior to start of study up to week 4. All other refunds are subject to the withdrawal policy.</li>
            <li>Programmes will only run if justified by demand.</li>
            <li>LSC Management will be happy to offer advice relating to College application, examination entries, etc., however it is the student\'s own responsibility to ensure that all applications, registration of entries, of whatever nature, are in order and sent off by the appropriate closing date.</li>
        </ol>
    </div>
    
    <div class="footer">
        <p>This is a computer-generated document and is valid without signature.</p>
        <p>Lusaka South College &copy; ' . date('Y') . ' - All Rights Reserved</p>
    </div>
</body>
</html>';

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
            require_once __DIR__ . '/../lib/fpdf/fpdf.php';
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