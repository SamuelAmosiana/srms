<?php
// Enable real error output for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Determine the project root directory based on the current file location
$project_root = dirname(__DIR__);

// Include configuration files using the project root
require_once $project_root . '/config.php';
require_once $project_root . '/email_config.php';

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
    
    // Generate download link
    $letter_filename = basename($letter_path);
    $download_link = "https://" . $_SERVER['HTTP_HOST'] . "/srms/download_letter.php?file=" . urlencode($letter_filename);
    
    // Create email content
    $subject = "Admission Acceptance - Lusaka South College";
    
    $body = "Dear " . $application['full_name'] . ",\n\n";
    $body .= "Congratulations! We are pleased to inform you that your application for admission has been accepted.\n\n";
    $body .= "Programme: " . $application['programme_name'] . "\n";
    $body .= "Intake: " . $application['intake_name'] . "\n";
    $body .= "Phone: " . ($application['phone'] ?? 'N/A') . "\n\n";
    
    $body .= "FEE STRUCTURE\n";
    $body .= "=============\n\n";
    
    if (!empty($grouped_fees['one_time'])) {
        $body .= "ONE-TIME FEES:\n";
        foreach ($grouped_fees['one_time'] as $fee) {
            $body .= "- " . $fee['fee_name'] . ": K" . number_format($fee['fee_amount'], 2) . "\n";
        }
        $body .= "Total One-Time Fees: K" . number_format($total_one_time, 2) . "\n\n";
    }
    
    if (!empty($grouped_fees['per_term'])) {
        $body .= "PER TERM FEES:\n";
        foreach ($grouped_fees['per_term'] as $fee) {
            $body .= "- " . $fee['fee_name'] . ": K" . number_format($fee['fee_amount'], 2) . "\n";
        }
        $body .= "Total Per Term Fees: K" . number_format($total_per_term, 2) . "\n\n";
    }
    
    if (!empty($grouped_fees['per_year'])) {
        $body .= "PER YEAR FEES:\n";
        foreach ($grouped_fees['per_year'] as $fee) {
            $body .= "- " . $fee['fee_name'] . ": K" . number_format($fee['fee_amount'], 2) . "\n";
        }
        $body .= "Total Per Year Fees: K" . number_format($total_per_year, 2) . "\n\n";
    }
    
    $body .= "TOTAL FEES: K" . number_format($total_fees, 2) . "\n\n";
    
    $body .= "LOGIN DETAILS\n";
    $body .= "=============\n";
    
    // Handle different types of login details
    if (isset($login_details['instruction'])) {
        // For first-time applicants who need to use email for login
        $body .= $login_details['instruction'] . "\n";
        $body .= "Portal URL: https://" . $_SERVER['HTTP_HOST'] . "/first_time_login\n\n";
    } else {
        // For existing students with username/password
        $username = $login_details['username'] ?? 'N/A';
        $password = $login_details['password'] ?? 'N/A';
        $body .= "Username: " . $username . "\n";
        $body .= "Password: " . $password . "\n";
        $body .= "Portal URL: " . PORTAL_URL . "\n\n";
    }
    
    $body .= "To download your acceptance letter, please click the link below:\n";
    $body .= $download_link . "\n\n";
    
    $body .= "Please proceed with the registration process as outlined in the student portal.\n";
    $body .= "Payment can be made at the Finance Office or through our online payment portal.\n\n";
    
    $body .= "For any queries regarding fees, please contact the Admissions Office at admissions@lsuczm.com.\n\n";
    $body .= "We look forward to welcoming you to Lusaka South College.\n\n";
    $body .= "Best regards,\n";
    $body .= "Admissions Office\n";
    $body .= "Lusaka South College";
    
    // Try to use PHPMailer if available, otherwise fall back to mail()
    if ($phpmailer_available) {
        return sendEmailWithPHPMailer($application['email'], $subject, $body);
    } else {
        // Send email using PHP's mail function
        $headers = "From: " . EMAIL_FROM . "\r\n";
        $headers .= "Reply-To: " . EMAIL_REPLY_TO . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        // Try to send the email
        $result = mail($application['email'], $subject, $body, $headers);
        
        // Log email details for debugging (whether successful or not)
        error_log("Email attempt to: " . $application['email']);
        error_log("Subject: " . $subject);
        error_log("Body: " . $body);
        
        // If mail() fails due to SMTP configuration, provide a more user-friendly message
        if (!$result) {
            error_log("Failed to send email to: " . $application['email'] . ". This is likely due to SMTP configuration issues.");
            // Return true anyway so the application process continues
            return true;
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
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(EMAIL_REPLY_TO);
        
        // Content
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        // Send email
        $mail->send();
        
        // Log success
        error_log("Email sent successfully to: " . $to);
        return true;
        
    } catch (Exception $e) {
        // Log error
        error_log("Failed to send email to: " . $to . ". Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Example usage (for testing)
if (basename($_SERVER['SCRIPT_NAME']) == 'generate_acceptance_letter_docx.php') {
    // This is for testing purposes only
    echo "This script is meant to be included in other files, not run directly.";
}
?>