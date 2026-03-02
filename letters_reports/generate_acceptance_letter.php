<?php
// Enable real error output for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include configuration files using the correct path
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../email_config.php';

// Load the FPDF library
require_once __DIR__ . '/../lib/fpdf/fpdf.php';

// Try to load PHPMailer if available
$phpmailer_available = false;
if (file_exists(__DIR__ . '/../lib/PHPMailer/PHPMailer.php')) {
    require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';
    require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
    $phpmailer_available = true;
}

/**
 * Generate an acceptance letter in PDF format
 * 
 * @param array $application Application data including programme_id, intake_id
 * @param PDO $pdo Database connection
 * @return string Path to the generated letter
 */
function generateAcceptanceLetter($application, $pdo) {
    // Get programme fees
    $sql = "SELECT pf.* FROM programme_fees pf WHERE pf.programme_id = ? AND pf.is_active = 1 ORDER BY pf.fee_type, pf.fee_name";
    $stmt = $pdo->prepare($sql);
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
        
    $pdf->MultiCell(0, 8, "Please proceed with the registration process as outlined in the student portal with 60% payment. You can download the student portal user manual on our website www.lsuczm.com under download section for guidance.");
    $pdf->Ln(5);
        
    $pdf->MultiCell(0, 8, "Payment can be made through the Bank physical or online transfer as follows:");
    $pdf->Ln(3);
        
    $pdf->MultiCell(0, 8, "Account Name: Lusaka South College");
    $pdf->MultiCell(0, 8, "Account Number: 5947236500193 (ZMW)");
    $pdf->MultiCell(0, 8, "Bank: ZANACO");
    $pdf->MultiCell(0, 8, "Branch Name: Acacia Park");
    $pdf->MultiCell(0, 8, "Branch Code: 086");
    $pdf->MultiCell(0, 8, "Sort Code: 010086");
    $pdf->MultiCell(0, 8, "Swift Code: ZNCOZMLU");
    $pdf->MultiCell(0, 8, "Or");
    $pdf->MultiCell(0, 8, "Mobile Money (Zambians Only)");
    $pdf->MultiCell(0, 8, "Dial: *767*1*111001301*Amount#");
    $pdf->Ln(5);
        
    $pdf->MultiCell(0, 8, "or through our online payment portal.");
    $pdf->Ln(10);
        
    
        
    // Student Login Details
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'STUDENT LOGIN DETAILS', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, str_repeat('=', 50), 0, 1);
    $pdf->Ln(5);
    
    $pdf->MultiCell(0, 8, "Your student portal login details will be provided separately with instructions on how to access your student account.");
    $pdf->Ln(10);
    
    // Contact Information
    $pdf->MultiCell(0, 8, "For any queries regarding your admission, please Contact/WhatsApp the Admissions Office +260 770359518 or email: admissions@lsuczm.com Or visit our main campus in Foxdale Lusaka at the Corner of Zambezi and Mutumbi Road.");
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

/**
 * Send acceptance letter email with download link
 * 
 * @param array $application Application data
 * @param string $letter_path Path to the generated letter
 * @param array $login_details Login details for the student portal
 * @param PDO $pdo Database connection
 * @return bool Whether email was sent successfully
 */
