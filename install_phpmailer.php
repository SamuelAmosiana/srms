<?php
/**
 * PHPMailer Installation Script for Namecheap
 * 
 * This script provides instructions for installing PHPMailer on Namecheap hosting
 */

echo "PHPMailer Installation Instructions for Namecheap\n";
echo "===============================================\n\n";

echo "1. Download PHPMailer:\n";
echo "   - Go to https://github.com/PHPMailer/PHPMailer\n";
echo "   - Click 'Code' then 'Download ZIP'\n";
echo "   - Extract the ZIP file\n\n";

echo "2. Upload PHPMailer files:\n";
echo "   - Upload the following files from the PHPMailer/src/ directory to your server:\n";
echo "     - PHPMailer.php\n";
echo "     - SMTP.php\n";
echo "     - Exception.php\n";
echo "   - Upload them to: /lib/PHPMailer/\n\n";

echo "3. Directory structure should look like:\n";
echo "   /lib/PHPMailer/PHPMailer.php\n";
echo "   /lib/PHPMailer/SMTP.php\n";
echo "   /lib/PHPMailer/Exception.php\n\n";

echo "4. Once uploaded, the system will automatically use PHPMailer for sending emails.\n";
echo "   Emails will be sent using your Namecheap SMTP settings:\n";
echo "   - SMTP Host: mail.lsuczm.com\n";
echo "   - Username: admissions@lsuczm.com\n";
echo "   - Password: #@adm1missions\n";
echo "   - Port: 587\n";
echo "   - Security: TLS\n\n";

echo "5. Test the configuration by approving an application in the enrollment system.\n";
echo "   Check your PHP error log for confirmation messages.\n\n";

echo "Note: If you don't upload PHPMailer, the system will fall back to the standard mail() function.\n";
?>