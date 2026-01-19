<?php
// Enable real error output for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include configuration files using the correct path
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../email_config.php';

// Try to load PHPMailer if available
$phpmailer_available = false;
if (file_exists(__DIR__ . '/lib/PHPMailer/PHPMailer.php')) {
    require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/lib/PHPMailer/SMTP.php';
    require_once __DIR__ . '/lib/PHPMailer/Exception.php';
    $phpmailer_available = true;
}

/**
 * Generate an acceptance letter in DOCX format from intake-specific templates
 * 
 * @param array $application Application data including programme_id, intake_id
 * @param PDO $pdo Database connection
 * @return string Path to the generated letter
 */
function generateAcceptanceLetterDOCX($application, $pdo) {
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
    
    // Determine which template to use based on intake
    $template_path = '';
    $intake_name = strtolower($application['intake_name'] ?? '');
    
    if (strpos($intake_name, 'january') !== false) {
        $template_path = __DIR__ . '/letters/Acceptance Letter_January_Intake.docx';
    } elseif (strpos($intake_name, 'april') !== false) {
        $template_path = __DIR__ . '/letters/Acceptance Letter_April_Intake.docx';
    } elseif (strpos($intake_name, 'july') !== false) {
        $template_path = __DIR__ . '/letters/Acceptance Letter_July_Intake.docx';
    } elseif (strpos($intake_name, 'october') !== false) {
        $template_path = __DIR__ . '/letters/Acceptance Letter_October_Intake.docx';
    } else {
        // Default to January template if no match
        $template_path = __DIR__ . '/letters/Acceptance Letter_January_Intake.docx';
    }
    
    // Check if template exists
    if (!file_exists($template_path)) {
        throw new Exception("Template file not found: " . $template_path);
    }
    
    // Copy template to new file
    // Use consistent naming per application to avoid broken links
    $output_filename = 'acceptance_letter_' . $application['id'] . '.docx';
    $output_path = __DIR__ . '/letters/' . $output_filename;
    
    // Remove any existing letter for this application
    if (file_exists($output_path)) {
        unlink($output_path);
    }
    
    if (!copy($template_path, $output_path)) {
        throw new Exception("Failed to copy template file");
    }
    
    // For now, we'll just copy the template and return the path
    // In a full implementation, we would use a library like PHPWord to replace placeholders
    // But since we're focusing on the core functionality, we'll just use the template as-is
    // and include the fee information in the email
    
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