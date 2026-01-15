<?php
require_once '../config.php';
require_once '../email_config.php';

// Try to load PHPMailer if available
$phpmailer_available = false;
if (file_exists(__DIR__ . '/lib/PHPMailer/PHPMailer.php')) {
    require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/lib/PHPMailer/SMTP.php';
    require_once __DIR__ . '/lib/PHPMailer/Exception.php';
    $phpmailer_available = true;
}

echo "Email Configuration Test\n";
echo "======================\n";
echo "SMTP Host: " . SMTP_HOST . "\n";
echo "SMTP Username: " . SMTP_USERNAME . "\n";
echo "SMTP Port: " . SMTP_PORT . "\n";
echo "SMTP Security: " . SMTP_SECURE . "\n";
echo "Email From: " . EMAIL_FROM . "\n";
echo "Email From Name: " . EMAIL_FROM_NAME . "\n";
echo "Reply To: " . EMAIL_REPLY_TO . "\n";
echo "Portal URL: " . PORTAL_URL . "\n";

echo "\nPHPMailer Status: " . ($phpmailer_available ? "Installed" : "Not found") . "\n";

if ($phpmailer_available) {
    echo "\nTesting PHPMailer connection...\n";
    
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
        
        echo "PHPMailer configuration successful!\n";
        echo "Ready to send emails.\n";
    } catch (Exception $e) {
        echo "PHPMailer configuration error: " . $e->getMessage() . "\n";
    }
} else {
    echo "\nFalling back to PHP mail() function.\n";
}

echo "\nTo test actual email sending, approve an application in the system.\n";
?>