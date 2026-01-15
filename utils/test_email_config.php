<?php
require_once '../email_config.php';

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

// Test if PHPMailer files exist
if (file_exists(__DIR__ . '/lib/PHPMailer/PHPMailer.php')) {
    echo "\nPHPMailer Status: Installed\n";
} else {
    echo "\nPHPMailer Status: Not found\n";
}
?>