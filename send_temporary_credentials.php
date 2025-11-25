<?php
// This is a placeholder function for sending registration confirmation emails
// In a real implementation, you would use PHPMailer or similar

function sendRegistrationConfirmationEmail($to, $name, $student_number, $password) {
    $subject = "LSC Registration Approved - Your Student Credentials";
    
    $message = "
    <html>
    <head>
        <title>LSC Registration Approved</title>
    </head>
    <body>
        <h2>Welcome to Lusaka South College</h2>
        <p>Dear $name,</p>
        <p>Congratulations! Your registration has been approved.</p>
        <p>You are now a full-time student at Lusaka South College. Please use the following credentials to access your student dashboard:</p>
        <p><strong>Student Number (Username):</strong> $student_number</p>
        <p><strong>Password:</strong> $password</p>
        <p>Visit <a href='https://lsuclms.com/student_login.php'>https://lsuclms.com/student_login.php</a> to login to your student dashboard.</p>
        <p><strong>Important:</strong> Please change your password immediately after your first login for security purposes.</p>
        <p>Welcome to the LSC community!</p>
        <p>Best regards,<br>LSC Admissions Team</p>
    </body>
    </html>
    ";
    
    // In a real implementation, you would use PHPMailer:
    // $mail = new PHPMailer\PHPMailer\PHPMailer();
    // $mail->setFrom('admissions@lsuclms.com', 'LSC Admissions');
    // $mail->addAddress($to, $name);
    // $mail->Subject = $subject;
    // $mail->Body = $message;
    // $mail->isHTML(true);
    // $mail->send();
    
    // For now, we'll just log to a file
    $log_entry = date('Y-m-d H:i:s') . " - Registration confirmed for $to: Student Number=$student_number, Password=$password\n";
    file_put_contents('registration_confirmations.txt', $log_entry, FILE_APPEND);
    
    return true;
}

// Example usage:
// sendRegistrationConfirmationEmail('student@example.com', 'John Doe', 'LSC000001', 'securePassword123!');
?>