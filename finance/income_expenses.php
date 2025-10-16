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

// Handle income submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_income'])) {
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $income_date = $_POST['income_date'];
    
    // Insert income record into finance_transactions table
    $stmt = $pdo->prepare("INSERT INTO finance_transactions (type, amount, description, created_at) VALUES ('income', ?, ?, ?)");
    $stmt->execute([$amount, $description, $income_date]);
    
    $success_message = "Income recorded successfully!";
}

// Handle expense submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_expense'])) {
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $expense_date = $_POST['expense_date'];
    
    // Insert expense record into finance_transactions table
    $stmt = $pdo->prepare("INSERT INTO finance_transactions (type, amount, description, created_at) VALUES ('expense', ?, ?, ?)");
    $stmt->execute([$amount, $description, $expense_date]);
    
    $success_message = "Expense recorded successfully!";
}

// Fetch recent transactions (income and expenses combined)
$stmt = $pdo->query("SELECT type, amount, description, created_at as transaction_date FROM finance_transactions ORDER BY created_at DESC LIMIT 10");
$transactions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Income & Expenses - LSC SRMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                <a href="income_expenses.php" class="nav-item active">
                    <i class="fas fa-chart-pie"></i>
                    <span>Income & Expenses</span>
                </a>
                <a href="finance_reports.php" class="nav-item">
                    <i class="fas fa-file-invoice"></i>
                    <span>Finance Reports</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-chart-pie"></i> Manage Income & Expenses</h1>
            <p>Record and track financial transactions for income and expenses</p>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Income Form -->
        <div class="form-container">
            <h2>Record Income</h2>
            <form method="POST" action="income_expenses.php">
                <div class="form-group">
                    <label for="income_description">Description</label>
                    <input type="text" name="description" id="income_description" required placeholder="e.g., Student Fee Payment">
                </div>
                <div class="form-group">
                    <label for="income_amount">Amount (K)</label>
                    <input type="number" name="amount" id="income_amount" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="income_date">Date</label>
                    <input type="date" name="income_date" id="income_date" required>
                </div>
                <div class="form-actions">
                    <button type="submit" name="record_income" class="btn green">Record Income</button>
                </div>
            </form>
        </div>
        
        <!-- Expense Form -->
        <div class="form-container">
            <h2>Record Expense</h2>
            <form method="POST" action="income_expenses.php">
                <div class="form-group">
                    <label for="expense_description">Description</label>
                    <input type="text" name="description" id="expense_description" required placeholder="e.g., Office Supplies">
                </div>
                <div class="form-group">
                    <label for="expense_amount">Amount (K)</label>
                    <input type="number" name="amount" id="expense_amount" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="expense_date">Date</label>
                    <input type="date" name="expense_date" id="expense_date" required>
                </div>
                <div class="form-actions">
                    <button type="submit" name="record_expense" class="btn orange">Record Expense</button>
                </div>
            </form>
        </div>
        
        <!-- Recent Transactions Table -->
        <div class="table-container">
            <h2>Recent Transactions</h2>
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
                    <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td>
                                <span class="status <?php echo $transaction['type'] === 'Income' ? 'green' : 'orange'; ?>">
                                    <?php echo htmlspecialchars($transaction['type']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                            <td><?php echo number_format($transaction['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($transaction['transaction_date']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
</body>
</html>