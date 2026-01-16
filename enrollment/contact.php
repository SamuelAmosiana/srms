<?php
// Enhanced Contact Form with SMTP Support
// Instructions for SMTP setup at the bottom of this file

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name    = htmlspecialchars($_POST['name']);
    $email   = htmlspecialchars($_POST['email']);
    $message = htmlspecialchars($_POST['message']);
    $application_type = isset($_POST['application_type']) ? htmlspecialchars($_POST['application_type']) : 'General Application';

    $to      = "admissions@lsuczm.com"; // ✅ Your target email
    $subject = "New $application_type - $name";
    $body    = "You have received a new application:\n\n".
               "Name: $name\n".
               "Email: $email\n".
               "Application Type: $application_type\n\n".
               "Message:\n$message\n";

    // 📝 STEP 1: Try basic PHP mail() first
    $headers = "From: no-reply@lsuczm.com\r\n"; 
    $headers .= "Reply-To: $email\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $mail_sent = @mail($to, $subject, $body, $headers);

    if ($mail_sent) {
        echo "✅ Success! Your application has been sent.";
        error_log("Email sent successfully to $to from $email");
    } else {
        // 📝 STEP 2: If basic mail() fails, try SMTP
        $smtp_result = sendWithSMTP($to, $subject, $body, $email, $name);
        
        if ($smtp_result['success']) {
            echo "✅ Success! Your application has been sent.";
        } else {
            // 📝 STEP 3: Log for manual processing if both fail
            logFailedEmail($to, $subject, $body, $email, $smtp_result['error']);
            echo "❌ Error sending your application. Please try again later.";
        }
    }
}

