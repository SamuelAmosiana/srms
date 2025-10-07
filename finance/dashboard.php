<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in and has finance role
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

requireRole('Sub Admin (Finance)', $pdo);

// Get finance profile
$stmt = $pdo->prepare("SELECT sp.full_name, sp.staff_id, sp.qualification FROM staff_profile sp WHERE sp.user_id = ?");
$stmt->execute([currentUserId()]);
$finance = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Dashboard - LSC SRMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <div class="dashboard-header">
            <h1>💰 Finance Dashboard</h1>
            <p>Welcome, <?php echo htmlspecialchars($finance['full_name'] ?? 'Finance Officer'); ?> (<?php echo htmlspecialchars($finance['staff_id'] ?? 'N/A'); ?>)</p>
            <p><strong>Department:</strong> Finance & Administration</p>
            <p><strong>Lusaka South College - Finance Portal</strong></p>
        </div>
        
        <div class="dashboard-nav">
            <h3>📋 Financial Functions</h3>
            <a href="manage_transactions.php">💳 Manage Transactions</a>
            <a href="view_balances.php">📈 View Student Balances</a>
            <a href="generate_reports.php">📄 Generate Reports</a>
        </div>
        
        <div class="container">
            <h2>Financial Management Portal</h2>
            <p>Welcome to your finance dashboard. Here you can manage student transactions and generate financial reports.</p>
            
            <div class="success">
                ✅ Login successful! Financial management features will be implemented in subsequent modules.
            </div>
            
            <div class="mt-20">
                <a href="../logout.php" class="btn btn-orange">🚪 Logout</a>
                <a href="../index.php" class="btn">🏠 Home</a>
            </div>
        </div>
    </div>
</body>
</html>