<?php
// Enhanced email submission handler with better error handling
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Enable CORS for cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Set content type to JSON
header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input or form data
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    // Log the received data for debugging
    error_log('Received form data: ' . print_r($input, true));
    
    // Validate required fields
    if (empty($input['formType']) || empty($input['email']) || empty($input['firstname']) || empty($input['lastname'])) {
        throw new Exception('Missing required fields: formType, email, firstname, or lastname');
    }
    
    $formType = $input['formType'];
    $applicantEmail = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
    
    if (!$applicantEmail) {
        throw new Exception('Invalid email address');
    }
    
    // Institution email
    $admissionsEmail = 'admissions@lsuczm.com';
    
    // Generate application ID
    $applicationId = 'LSUC-' . strtoupper($formType) . '-' . date('Y') . '-' . sprintf('%06d', rand(1, 999999));
    
    // Prepare email content based on form type
    $emailContent = generateEmailContent($input, $applicationId);
    $subject = $emailContent['subject'];
    $body = $emailContent['body'];
    
    // Try multiple email sending methods
    $mailSent = false;
    $emailMethod = '';
    
    // Method 1: Try using mail() function
    if (function_exists('mail')) {
        $headers = [
            'From: ' . $applicantEmail,
            'Reply-To: ' . $applicantEmail,
            'X-Mailer: PHP/' . phpversion(),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8'
        ];
        
        $mailSent = @mail($admissionsEmail, $subject, $body, implode("\r\n", $headers));
        if ($mailSent) {
            $emailMethod = 'PHP mail() function';
        }
    }
    
    // Method 2: Log to file if mail doesn't work (for testing)
    if (!$mailSent) {
        $logFile = __DIR__ . '/application_logs.txt';
        $logEntry = "\n" . str_repeat('=', 80) . "\n";
        $logEntry .= "Date: " . date('Y-m-d H:i:s') . "\n";
        $logEntry .= "Application ID: " . $applicationId . "\n";
        $logEntry .= "Email Subject: " . $subject . "\n";
        $logEntry .= "From: " . $applicantEmail . "\n";
        $logEntry .= "To: " . $admissionsEmail . "\n";
        $logEntry .= "Form Type: " . $formType . "\n";
        $logEntry .= "Raw Form Data: " . json_encode($input, JSON_PRETTY_PRINT) . "\n";
        $logEntry .= "Email Body: " . strip_tags($body) . "\n";
        $logEntry .= str_repeat('=', 80) . "\n";
        
        $written = file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        if ($written !== false) {
            $mailSent = true;
            $emailMethod = 'File logging (for testing)';
        }
    }
    
    if ($mailSent) {
        // Try to send confirmation email to applicant
        sendConfirmationEmail($applicantEmail, $input['firstname'], $applicationId, $formType);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Application submitted successfully!',
            'applicationId' => $applicationId,
            'method' => $emailMethod,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception('Failed to send application. Please try again or contact us directly.');
    }
    
} catch (Exception $e) {
    error_log('Error in submit_application.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'debug' => [
            'php_version' => phpversion(),
            'mail_function' => function_exists('mail') ? 'available' : 'not available',
            'received_data' => isset($rawInput) ? substr($rawInput, 0, 200) : 'no data'
        ]
    ]);
}

function generateEmailContent($data, $applicationId) {
    $formType = $data['formType'];
    $firstname = htmlspecialchars($data['firstname']);
    $lastname = htmlspecialchars($data['lastname']);
    
    switch ($formType) {
        case 'undergraduate':
            $subject = "New Undergraduate Application - $firstname $lastname";
            $body = generateUndergraduateEmail($data, $applicationId);
            break;
        case 'short-courses':
            $subject = "New Short Course Application - $firstname $lastname";
            $body = generateShortCourseEmail($data, $applicationId);
            break;
        case 'corporate-training':
            $subject = "New Corporate Training Request - " . htmlspecialchars($data['company']);
            $body = generateCorporateTrainingEmail($data, $applicationId);
            break;
        default:
            $subject = "New Application - $firstname $lastname";
            $body = generateGenericEmail($data, $applicationId);
    }
    
    return ['subject' => $subject, 'body' => $body];
}

