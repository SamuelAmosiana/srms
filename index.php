<?php
// Landing page - choose login type
echo "<!-- Test auto-deployment: " . date("Y-m-d H:i:s") . " -->";
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
            <div class="subtitle">Dream, Explore, Acquire</div>
        </div>
        <h2>Student and Staff Portal</h2>
        <p>Please select your login type to access the system:</p>
        <div class="login-options">
            <a href="login.php">👨‍💼 Staff Login</a>
            <a href="student_login.php">🎓 Student Login</a>
            <a href="enroll.php" class="btn-orange">📝 Enroll Now</a>
        </div>
        
        <!-- <div class="mt-20">
          <p><small>Having login issues? <a href="database_setup_guide.php">📋 Database Setup Guide</a></small></p>
        </div>
    </div>-->
</body>
</html>
