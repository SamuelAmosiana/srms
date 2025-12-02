<?php
session_start();
require_once 'config.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    // Normalize possible student number input for comparison
    $normalizedUsername = strtoupper($username);
    
    if (empty($username) || empty($password)) {
        $message = "Please enter both username and password!";
        $messageType = 'error';
    } else {
        try {
            // Check if it's a regular student user by username
            $stmt = $pdo->prepare("SELECT u.*, sp.student_number FROM users u JOIN student_profile sp ON u.id = sp.user_id WHERE u.username = ? AND u.is_active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            // If not found by username, try finding by student number
            if (!$user) {
                $stmt = $pdo->prepare("SELECT u.*, sp.student_number FROM student_profile sp JOIN users u ON sp.user_id = u.id WHERE sp.student_number = ? AND u.is_active = 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
            }

            $loginOk = false;
            if ($user) {
                // Primary: verify against stored password hash
                if (!empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
                    $loginOk = true;
                } else {
                    // Fallback: allow login if student number is used as both username and password
                    $studentNumber = isset($user['student_number']) ? strtoupper(trim($user['student_number'])) : '';
                    if ($password === $username && ($normalizedUsername === $studentNumber || $normalizedUsername === strtoupper($user['username']))) {
                        $loginOk = true;
                    }
                }
            }

            if ($loginOk) {
                $_SESSION['user_id'] = $user['id'];
                // Ensure Student role is present in session for role checks on dashboard
                if (!isset($_SESSION['roles']) || !is_array($_SESSION['roles'])) {
                    $_SESSION['roles'] = [];
                }
                if (!in_array('Student', $_SESSION['roles'])) {
                    $_SESSION['roles'][] = 'Student';
                }
                // Use relative path for redirect to avoid issues
                header('Location: student/dashboard.php');
                exit();
            } else {
                // More detailed error message for debugging
                if (!$user) {
                    $message = "Account not found. Please check your student number.";
                } else {
                    $message = "Invalid password. Please try again.";
                }
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
    <title>Student Login - LSC SRMS</title>
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
        
        .first-time-link {
            display: block;
            text-align: center;
            margin-top: 15px;
        }
        
        .first-time-link a {
            color: #007bff;
            text-decoration: none;
        }
        
        .first-time-link a:hover {
            text-decoration: underline;
        }
        
        .toggle-links {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }
        
        .toggle-links a {
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
        }
        
        .toggle-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body class="login-body">
    <div class="login-container">
        <div class="logo-container">
            <img src="assets/images/lsc-logo.png" alt="LSC Logo" class="logo" onerror="this.style.display='none'">
            <span class="logo-text">Lusaka South College</span>
            <p>Student Portal</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="icon-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Student Number</label>
                <input type="text" id="username" name="username" required placeholder="Enter your student number ">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password">
            </div>
            
            <button type="submit" class="btn">
                <i class="icon-sign-in"></i> Login
            </button>
        </form>
        
        <div class="toggle-links">
            <a href="first_time_login.php">First time registration</a>
            <a href="student_login.php">Regular student login</a>
        </div>
        
        <div class="login-footer">
            <p>Use your student number as username to login.</p>
        </div>
    </div>
</body>
</html>