function sendAcceptanceLetterEmail($application, $letter_path, $login_details, $pdo) {
    global $phpmailer_available;
    // Get programme fees for email content
    $stmt = $pdo->prepare("
        SELECT pf.*
        FROM programme_fees pf 
        WHERE pf.programme_id = ? AND pf.is_active = 1
        ORDER BY pf.fee_type, pf.fee_name
    ");
    $stmt->execute([$application['programme_id']]);
    $programme_fees = $stmt->fetchAll();
    
    // Group fees by type for email content
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
    
    // Create email content
    $subject = "Admission Acceptance - Lusaka South College";
    
    $body = "Dear " . $application['full_name'] . "\n\n";
    $body .= "Congratulations! We are pleased to inform you that your application for admission at Lusaka South College has been accepted.\n\n";
    $body .= "Programme: " . $application['programme_name'] . "\n";
    $body .= "Intake: " . ($application['intake_name'] ? explode(' ', $application['intake_name'])[0] : 'N/A') . "\n";
    $body .= "Phone: " . ($application['phone'] ?? 'N/A') . "\n\n";
    
    $body .= "FEE STRUCTURE\n";
    $body .= "=============\n\n";
    
    $body .= "TOTAL FEES: K" . number_format($total_fees, 2) . "\n\n";
    
    $body .= "YOUR STUDENT LOGIN DETAILS\n";
    $body .= "==========================\n";
    
    // Handle different types of login details
    if (isset($login_details['instruction'])) {
        // For first-time applicants who need to use email for login
        $body .= $login_details['instruction'] . "\n";
        $body .= "Portal URL/Link : https://" . $_SERVER['HTTP_HOST'] . "/user_management/first_time_login.php\n\n";
    } else {
        // For existing students with username/password
        $username = $login_details['username'] ?? 'N/A';
        $password = $login_details['password'] ?? 'N/A';
        $body .= "Username: " . $username . "\n";
        $body .= "Password: " . $password . "\n";
        $body .= "Portal URL: " . PORTAL_URL . "\n\n";
    }
    
    $body .= "Please proceed with the registration process as outlined in the student portal with 60% payment. You can download the student portal user manual on our website www.lsuczm.com under download section for guidance.\n\n";
    
    // Add download link for the acceptance letter
    $download_url = "https://" . $_SERVER['HTTP_HOST'] . "/srms/letters_reports/download_letter.php?file=" . basename($letter_path);
    $body .= "DOWNLOAD ACCEPTANCE LETTER\n";
    $body .= "==========================\n";
    $body .= "You can download your official acceptance letter using the link below:\n";
    $body .= $download_url . "\n\n";
    
    $body .= "STUDENT FEES PAYMENT PROCESS\n";
    $body .= "==========================\n";
    $body .= "Payment can be made through the Bank physical or online transfer as follows:\n";
    $body .= "Account Name: Lusaka South College\n";
    $body .= "Account Number: 5947236500193 (ZMW)\n";
    $body .= "Bank: ZANACO\n";
    $body .= "Branch Name: Acacia Park\n";
    $body .= "Branch Code: 086\n";
    $body .= "Sort Code: 010086\n";
    $body .= "Swift Code: ZNCOZMLU\n";
    $body .= "Or\n";
    $body .= "Mobile Money (Zambians Only)\n";
    $body .= "Dial: *767*1*111001301*Amount#\n\n";
    $body .= "or through our online payment portal.\n\n";
    
    $body .= "For any queries regarding your admission, please Contact/WhatsApp the Admissions Office +260 770359518 or email: admissions@lsuczm.com Or visit our main campus in Foxdale Lusaka at the Corner of Zambezi and Mutumbi Road.\n\n";
    
    $body .= "We look forward to welcoming you to Lusaka South College.\n\n";
    $body .= "Best regards,\n";
    $body .= "Admissions Office\n";
    $body .= "Lusaka South College";
    
    // Try to use PHPMailer if available, otherwise fall back to mail()
    if ($phpmailer_available) {
        error_log("Attempting to send acceptance email via PHPMailer to: " . $application['email']);
        $result = sendEmailWithPHPMailer($application['email'], $subject, $body);
        
        if (!$result) {
            error_log("PHPMailer failed, falling back to mail() function for: " . $application['email']);
            // Fallback to mail() function
            $headers = "From: " . EMAIL_FROM . "\r\n";
            $headers .= "Reply-To: " . EMAIL_REPLY_TO . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            
            $result = mail($application['email'], $subject, $body, $headers);
            
            if ($result) {
                error_log("Email sent successfully using mail() function to: " . $application['email']);
            } else {
                error_log("Both PHPMailer and mail() function failed for: " . $application['email']);
            }
        }
        
        return $result;
    } else {
        // Send email using PHP's mail function
        $headers = "From: " . EMAIL_FROM . "\r\n";
        $headers .= "Reply-To: " . EMAIL_REPLY_TO . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        $result = mail($application['email'], $subject, $body, $headers);
        
        if ($result) {
            error_log("Email sent successfully using mail() function to: " . $application['email']);
        } else {
            error_log("mail() function failed for: " . $application['email'] . ". This may be due to server configuration.");
        }
        
        return $result;
    }
}

/**
 * Send email using PHPMailer with Namecheap SMTP settings
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body
 * @return bool Whether email was sent successfully
 */
function sendEmailWithPHPMailer($to, $subject, $body) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; // Use STARTTLS
        $mail->Port = SMTP_PORT;
        
        // Enable verbose debug output
        $mail->SMTPDebug = 0; // Set to 2 for detailed debugging
        
        // Recipients
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(EMAIL_REPLY_TO);
        
        // Content
        $mail->isHTML(true); // Changed to HTML for better formatting
        $mail->Subject = $subject;
        $mail->Body    = nl2br($body); // Convert newlines to HTML line breaks
        $mail->AltBody = $body; // Plain text version
        
        // Send email
        $mail->send();
        
        // Log success
        error_log("Email sent successfully to: " . $to);
        return true;
        
    } catch (Exception $e) {
        // Log error with more details
        error_log("Failed to send email to: " . $to . ". Error: " . $e->getMessage());
        error_log("SMTP Error Info: " . $mail->ErrorInfo ?? 'N/A');
        return false;
    }
}

// Example usage (for testing)
if (basename($_SERVER['SCRIPT_NAME']) == 'generate_acceptance_letter_docx.php') {
    // This is for testing purposes only
    echo "This script is meant to be included in other files, not run directly.";
}
?>