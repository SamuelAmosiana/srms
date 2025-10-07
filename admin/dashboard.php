<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in and has admin role
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

requireRole('Super Admin', $pdo);

// Get admin profile
$stmt = $pdo->prepare("SELECT ap.full_name, ap.staff_id FROM admin_profile ap WHERE ap.user_id = ?");
$stmt->execute([currentUserId()]);
$admin = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LSC SRMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <h1>🏛️ Admin Dashboard</h1>
            <p>Welcome, <?php echo htmlspecialchars($admin['full_name'] ?? 'Administrator'); ?> (<?php echo htmlspecialchars($admin['staff_id'] ?? 'N/A'); ?>)</p>
            <p><strong>Lusaka South College - Student Records Management System</strong></p>
        </div>
        
        <div class="dashboard-nav">
            <h3>📋 Administration Functions</h3>
            <a href="manage_users.php">👥 Manage Users</a>
            <a href="manage_roles.php">🔐 Manage Roles</a>
            <a href="manage_school.php">🏫 Manage Schools</a>
            <a href="manage_department.php">🏢 Manage Departments</a>
            <a href="manage_programme.php">📚 Manage Programmes</a>
            <a href="manage_course.php">📖 Manage Courses</a>
            <a href="upload_users.php">📤 Bulk Upload Users</a>
        </div>
        
        <div class="container">
            <h2>System Overview</h2>
            <p>This is your administrative control panel. From here you can manage all aspects of the Student Records Management System.</p>
            
            <div class="success">
                ✅ Login successful! All administration features will be implemented in subsequent modules.
            </div>
            
            <div class="mt-20">
                <a href="../logout.php" class="btn btn-orange">🚪 Logout</a>
                <a href="../index.php" class="btn">🏠 Home</a>
            </div>
        </div>
    </div>
</body>
</html>