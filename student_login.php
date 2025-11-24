<?php
session_start();
require_once 'config.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $message = "Please enter both username and password!";
        $messageType = 'error';
    } else {
        try {
            // First check if it's a regular student user
            $stmt = $pdo->prepare("SELECT * FROM users u JOIN student_profile sp ON u.id = sp.user_id WHERE u.username = ? AND u.is_active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Regular student login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = 'Student';
                header('Location: student/dashboard.php');
                exit();
            } else {
                // Check if it's a pending student with temporary credentials
                $stmt = $pdo->prepare("SELECT * FROM pending_students WHERE email = ? AND temp_password = ? AND status = 'accepted'");
                $stmt->execute([$username, $password]); // For pending students, we're using plain text temp password
                $pending_user = $stmt->fetch();
                
                if ($pending_user) {
                    // Temporary user login - redirect to first-time registration
                    $_SESSION['temp_user_id'] = $pending_user['id'];
                    header('Location: first_time_registration.php');
                    exit();
                } else {
                    $message = "Invalid username or password!";
                    $messageType = 'error';
                }
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
    <title>Student Login - LSC SRMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
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
    </style>
</head>
<body class="login-body">
    <div class="login-container">
        <div class="logo-container">
            <img src="assets/images/lsc-logo.png" alt="LSC Logo" class="logo" onerror="this.style.display='none'">
            <span class="logo-text">LSC SRMS</span>
            <p>Student Portal</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Email Address</label>
                <input type="text" id="username" name="username" required placeholder="Enter your email">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password">
            </div>
            
            <button type="submit" class="btn">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
        
        <div class="login-footer">
            <p>First time user? Please use the credentials sent to your email.</p>
        </div>
    </div>
</body>
</html>