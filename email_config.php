<?php
/**
 * Email Configuration for Namecheap Hosting
 * 
 * This file contains the SMTP settings for sending emails through Namecheap's mail servers
 */

// Email configuration constants
define('SMTP_HOST', 'mail.lsuczm.com');
define('SMTP_USERNAME', 'admissions@lsuczm.com');
define('SMTP_PASSWORD', '#@adm1missions');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('EMAIL_FROM', 'admissions@lsuczm.com');
define('EMAIL_FROM_NAME', 'LSC Admissions Office');
define('EMAIL_REPLY_TO', 'admissions@lsuczm.com');
define('PORTAL_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/srms/login.php');

// Test email function
function testEmailConfig() {
    echo "Email Configuration:\n";
    echo "SMTP Host: " . SMTP_HOST . "\n";
    echo "SMTP Username: " . SMTP_USERNAME . "\n";
    echo "SMTP Port: " . SMTP_PORT . "\n";
    echo "SMTP Security: " . SMTP_SECURE . "\n";
    echo "From Email: " . EMAIL_FROM . "\n";
    echo "From Name: " . EMAIL_FROM_NAME . "\n";
}

// Example usage (uncomment to test)
// testEmailConfig();
?>