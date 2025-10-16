<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in and has Sub Admin role
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

requireRole('Sub Admin (Finance)', $pdo);

// Get sub admin profile
$stmt = $pdo->prepare("SELECT ap.full_name, ap.staff_id FROM admin_profile ap WHERE ap.user_id = ?");
$stmt->execute([currentUserId()]);
$admin = $stmt->fetch();

// Handle report filtering
$report_type = $_GET['type'] ?? 'combined';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

if ($report_type === 'income') {
    $stmt = $pdo->prepare("SELECT type, description, amount, created_at as transaction_date FROM finance_transactions WHERE type = 'income' AND created_at BETWEEN ? AND ? ORDER BY created_at DESC");
    $stmt->execute([$start_date, $end_date]);
    $transactions = $stmt->fetchAll();
} elseif ($report_type === 'expense') {
    $stmt = $pdo->prepare("SELECT type, description, amount, created_at as transaction_date FROM finance_transactions WHERE type = 'expense' AND created_at BETWEEN ? AND ? ORDER BY created_at DESC");
    $stmt->execute([$start_date, $end_date]);
    $transactions = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT type, description, amount, created_at as transaction_date FROM finance_transactions WHERE created_at BETWEEN ? AND ? ORDER BY created_at DESC");
    $stmt->execute([$start_date, $end_date]);
    $transactions = $stmt->fetchAll();
}

// Calculate totals
$total_income = 0;
$total_expense = 0;
foreach ($transactions as $transaction) {
    if ($transaction['type'] === 'income') {
        $total_income += $transaction['amount'];
    } else {
        $total_expense += $transaction['amount'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Reports - LSC SRMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .top-nav, .sidebar, .form-container, .no-print { display: none; }
            .main-content { margin: 0; }
            .print-header { display: block; text-align: center; margin-bottom: 20px; }
        }
    </style>
</head>
<body class="admin-layout" data-theme="light">
    <!-- Top Navigation Bar -->
    <nav class="top-nav">
        <div class="nav-left">
            <div class="logo-container">
                <img src="../assets/images/lsc-logo.png" alt="LSC Logo" class="logo" onerror="this.style.display='none'">
                <span class="logo-text">LSC SRMS</span>
            </div>
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <div class="nav-right">
            <div class="user-info">
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($admin['full_name'] ?? 'Finance Administrator'); ?></span>
                <span class="staff-id">(<?php echo htmlspecialchars($admin['staff_id'] ?? 'N/A'); ?>)</span>
            </div>
            
            <div class="nav-actions">
                <button class="theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
                    <i class="fas fa-moon" id="theme-icon"></i>
                </button>
                
                <div class="dropdown">
                    <button class="profile-btn" onclick="toggleDropdown()">
                        <i class="fas fa-user-circle"></i>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="profileDropdown">
                        <a href="profile.php"><i class="fas fa-user"></i> View Profile</a>
                        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                        <div class="dropdown-divider"></div>
                        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-tachometer-alt"></i> Finance Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Student Management</h4>
                <a href="view_students.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>View Students</span>
                </a>
                <a href="manage_fees.php" class="nav-item">
                    <i class="fas fa-money-bill"></i>
                    <span>Manage Fees & Finances</span>
                </a>
                <a href="results_access.php" class="nav-item">
                    <i class="fas fa-lock"></i>
                    <span>Manage Results Access</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Financial Operations</h4>
                <a href="income_expenses.php" class="nav-item">
                    <i class="fas fa-chart-pie"></i>
                    <span>Income & Expenses</span>
                </a>
                <a href="finance_reports.php" class="nav-item active">
                    <i class="fas fa-file-invoice"></i>
                    <span>Finance Reports</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-file-invoice"></i> Finance Reports</h1>
            <p>Generate and print financial reports for income and expenses</p>
        </div>
        
        <!-- Report Filter Form -->
        <div class="form-container no-print">
            <h2>Filter Report</h2>
            <form method="GET" action="finance_reports.php">
                <div class="form-group">
                    <label for="report_type">Report Type</label>
                    <select name="type" id="report_type">
                        <option value="combined" <?php echo $report_type === 'combined' ? 'selected' : ''; ?>>Combined</option>
                        <option value="income" <?php echo $report_type === 'income' ? 'selected' : ''; ?>>Income</option>
                        <option value="expense" <?php echo $report_type === 'expense' ? 'selected' : ''; ?>>Expense</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>" required>
                </div>
                <div class="form-group">
                    <label for="end_date">End Date</label>
                    <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn green">Generate Report</button>
                    <button type="button" class="btn orange" onclick="window.print()">Print Report</button>
                </div>
            </form>
        </div>
        
        <!-- Report Header for Printing -->
        <div class="print-header" style="display: none;">
            <h2>Lusaka South College SRMS</h2>
            <h3><?php echo ucfirst($report_type); ?> Report</h3>
            <p>Period: <?php echo htmlspecialchars($start_date); ?> to <?php echo htmlspecialchars($end_date); ?></p>
            <p>Generated by: <?php echo htmlspecialchars($admin['full_name'] ?? 'Finance Administrator'); ?> (<?php echo htmlspecialchars($admin['staff_id'] ?? 'N/A'); ?>)</p>
            <p>Date Generated: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
        
        <!-- Transactions Table -->
        <div class="table-container">
            <h2><?php echo ucfirst($report_type); ?> Transactions</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Amount (K)</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="4">No transactions found for the selected period.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td>
                                    <span class="status <?php echo $transaction['type'] === 'income' ? 'green' : 'orange'; ?>">
                                        <?php echo htmlspecialchars($transaction['type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                <td><?php echo number_format($transaction['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($transaction['transaction_date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2"><strong>Total Income:</strong></td>
                        <td><strong>K<?php echo number_format($total_income, 2); ?></strong></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="2"><strong>Total Expenses:</strong></td>
                        <td><strong>K<?php echo number_format($total_expense, 2); ?></strong></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="2"><strong>Net Balance:</strong></td>
                        <td><strong>K<?php echo number_format($total_income - $total_expense, 2); ?></strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
</body>
</html>