<?php
session_start();
require_once 'config.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $message = "Please enter your email address!";
        $messageType = 'error';
    } else {
        try {
            // Check if student has an approved application
            $stmt = $pdo->prepare("SELECT * FROM applications WHERE email = ? AND status = 'approved'");
            $stmt->execute([$email]);
            $application = $stmt->fetch();
            
            if ($application) {
                // Redirect to first time registration form with pre-filled data
                header('Location: first_time_registration.php?email=' . urlencode($email));
                exit();
            } else {
                $message = "No approved registration found for this email. Please check your email or contact admissions.";
                $messageType = 'error';
            }
        } catch (Exception $e) {
            $message = "Login error: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>First Time Login - LSC SRMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Simple icon replacements using CSS */
        .icon-sign-in:before { content: "→"; }
        .icon-check-circle:before { content: "✓"; }
        .icon-exclamation-triangle:before { content: "!"; }
    
    .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo {
            max-width: 150px;
            height: auto;
        }
        
        .logo-text {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-top: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 4px;
            background-color: #007bff;
            color: white;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #0056b3;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 15px;
        }
        
        .back-link a {
            color: #007bff;
            text-decoration: none;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body class="login-body">
    <div class="login-container">
        <div class="logo-container">
            <img src="assets/images/lsc-logo.png" alt="LSC Logo" class="logo" onerror="this.style.display='none'">
            <span class="logo-text">LSC SRMS</span>
            <p>First Time Student Portal</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="icon-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="Enter your email address">
            </div>
            
            <button type="submit" class="btn">
                <i class="icon-sign-in"></i> Access Registration
            </button>
        </form>
        
        <div class="back-link">
            <a href="student_login.php">← Back to Student Login</a>
        </div>
        
        <div class="login-footer">
            <p>Enter the email address you used during your application.</p>
        </div>
    </div>
</body>
</html>