// 🖯 SMTP Function - UPDATE THESE CREDENTIALS
function sendWithSMTP($to, $subject, $body, $reply_to, $from_name) {
    // 🔴 IMPORTANT: Update these SMTP settings with your actual email provider details
    // Common providers:
    // Gmail: smtp.gmail.com, port 587, TLS
    // Outlook: smtp-mail.outlook.com, port 587, TLS  
    // GoDaddy: smtpout.secureserver.net, port 587, TLS
    // cPanel/WHM: mail.yourdomain.com, port 587, TLS
    
    $smtp_config = [
        'host' => 'mail.lsuczm.com',           // ❗ CHANGE THIS to your mail server
        'port' => 587,                         // Common: 587 (TLS), 465 (SSL), 25 (unsecured)
        'username' => 'admissions@lsuczm.com', // ❗ CHANGE THIS to your email
        'password' => 'YOUR_EMAIL_PASSWORD',   // ❗ CHANGE THIS to your email password
        'encryption' => 'tls',                 // 'tls', 'ssl', or '' for none
        'timeout' => 30
    ];
    
    // Don't try SMTP if credentials not set
    if ($smtp_config['password'] === 'YOUR_EMAIL_PASSWORD') {
        return ['success' => false, 'error' => 'SMTP credentials not configured'];
    }
    
    try {
        // Create socket connection
        $context = stream_context_create();
        if ($smtp_config['encryption'] === 'ssl') {
            $smtp_config['host'] = 'ssl://' . $smtp_config['host'];
        }
        
        $socket = @stream_socket_client(
            $smtp_config['host'] . ':' . $smtp_config['port'],
            $errno, $errstr, $smtp_config['timeout'],
            STREAM_CLIENT_CONNECT, $context
        );
        
        if (!$socket) {
            throw new Exception("Connection failed: $errstr ($errno)");
        }
        
        // Read initial response
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '220') {
            throw new Exception("Server error: $response");
        }
        
        // Send EHLO
        fwrite($socket, "EHLO localhost\r\n");
        $response = fgets($socket, 515);
        
        // Start TLS if needed
        if ($smtp_config['encryption'] === 'tls') {
            fwrite($socket, "STARTTLS\r\n");
            $response = fgets($socket, 515);
            if (substr($response, 0, 3) != '220') {
                throw new Exception("STARTTLS failed: $response");
            }
            
            // Enable crypto
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("TLS encryption failed");
            }
            
            // Send EHLO again after TLS
            fwrite($socket, "EHLO localhost\r\n");
            $response = fgets($socket, 515);
        }
        
        // Authenticate
        fwrite($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '334') {
            throw new Exception("AUTH LOGIN failed: $response");
        }
        
        fwrite($socket, base64_encode($smtp_config['username']) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '334') {
            throw new Exception("Username rejected: $response");
        }
        
        fwrite($socket, base64_encode($smtp_config['password']) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '235') {
            throw new Exception("Authentication failed: $response");
        }
        
        // Send email
        fwrite($socket, "MAIL FROM: <" . $smtp_config['username'] . ">\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '250') {
            throw new Exception("MAIL FROM failed: $response");
        }
        
        fwrite($socket, "RCPT TO: <$to>\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '250') {
            throw new Exception("RCPT TO failed: $response");
        }
        
        fwrite($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '354') {
            throw new Exception("DATA failed: $response");
        }
        
        // Email content
        $email_content = "From: $from_name <" . $smtp_config['username'] . ">\r\n";
        $email_content .= "Reply-To: $reply_to\r\n";
        $email_content .= "To: $to\r\n";
        $email_content .= "Subject: $subject\r\n";
        $email_content .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $email_content .= "\r\n";
        $email_content .= $body;
        $email_content .= "\r\n.\r\n";
        
        fwrite($socket, $email_content);
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '250') {
            throw new Exception("Message send failed: $response");
        }
        
        // Quit
        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        
        return ['success' => true, 'error' => ''];
        
    } catch (Exception $e) {
        if (isset($socket) && $socket) {
            fclose($socket);
        }
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// 📋 Logging function for failed emails
function logFailedEmail($to, $subject, $body, $from_email, $error) {
    $log_file = __DIR__ . '/application_logs.txt';
    $log_entry = "\n" . str_repeat('=', 60) . "\n";
    $log_entry .= "FAILED EMAIL - " . date('Y-m-d H:i:s') . "\n";
    $log_entry .= "To: $to\n";
    $log_entry .= "From: $from_email\n";
    $log_entry .= "Subject: $subject\n";
    $log_entry .= "Error: $error\n";
    $log_entry .= "Body:\n$body\n";
    $log_entry .= str_repeat('=', 60) . "\n";
    
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    error_log("Email failed - logged to application_logs.txt: $error");
}

/*
📝 SMTP CONFIGURATION INSTRUCTIONS:

1. 🔍 Find your email provider's SMTP settings:
   
   • Gmail:
     Host: smtp.gmail.com
     Port: 587
     Encryption: TLS
     Username: your-gmail@gmail.com
     Password: Your App Password (not regular password)
   
   • Outlook/Hotmail:
     Host: smtp-mail.outlook.com
     Port: 587  
     Encryption: TLS
     Username: your-email@outlook.com
     Password: Your account password
   
   • GoDaddy/Domain Email:
     Host: smtpout.secureserver.net (or mail.yourdomain.com)
     Port: 587
     Encryption: TLS
     Username: admissions@lsuczm.com
     Password: Your email password
   
   • cPanel/WHM Hosting:
     Host: mail.yourdomain.com
     Port: 587
     Encryption: TLS
     Username: admissions@lsuczm.com
     Password: Your email password

2. 🔄 Update the $smtp_config array above (around line 47-54):
   - Replace 'mail.lsuczm.com' with your SMTP host
   - Replace 'YOUR_EMAIL_PASSWORD' with your actual password
   - Adjust port and encryption if needed

3. ✅ Test the form - it will try basic PHP mail() first, then SMTP if that fails

4. 📁 Check application_logs.txt if emails still fail

📞 Need help? Contact your hosting provider for SMTP settings!
*/
?>
?>