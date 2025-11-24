<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['temp_user_id'])) {
    header('Location: student_login.php');
    exit();
}

// Get user info
$temp_user_id = $_SESSION['temp_user_id'];
$stmt = $pdo->prepare("SELECT * FROM pending_students WHERE id = ?");
$stmt->execute([$temp_user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: student_login.php');
    exit();
}

// Check if registration is approved
if ($user['registration_status'] !== 'approved' || empty($user['student_number'])) {
    // Redirect back to registration if not approved
    header('Location: first_time_registration.php');
    exit();
}

// Clear temp session
unset($_SESSION['temp_user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful - LSC SRMS</title>
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
        
        .student-number {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin: 20px 0;
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
        
        <h1>Registration Successful!</h1>
        <p>Congratulations <?php echo htmlspecialchars($user['full_name']); ?>!</p>
        <p>Your registration has been approved and you are now a full-time student at LSC.</p>
        
        <div class="student-number">
            Your Student Number: <?php echo htmlspecialchars($user['student_number']); ?>
        </div>
        
        <p>You can now login to the student dashboard using your student number and the password you created.</p>
        
        <a href="student_login.php" class="btn btn-primary">
            <i class="fas fa-sign-in-alt"></i> Go to Student Login
        </a>
        
        <div class="instructions">
            <h3>Next Steps:</h3>
            <ul>
                <li>Login to the student dashboard using your student number</li>
                <li>Check your course registration status</li>
                <li>View your academic calendar and important dates</li>
                <li>Access your course materials and resources</li>
                <li>Contact your academic advisor if you have any questions</li>
            </ul>
        </div>
    </div>
</body>
</html>