function generateUndergraduateEmail($data, $applicationId) {
    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background: #2e8b57; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .field { margin-bottom: 15px; }
            .label { font-weight: bold; color: #2e8b57; }
            .value { margin-left: 10px; }
            .section { margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>New Undergraduate Application</h2>
            <p>Application ID: $applicationId</p>
        </div>
        <div class='content'>
            <div class='section'>
                <h3>Personal Information</h3>
                <div class='field'><span class='label'>Name:</span><span class='value'>" . htmlspecialchars($data['firstname']) . " " . htmlspecialchars($data['lastname']) . "</span></div>
                <div class='field'><span class='label'>Email:</span><span class='value'>" . htmlspecialchars($data['email']) . "</span></div>
                <div class='field'><span class='label'>Phone:</span><span class='value'>" . htmlspecialchars($data['phone']) . "</span></div>
                <div class='field'><span class='label'>NRC/Passport:</span><span class='value'>" . htmlspecialchars($data['nrc']) . "</span></div>
                <div class='field'><span class='label'>Date of Birth:</span><span class='value'>" . htmlspecialchars($data['dateofbirth']) . "</span></div>
                <div class='field'><span class='label'>Address:</span><span class='value'>" . nl2br(htmlspecialchars($data['address'])) . "</span></div>
            </div>
            
            <div class='section'>
                <h3>Academic Information</h3>
                <div class='field'><span class='label'>Preferred Program:</span><span class='value'>" . htmlspecialchars($data['program']) . "</span></div>
                <div class='field'><span class='label'>Preferred Intake:</span><span class='value'>" . htmlspecialchars($data['intake']) . "</span></div>
                <div class='field'><span class='label'>Previous School:</span><span class='value'>" . htmlspecialchars($data['previousschool']) . "</span></div>
                <div class='field'><span class='label'>Grade 12 Results:</span><span class='value'>" . htmlspecialchars($data['grade12results']) . "</span></div>
            </div>
            
            <div class='section'>
                <h3>Emergency Contact</h3>
                <div class='field'><span class='label'>Guardian Name:</span><span class='value'>" . htmlspecialchars($data['guardianname']) . "</span></div>
                <div class='field'><span class='label'>Guardian Phone:</span><span class='value'>" . htmlspecialchars($data['guardianphone']) . "</span></div>
                <div class='field'><span class='label'>Relationship:</span><span class='value'>" . htmlspecialchars($data['relationship']) . "</span></div>
            </div>
            
            <p><strong>Submitted on:</strong> " . date('Y-m-d H:i:s') . "</p>
        </div>
    </body>
    </html>";
    
    return $html;
}

function generateShortCourseEmail($data, $applicationId) {
    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background: #2e8b57; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .field { margin-bottom: 15px; }
            .label { font-weight: bold; color: #2e8b57; }
            .value { margin-left: 10px; }
            .section { margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>New Short Course Application</h2>
            <p>Application ID: $applicationId</p>
        </div>
        <div class='content'>
            <div class='section'>
                <h3>Personal Information</h3>
                <div class='field'><span class='label'>Name:</span><span class='value'>" . htmlspecialchars($data['firstname']) . " " . htmlspecialchars($data['lastname']) . "</span></div>
                <div class='field'><span class='label'>Email:</span><span class='value'>" . htmlspecialchars($data['email']) . "</span></div>
                <div class='field'><span class='label'>Phone:</span><span class='value'>" . htmlspecialchars($data['phone']) . "</span></div>
                <div class='field'><span class='label'>Current Occupation:</span><span class='value'>" . htmlspecialchars($data['occupation'] ?? 'Not specified') . "</span></div>
            </div>
            
            <div class='section'>
                <h3>Course Information</h3>
                <div class='field'><span class='label'>Selected Course:</span><span class='value'>" . htmlspecialchars($data['course']) . "</span></div>
                <div class='field'><span class='label'>Preferred Intake:</span><span class='value'>" . htmlspecialchars($data['startdate']) . "</span></div>
                <div class='field'><span class='label'>Mode of Study:</span><span class='value'>" . htmlspecialchars($data['schedule']) . "</span></div>
                <div class='field'><span class='label'>Relevant Experience:</span><span class='value'>" . nl2br(htmlspecialchars($data['experience'] ?? 'Not specified')) . "</span></div>
                <div class='field'><span class='label'>Learning Goals:</span><span class='value'>" . nl2br(htmlspecialchars($data['goals'] ?? 'Not specified')) . "</span></div>
            </div>
            
            <p><strong>Submitted on:</strong> " . date('Y-m-d H:i:s') . "</p>
        </div>
    </body>
    </html>";
    
    return $html;
}

function generateCorporateTrainingEmail($data, $applicationId) {
    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background: #2e8b57; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .field { margin-bottom: 15px; }
            .label { font-weight: bold; color: #2e8b57; }
            .value { margin-left: 10px; }
            .section { margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>New Corporate Training Request</h2>
            <p>Request ID: $applicationId</p>
        </div>
        <div class='content'>
            <div class='section'>
                <h3>Organization Information</h3>
                <div class='field'><span class='label'>Company:</span><span class='value'>" . htmlspecialchars($data['company']) . "</span></div>
                <div class='field'><span class='label'>Industry:</span><span class='value'>" . htmlspecialchars($data['industry']) . "</span></div>
                <div class='field'><span class='label'>Company Size:</span><span class='value'>" . htmlspecialchars($data['companysize']) . "</span></div>
                <div class='field'><span class='label'>Address:</span><span class='value'>" . nl2br(htmlspecialchars($data['address'])) . "</span></div>
            </div>
            
            <div class='section'>
                <h3>Contact Person</h3>
                <div class='field'><span class='label'>Name:</span><span class='value'>" . htmlspecialchars($data['contactname']) . "</span></div>
                <div class='field'><span class='label'>Position:</span><span class='value'>" . htmlspecialchars($data['position']) . "</span></div>
                <div class='field'><span class='label'>Email:</span><span class='value'>" . htmlspecialchars($data['email']) . "</span></div>
                <div class='field'><span class='label'>Phone:</span><span class='value'>" . htmlspecialchars($data['phone']) . "</span></div>
            </div>
            
            <div class='section'>
                <h3>Training Requirements</h3>
                <div class='field'><span class='label'>Training Type:</span><span class='value'>" . htmlspecialchars($data['trainingtype']) . "</span></div>
                <div class='field'><span class='label'>Number of Participants:</span><span class='value'>" . htmlspecialchars($data['participants']) . "</span></div>
                <div class='field'><span class='label'>Duration:</span><span class='value'>" . htmlspecialchars($data['duration']) . "</span></div>
                <div class='field'><span class='label'>Location:</span><span class='value'>" . htmlspecialchars($data['location']) . "</span></div>
                <div class='field'><span class='label'>Budget Range:</span><span class='value'>" . htmlspecialchars($data['budget'] ?? 'Not specified') . "</span></div>
                <div class='field'><span class='label'>Specific Needs:</span><span class='value'>" . nl2br(htmlspecialchars($data['specificneeds'])) . "</span></div>
                <div class='field'><span class='label'>Timeline:</span><span class='value'>" . nl2br(htmlspecialchars($data['timeline'] ?? 'Not specified')) . "</span></div>
            </div>
            
            <p><strong>Submitted on:</strong> " . date('Y-m-d H:i:s') . "</p>
        </div>
    </body>
    </html>";
    
    return $html;
}

function sendConfirmationEmail($email, $firstname, $applicationId, $formType) {
    try {
        $subject = "Application Confirmation - Lusaka South College";
        
        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: #2e8b57; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>Application Received</h2>
                <p>Lusaka South College</p>
            </div>
            <div class='content'>
                <p>Dear " . htmlspecialchars($firstname) . ",</p>
                
                <p>Thank you for your application to Lusaka South College. We have successfully received your " . 
                str_replace('-', ' ', $formType) . " application.</p>
                
                <p><strong>Application ID:</strong> $applicationId</p>
                <p><strong>Submitted on:</strong> " . date('Y-m-d H:i:s') . "</p>
                
                <p>Our admissions team will review your application and contact you within 2-3 business days.</p>
                
                <p>If you have any questions, please don't hesitate to contact us:</p>
                <ul>
                    <li>Email: admissions@lsuczm.com</li>
                    <li>Phone: +260-211-292-299</li>
                </ul>
                
                <p>We appreciate your interest in Lusaka South College.</p>
                
                <p>Best regards,<br>
                Admissions Office<br>
                Lusaka South College</p>
            </div>
            <div class='footer'>
                <p>&copy; 2025 Lusaka South College. All rights reserved.</p>
                <p>Dream, Explore, Acquire</p>
            </div>
        </body>
        </html>";
        
        $headers = [
            'From: admissions@lsuczm.com',
            'Reply-To: admissions@lsuczm.com',
            'X-Mailer: PHP/' . phpversion(),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8'
        ];
        
        // Try to send confirmation email, but don't fail if it doesn't work
        @mail($email, $subject, $body, implode("\r\n", $headers));
        
    } catch (Exception $e) {
        // Log error but don't fail the main application submission
        error_log('Failed to send confirmation email: ' . $e->getMessage());
    }
}
?>