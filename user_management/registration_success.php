<?php
// Simple success page for first-time registration
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Submitted - LSC SRMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .success-container {
            max-width: 600px;
            margin: 100px auto;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .success-icon {
            font-size: 64px;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #0056b3;
        }
        
        .instructions {
            text-align: left;
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-top: 30px;
        }
        
        .instructions h3 {
            margin-top: 0;
        }
        
        .instructions ul {
            padding-left: 20px;
        }
        
        .instructions li {
            margin-bottom: 10px;
        }
    </style>
</head>
<body class="login-body">
    <div class="success-container">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        
        <h1>Registration Submitted Successfully!</h1>
        <p>Thank you for completing your registration.</p>
        <p>Our admissions team will review your application and payment proof.</p>
        <p>You will receive an email with your student number and login credentials once your registration is approved.</p>
        <p>Please check your email (including spam/junk folder) within 2-3 business days.</p>
        
        <a href="student_login.php" class="btn btn-primary">
            <i class="fas fa-sign-in-alt"></i> Go to Student Login
        </a>
        
        <div class="instructions">
            <h3>What happens next:</h3>
            <ul>
                <li>Your registration will be reviewed by our admissions team</li>
                <li>Payment proof will be verified</li>
                <li>You will receive an email with your student number and temporary password</li>
                <li>Use your student number to login to the student dashboard</li>
                <li>Change your password after first login for security</li>
            </ul>
        </div>
    </div>
</body>
</html>