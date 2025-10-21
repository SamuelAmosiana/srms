<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studnum = strtoupper(trim($_POST['student_number']));
    $password = $_POST['password'] ?? '';

    // Updated regex to accept both LSC format and STU-YYYY-NNNN format
    if (!preg_match('/^(LSC\d{6}|STU-\d{4}-\d{4})$/', $studnum)) {
        $error = "Invalid student number format. Use LSC000001 or STU-2025-0016";
    } else {
        $stmt = $pdo->prepare("SELECT u.id, u.password_hash FROM users u 
                               JOIN student_profile s ON u.id = s.user_id 
                               WHERE s.student_number = ? LIMIT 1");
        $stmt->execute([$studnum]);
        $user = $stmt->fetch();

        if ($user && (empty($user['password_hash']) || password_verify($password, $user['password_hash']))) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['roles'] = ['Student'];
            header('Location: student/dashboard.php');
            exit;
        } else {
            $error = "Student not found or invalid password";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Login - SRMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="container">
    <div class="school-header">
        <h1>Lusaka South College</h1>
        <div class="subtitle">Student Portal</div>
    </div>
    <h2>Student Login</h2>
    <p>Enter your student number to access your account</p>
    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
    <form method="POST">
        <input type="text" name="student_number" placeholder="🎓 Student ID / Username" required>
        <input type="password" name="password" placeholder="🔒 Password ">
        <button type="submit">🚀 Login</button>
    </form>
    <div class="back-link">
        <a href="https://lsuczm.com/#home">← Back to Home</a>
    </div>
</div>
</body>
</html>