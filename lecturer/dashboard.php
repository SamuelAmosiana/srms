<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in and has lecturer role
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

requireRole('Lecturer', $pdo);

// Get lecturer profile
$stmt = $pdo->prepare("SELECT sp.full_name, sp.staff_id, sp.qualification FROM staff_profile sp WHERE sp.user_id = ?");
$stmt->execute([currentUserId()]);
$lecturer = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard - LSC SRMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <h1>👨‍🏫 Lecturer Dashboard</h1>
            <p>Welcome, <?php echo htmlspecialchars($lecturer['full_name'] ?? 'Lecturer'); ?> (<?php echo htmlspecialchars($lecturer['staff_id'] ?? 'N/A'); ?>)</p>
            <p><strong>Qualification:</strong> <?php echo htmlspecialchars($lecturer['qualification'] ?? 'N/A'); ?></p>
            <p><strong>Lusaka South College - Academic Portal</strong></p>
        </div>
        
        <div class="dashboard-nav">
            <h3>📋 Academic Functions</h3>
            <a href="view_students.php">👥 View Students</a>
            <a href="upload_results.php">📊 Upload Results</a>
        </div>
        
        <div class="container">
            <h2>Academic Portal</h2>
            <p>Welcome to your lecturer dashboard. Here you can manage your courses and student results.</p>
            
            <div class="success">
                ✅ Login successful! Academic management features will be implemented in subsequent modules.
            </div>
            
            <div class="mt-20">
                <a href="../logout.php" class="btn btn-orange">🚪 Logout</a>
                <a href="../index.php" class="btn">🏠 Home</a>
            </div>
        </div>
    </div>
</body>
</html>