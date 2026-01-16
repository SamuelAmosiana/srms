<?php
// SMTP Email sender using simple PHP mail with proper headers
function sendSMTPEmail($to, $subject, $body, $from = 'admissions@lsuczm.com', $fromName = 'LSUC Admissions') {
    // Set proper headers for email
    $headers = array();
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-type: text/html; charset=UTF-8";
    $headers[] = "From: $fromName <$from>";
    $headers[] = "Reply-To: $from";
    $headers[] = "X-Mailer: PHP/" . phpversion();
    $headers[] = "X-Priority: 1";
    
    // Convert headers array to string
    $headersString = implode("\r\n", $headers);
    
    // Try to send email
    $mailSent = mail($to, $subject, $body, $headersString);
    
    if (!$mailSent) {
        // Get the last error
        $error = error_get_last();
        throw new Exception('Mail sending failed: ' . ($error['message'] ?? 'Unknown error'));
    }
    
    return true;
}

// Alternative SMTP function using sockets (for when mail() doesn't work)
function sendSocketEmail($to, $subject, $body, $from = 'admissions@lsuczm.com') {
    // This is a basic SMTP implementation
    // For production use, consider using PHPMailer or SwiftMailer
    
    try {
        // Try to connect to local SMTP server
        $smtp_server = "localhost";
        $smtp_port = 25;
        
        $socket = @fsockopen($smtp_server, $smtp_port, $errno, $errstr, 10);
        
        if (!$socket) {
            throw new Exception("Could not connect to SMTP server: $errstr ($errno)");
        }
        
        // Read initial response
        $response = fgets($socket, 512);
        
        // Send HELO command
        fputs($socket, "HELO localhost\r\n");
        $response = fgets($socket, 512);
        
        // Send MAIL FROM
        fputs($socket, "MAIL FROM: <$from>\r\n");
        $response = fgets($socket, 512);
        
        // Send RCPT TO
        fputs($socket, "RCPT TO: <$to>\r\n");
        $response = fgets($socket, 512);
        
        // Send DATA command
        fputs($socket, "DATA\r\n");
        $response = fgets($socket, 512);
        
        // Send email headers and body
        $email_content = "Subject: $subject\r\n";
        $email_content .= "From: $from\r\n";
        $email_content .= "To: $to\r\n";
        $email_content .= "Content-Type: text/html; charset=UTF-8\r\n";
        $email_content .= "\r\n";
        $email_content .= $body;
        $email_content .= "\r\n.\r\n";
        
        fputs($socket, $email_content);
        $response = fgets($socket, 512);
        
        // Send QUIT
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        return true;
        
    } catch (Exception $e) {
        throw new Exception("Socket email failed: " . $e->getMessage());
    }
}

// Gmail SMTP function (requires app password)
function sendGmailSMTP($to, $subject, $body, $gmail_user, $gmail_pass, $from_name = 'LSUC Admissions') {
    require_once 'PHPMailer/PHPMailer.php';
    require_once 'PHPMailer/SMTP.php';
    require_once 'PHPMailer/Exception.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $gmail_user;
        $mail->Password   = $gmail_pass;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom($gmail_user, $from_name);
        $mail->addAddress($to);
        $mail->addReplyTo('admissions@lsuczm.com', 'LSUC Admissions');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        throw new Exception("Gmail SMTP failed: " . $mail->ErrorInfo);
    }
}
?>