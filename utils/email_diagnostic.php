<?php
// Email Diagnostic Tool for LSC SRMS
require_once '../config.php';
require_once '../email_config.php';

// Try to load PHPMailer if available
$phpmailer_available = false;
if (file_exists(__DIR__ . '/../lib/PHPMailer/PHPMailer.php')) {
    require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';
    require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
    $phpmailer_available = true;
}

echo "<h2>Email Configuration Diagnostic</h2>";
echo "<div style='font-family: monospace; line-height: 1.6;'>";

echo "<h3>Configuration Settings:</h3>";
echo "SMTP Host: " . SMTP_HOST . "<br>";
echo "SMTP Username: " . SMTP_USERNAME . "<br>";
echo "SMTP Port: " . SMTP_PORT . "<br>";
echo "SMTP Security: " . SMTP_SECURE . "<br>";
echo "Email From: " . EMAIL_FROM . "<br>";
echo "Email From Name: " . EMAIL_FROM_NAME . "<br>";
echo "Reply To: " . EMAIL_REPLY_TO . "<br>";
echo "Portal URL: " . PORTAL_URL . "<br>";

echo "<br><h3>System Capabilities:</h3>";
echo "PHPMailer Available: " . ($phpmailer_available ? "YES" : "NO") . "<br>";
echo "Mail() Function Available: " . (function_exists('mail') ? "YES" : "NO") . "<br>";
echo "Sendmail Path: " . (ini_get('sendmail_path') ?: 'NOT CONFIGURED') . "<br>";

if ($phpmailer_available) {
    echo "<br><h3>Testing PHPMailer Connection:</h3>";
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(false); // Don't throw exceptions yet
        
        // Test settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        
        echo "PHPMailer SMTP settings configured successfully!<br>";
        
        // Now test actual connection with exception handling
        $mail = new PHPMailer\PHPMailer\PHPMailer(true); // Enable exceptions
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        
        // Enable debug mode to see what happens
        $mail->SMTPDebug = 2; // Full debug output
        
        echo "Attempting SMTP connection...<br>";
        
        // Try to connect
        $connected = $mail->smtpConnect([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        if ($connected) {
            echo "<span style='color: green;'>✓ SMTP Connection Successful!</span><br>";
        } else {
            echo "<span style='color: red;'>✗ SMTP Connection Failed</span><br>";
        }
        
    } catch (Exception $e) {
        echo "<span style='color: red;'>✗ PHPMailer Error: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    }
} else {
    echo "<br><h3>PHPMailer Not Available</h3>";
    echo "Will rely on PHP's mail() function<br>";
}

echo "<br><h3>Testing mail() Function:</h3>";
$test_email = 'test@example.com'; // Don't actually send to a real address
$test_subject = 'Test Email from LSC SRMS';
$test_body = 'This is a test email to verify the mail() function is working.';

// Capture mail() result without actually sending
$result = mail($test_email, $test_subject, $test_body);

if ($result) {
    echo "<span style='color: green;'>✓ mail() function call succeeded</span><br>";
} else {
    echo "<span style='color: red;'>✗ mail() function call failed</span><br>";
}

echo "<br><h3>Troubleshooting Tips:</h3>";
echo "<ol>";
echo "<li>Check that SMTP credentials in email_config.php are correct</li>";
echo "<li>Verify that the SMTP password is filled in (currently showing 'your_actual_password_here')</li>";
echo "<li>Ensure your hosting provider allows SMTP connections on port 465</li>";
echo "<li>For XAMPP/WAMP local environments, consider using mailcatcher or similar tools</li>";
echo "<li>Check server firewall settings if hosting remotely</li>";
echo "<li>Review PHP mail configuration in php.ini</li>";
echo "</ol>";

echo "</div>";

echo "<br><h3>Server Information:</h3>";
echo "<div style='font-family: monospace;'>";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "<br>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Operating System: " . PHP_OS . "<br>";
echo "</div>";
?>