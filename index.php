<?php
// Landing page - choose login type
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LSC SRMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="school-header">
            <h1>Lusaka South College</h1>
            <div class="subtitle">Student Records Management System</div>
        </div>
        <h2>Welcome to LSC SRMS</h2>
        <p>Please select your login type to access the system:</p>
        <div class="login-options">
            <a href="login.php">👨‍💼 Admin / Staff / Lecturer Login</a>
            <a href="student_login.php">🎓 Student Login</a>
            <a href="enroll.php" class="btn-orange">📝 Enroll Now</a>
        </div>
        
        <div class="mt-20">
            <p><small>Having login issues? <a href="database_setup_guide.php">📋 Database Setup Guide</a></small></p>
        </div>
    </div>
</body>
</html>
