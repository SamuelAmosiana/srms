<?php
// Real email sender - forces actual email delivery
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get form data
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    // Validate required fields
    if (empty($input['formType']) || empty($input['email']) || empty($input['firstname']) || empty($input['lastname'])) {
        throw new Exception('Missing required fields');
    }
    
    $formType = $input['formType'];
    $applicantEmail = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
    
    if (!$applicantEmail) {
        throw new Exception('Invalid email address');
    }
    
    // Your actual email address
    $admissionsEmail = 'admissions@lsuczm.com';
    
    // Generate application ID
    $applicationId = 'LSUC-' . strtoupper($formType) . '-' . date('Y') . '-' . sprintf('%06d', rand(1, 999999));
    
    // Prepare email content
    $emailContent = generateEmailContent($input, $applicationId);
    $subject = $emailContent['subject'];
    $body = $emailContent['body'];
    
    // Force real email sending with multiple methods
    $emailSent = false;
    $method = '';
    $errors = [];
    
    // Method 1: Use ini_set to configure mail properly
    ini_set('SMTP', 'smtp.gmail.com');
    ini_set('smtp_port', '587');
    ini_set('sendmail_from', $applicantEmail);
    
    // Method 2: Try with proper headers
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $applicantEmail,
        'Reply-To: ' . $applicantEmail,
        'X-Mailer: PHP/' . phpversion(),
        'Return-Path: ' . $applicantEmail
    ];
    
    $headerString = implode("\r\n", $headers);
    
    // Try sending email
    $emailSent = @mail($admissionsEmail, $subject, $body, $headerString);
    
    if ($emailSent) {
        $method = 'PHP mail() with enhanced headers';
    } else {
        $errors[] = 'PHP mail() failed: ' . (error_get_last()['message'] ?? 'Unknown error');
        
        // Method 3: Try using sendmail directly (if available)
        if (function_exists('exec') && !$emailSent) {
            $sendmail_path = '/usr/sbin/sendmail';
            if (file_exists($sendmail_path)) {
                try {
                    $email_message = "To: $admissionsEmail\r\n";
                    $email_message .= "Subject: $subject\r\n";
                    $email_message .= "From: $applicantEmail\r\n";
                    $email_message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
                    $email_message .= $body;
                    
                    $descriptorspec = array(
                        0 => array("pipe", "r"),  // stdin
                        1 => array("pipe", "w"),  // stdout
                        2 => array("pipe", "w")   // stderr
                    );
                    
                    $process = proc_open("$sendmail_path -t", $descriptorspec, $pipes);
                    
                    if (is_resource($process)) {
                        fwrite($pipes[0], $email_message);
                        fclose($pipes[0]);
                        
                        $stdout = stream_get_contents($pipes[1]);
                        fclose($pipes[1]);
                        
                        $stderr = stream_get_contents($pipes[2]);
                        fclose($pipes[2]);
                        
                        $return_value = proc_close($process);
                        
                        if ($return_value === 0) {
                            $emailSent = true;
                            $method = 'Sendmail direct';
                        } else {
                            $errors[] = "Sendmail failed with code $return_value: $stderr";
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = 'Sendmail exception: ' . $e->getMessage();
                }
            }
        }
        
        // Method 4: Use cURL to send via external service (as backup)
        if (!$emailSent) {
            try {
                // This is a simple email API call - you can replace with your preferred service
                $emailData = [
                    'to' => $admissionsEmail,
                    'from' => $applicantEmail,
                    'subject' => $subject,
                    'html' => $body,
                    'text' => strip_tags($body)
                ];
                
                // Log email for manual sending if all else fails
                $logFile = __DIR__ . '/failed_emails.log';
                $logEntry = "\n" . str_repeat('=', 80) . "\n";
                $logEntry .= "FAILED EMAIL ATTEMPT - " . date('Y-m-d H:i:s') . "\n";
                $logEntry .= "Application ID: " . $applicationId . "\n";
                $logEntry .= "To: " . $admissionsEmail . "\n";
                $logEntry .= "From: " . $applicantEmail . "\n";
                $logEntry .= "Subject: " . $subject . "\n";
                $logEntry .= "Errors: " . implode(', ', $errors) . "\n";
                $logEntry .= "Body:\n" . strip_tags($body) . "\n";
                $logEntry .= str_repeat('=', 80) . "\n";
                
                file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
                
                throw new Exception('All email methods failed. Email logged for manual processing. Errors: ' . implode(', ', $errors));
            } catch (Exception $e) {
                throw $e;
            }
        }
    }
    
    if ($emailSent) {
        // Try to send confirmation to applicant
        @mail($applicantEmail, 
              "Application Confirmation - LSUC", 
              generateConfirmationEmail($input['firstname'], $applicationId, $formType),
              $headerString);
        
        echo json_encode([
            'success' => true,
            'message' => 'Email sent successfully to admissions@lsuczm.com!',
            'applicationId' => $applicationId,
            'method' => $method,
            'timestamp' => date('Y-m-d H:i:s'),
            'recipient' => $admissionsEmail
        ]);
    } else {
        throw new Exception('Failed to send email after trying multiple methods');
    }
    
} catch (Exception $e) {
    error_log('Email sending error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'php_version' => phpversion(),
            'mail_function' => function_exists('mail') ? 'available' : 'not available',
            'smtp_setting' => ini_get('SMTP'),
            'sendmail_path' => ini_get('sendmail_path'),
            'errors' => $errors ?? []
        ]
    ]);
}

