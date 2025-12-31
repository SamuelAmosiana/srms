
<?php
/**
 * Email Configuration for Namecheap Hosting (LSUCLMS)
 */

// SMTP settings for enroll@lsuclms.com
define('SMTP_HOST', 'lsuclms.com'); 
define('SMTP_USERNAME', 'enroll@lsuclms.com');
define('SMTP_PASSWORD', '****');  // Hidden for security purposes, replace with actual password
define('SMTP_PORT', 465);          
define('SMTP_SECURE', 'ssl');      

// Email metadata
define('EMAIL_FROM', 'enroll@lsuclms.com');
define('EMAIL_FROM_NAME', 'LSC Enrollment Office');
define('EMAIL_REPLY_TO', 'enroll@lsuclms.com');

// Portal URL
define('PORTAL_URL', 'https://lsuclms.com/srms/login.php');

// Test function
function testEmailConfig() {
    echo "SMTP Host: " . SMTP_HOST . "\n";
    echo "SMTP Username: " . SMTP_USERNAME . "\n";
    echo "SMTP Port: " . SMTP_PORT . "\n";
    echo "SMTP Security: " . SMTP_SECURE . "\n";
    echo "From Email: " . EMAIL_FROM . "\n";
    echo "From Name: " . EMAIL_FROM_NAME . "\n";
}
?>