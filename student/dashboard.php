<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in and has student role
if (!currentUserId()) {
    header('Location: ../student_login.php');
    exit;
}

// Get student profile
$stmt = $pdo->prepare("SELECT sp.full_name, sp.student_number, sp.NRC, sp.gender, sp.balance FROM student_profile sp WHERE sp.user_id = ?");
$stmt->execute([currentUserId()]);
$student = $stmt->fetch();

if (!$student) {
    header('Location: ../student_login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - LSC SRMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <h1>🎓 Student Dashboard</h1>
            <p>Welcome, <?php echo htmlspecialchars($student['full_name'] ?? 'Student'); ?></p>
            <p><strong>Student Number:</strong> <?php echo htmlspecialchars($student['student_number'] ?? 'N/A'); ?></p>
            <p><strong>Account Balance:</strong> K<?php echo number_format($student['balance'] ?? 0, 2); ?></p>
            <p><strong>Lusaka South College - Student Portal</strong></p>
        </div>
        
        <div class="dashboard-nav">
            <h3>📋 Student Services</h3>
            <a href="register_courses.php">📖 Register Courses</a>
            <a href="view_results.php">📊 View Results</a>
            <a href="view_balance.php">💰 View Balance</a>
            <a href="view_docket.php">📄 Academic Docket</a>
            <a href="accommodation.php">🏠 Accommodation</a>
        </div>
        
        <div class="container">
            <h2>Student Information</h2>
            <div class="p-20">
                <p><strong>Full Name:</strong> <?php echo htmlspecialchars($student['full_name']); ?></p>
                <p><strong>Student Number:</strong> <?php echo htmlspecialchars($student['student_number']); ?></p>
                <p><strong>NRC:</strong> <?php echo htmlspecialchars($student['NRC'] ?? 'Not provided'); ?></p>
                <p><strong>Gender:</strong> <?php echo htmlspecialchars($student['gender'] ?? 'Not specified'); ?></p>
                <p><strong>Current Balance:</strong> K<?php echo number_format($student['balance'], 2); ?></p>
            </div>
            
            <div class="success">
                ✅ Login successful! Student services will be implemented in subsequent modules.
            </div>
            
            <div class="mt-20">
                <a href="../logout.php" class="btn btn-orange">🚪 Logout</a>
                <a href="../index.php" class="btn">🏠 Home</a>
            </div>
        </div>
    </div>
</body>
</html>