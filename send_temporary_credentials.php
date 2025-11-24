<?php
// This is a placeholder function for sending temporary credentials
// In a real implementation, you would use PHPMailer or similar

function sendTemporaryCredentialsEmail($to, $name, $username, $password) {
    $subject = "Welcome to LSC - Temporary Login Credentials";
    
    $message = "
    <html>
    <head>
        <title>Welcome to LSC</title>
    </head>
    <body>
        <h2>Welcome to Lusaka South College</h2>
        <p>Dear $name,</p>
        <p>Congratulations! Your application has been accepted.</p>
        <p>Please use the following temporary credentials to complete your first-time registration:</p>
        <p><strong>Username:</strong> $username</p>
        <p><strong>Password:</strong> $password</p>
        <p>Visit <a href='https://lsuclms.com/student_login.php'>https://lsuclms.com/student_login.php</a> to login and complete your registration.</p>
        <p><strong>Please change your password after your first login.</strong></p>
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
    $log_entry = date('Y-m-d H:i:s') . " - Sent credentials to $to: Username=$username, Password=$password\n";
    file_put_contents('credential_logs.txt', $log_entry, FILE_APPEND);
    
    return true;
}

// Example usage:
// sendTemporaryCredentialsEmail('student@example.com', 'John Doe', 'student@example.com', 'temp123456');
?>