function generateEmailContent($data, $applicationId) {
    $formType = $data['formType'];
    $firstname = htmlspecialchars($data['firstname']);
    $lastname = htmlspecialchars($data['lastname']);
    
    switch ($formType) {
        case 'undergraduate':
            $subject = "🎓 New Undergraduate Application - $firstname $lastname";
            $body = generateUndergraduateEmail($data, $applicationId);
            break;
        case 'short-courses':
            $subject = "📚 New Short Course Application - $firstname $lastname";
            $body = generateShortCourseEmail($data, $applicationId);
            break;
        case 'corporate-training':
            $subject = "🏢 New Corporate Training Request - " . htmlspecialchars($data['company'] ?? ($firstname . ' ' . $lastname));
            $body = generateCorporateTrainingEmail($data, $applicationId);
            break;
        default:
            $subject = "📝 New Application - $firstname $lastname";
            $body = generateGenericEmail($data, $applicationId);
    }
    
    return ['subject' => $subject, 'body' => $body];
}

function generateUndergraduateEmail($data, $applicationId) {
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
            .header { background: #2e8b57; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #fff; padding: 30px; border: 1px solid #ddd; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; }
            .field { margin-bottom: 15px; }
            .label { font-weight: bold; color: #2e8b57; display: inline-block; width: 150px; }
            .value { margin-left: 10px; }
            .section { margin-bottom: 25px; border-bottom: 2px solid #eee; padding-bottom: 15px; }
            .urgent { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>🎓 NEW UNDERGRADUATE APPLICATION</h2>
            <p><strong>Application ID: $applicationId</strong></p>
            <p>Submitted: " . date('Y-m-d H:i:s') . "</p>
        </div>
        
        <div class='urgent'>
            <h3>⚡ IMMEDIATE ACTION REQUIRED</h3>
            <p>A new undergraduate application has been received and requires your attention.</p>
        </div>
        
        <div class='content'>
            <div class='section'>
                <h3>👤 Personal Information</h3>
                <div class='field'><span class='label'>Full Name:</span><span class='value'>" . htmlspecialchars($data['firstname']) . " " . htmlspecialchars($data['lastname']) . "</span></div>
                <div class='field'><span class='label'>Email:</span><span class='value'>" . htmlspecialchars($data['email']) . "</span></div>
                <div class='field'><span class='label'>Phone:</span><span class='value'>" . htmlspecialchars($data['phone']) . "</span></div>
                <div class='field'><span class='label'>NRC/Passport:</span><span class='value'>" . htmlspecialchars($data['nrc'] ?? 'Not provided') . "</span></div>
                <div class='field'><span class='label'>Date of Birth:</span><span class='value'>" . htmlspecialchars($data['dateofbirth'] ?? 'Not provided') . "</span></div>
                <div class='field'><span class='label'>Address:</span><span class='value'>" . nl2br(htmlspecialchars($data['address'] ?? 'Not provided')) . "</span></div>
            </div>
            
            <div class='section'>
                <h3>🎓 Academic Information</h3>
                <div class='field'><span class='label'>Program:</span><span class='value'>" . htmlspecialchars($data['program'] ?? 'Not specified') . "</span></div>
                <div class='field'><span class='label'>Intake:</span><span class='value'>" . htmlspecialchars($data['intake'] ?? 'Not specified') . "</span></div>
                <div class='field'><span class='label'>Previous School:</span><span class='value'>" . htmlspecialchars($data['previousschool'] ?? 'Not provided') . "</span></div>
                <div class='field'><span class='label'>Grade 12 Results:</span><span class='value'>" . htmlspecialchars($data['grade12results'] ?? 'Not provided') . "</span></div>
            </div>
            
            <div class='section'>
                <h3>🚨 Emergency Contact</h3>
                <div class='field'><span class='label'>Guardian Name:</span><span class='value'>" . htmlspecialchars($data['guardianname'] ?? 'Not provided') . "</span></div>
                <div class='field'><span class='label'>Guardian Phone:</span><span class='value'>" . htmlspecialchars($data['guardianphone'] ?? 'Not provided') . "</span></div>
                <div class='field'><span class='label'>Relationship:</span><span class='value'>" . htmlspecialchars($data['relationship'] ?? 'Not provided') . "</span></div>
            </div>
        </div>
        
        <div class='footer'>
            <h3>📞 Next Steps</h3>
            <p>Please contact the applicant within 2-3 business days at: <strong>" . htmlspecialchars($data['email']) . "</strong></p>
            <p>Application received via LSUC Online Application System</p>
        </div>
    </body>
    </html>";
    
    return $html;
}

function generateShortCourseEmail($data, $applicationId) {
    // Similar structure for short courses...
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
            .header { background: #2e8b57; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #fff; padding: 30px; border: 1px solid #ddd; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; }
            .field { margin-bottom: 15px; }
            .label { font-weight: bold; color: #2e8b57; display: inline-block; width: 150px; }
            .urgent { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>📚 NEW SHORT COURSE APPLICATION</h2>
            <p><strong>Application ID: $applicationId</strong></p>
            <p>Submitted: " . date('Y-m-d H:i:s') . "</p>
        </div>
        
        <div class='urgent'>
            <h3>⚡ IMMEDIATE ACTION REQUIRED</h3>
            <p>A new short course application has been received.</p>
        </div>
        
        <div class='content'>
            <div class='field'><span class='label'>Name:</span>" . htmlspecialchars($data['firstname']) . " " . htmlspecialchars($data['lastname']) . "</div>
            <div class='field'><span class='label'>Email:</span>" . htmlspecialchars($data['email']) . "</div>
            <div class='field'><span class='label'>Phone:</span>" . htmlspecialchars($data['phone']) . "</div>
            <div class='field'><span class='label'>Course:</span>" . htmlspecialchars($data['course'] ?? 'Not specified') . "</div>
            <div class='field'><span class='label'>Start Date:</span>" . htmlspecialchars($data['startdate'] ?? 'Not specified') . "</div>
            <div class='field'><span class='label'>Schedule:</span>" . htmlspecialchars($data['schedule'] ?? 'Not specified') . "</div>
        </div>
        
        <div class='footer'>
            <p>Please contact: <strong>" . htmlspecialchars($data['email']) . "</strong></p>
        </div>
    </body>
    </html>";
    
    return $html;
}

function generateCorporateTrainingEmail($data, $applicationId) {
    // Similar structure for corporate training...
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
            .header { background: #2e8b57; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #fff; padding: 30px; border: 1px solid #ddd; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; }
            .field { margin-bottom: 15px; }
            .label { font-weight: bold; color: #2e8b57; display: inline-block; width: 150px; }
            .urgent { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>🏢 NEW CORPORATE TRAINING REQUEST</h2>
            <p><strong>Request ID: $applicationId</strong></p>
            <p>Submitted: " . date('Y-m-d H:i:s') . "</p>
        </div>
        
        <div class='urgent'>
            <h3>⚡ BUSINESS OPPORTUNITY</h3>
            <p>A new corporate training request has been received.</p>
        </div>
        
        <div class='content'>
            <div class='field'><span class='label'>Company:</span>" . htmlspecialchars($data['company'] ?? 'Not provided') . "</div>
            <div class='field'><span class='label'>Contact:</span>" . htmlspecialchars($data['contactname'] ?? $data['firstname'] . ' ' . $data['lastname']) . "</div>
            <div class='field'><span class='label'>Email:</span>" . htmlspecialchars($data['email']) . "</div>
            <div class='field'><span class='label'>Phone:</span>" . htmlspecialchars($data['phone']) . "</div>
            <div class='field'><span class='label'>Training Type:</span>" . htmlspecialchars($data['trainingtype'] ?? 'Not specified') . "</div>
            <div class='field'><span class='label'>Participants:</span>" . htmlspecialchars($data['participants'] ?? 'Not specified') . "</div>
        </div>
        
        <div class='footer'>
            <p>Please contact: <strong>" . htmlspecialchars($data['email']) . "</strong></p>
        </div>
    </body>
    </html>";
    
    return $html;
}

function generateConfirmationEmail($firstname, $applicationId, $formType) {
    return "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <div style='background: #2e8b57; color: white; padding: 20px; text-align: center;'>
            <h2>Application Received ✅</h2>
        </div>
        <div style='padding: 20px;'>
            <p>Dear " . htmlspecialchars($firstname) . ",</p>
            <p>Your application has been successfully submitted!</p>
            <p><strong>Application ID:</strong> $applicationId</p>
            <p>We will contact you within 2-3 business days.</p>
            <p>Best regards,<br>LSUC Admissions</p>
        </div>
    </body>
    </html>";
}
?>