<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in and has sub admin role
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

requireRole('Sub Admin (Finance)', $pdo);

// Create necessary finance tables if they don't exist - MUST BE BEFORE ANY QUERIES
try {
    // Create payments table without foreign key constraints initially
    $table_check = $pdo->query("SHOW TABLES LIKE 'payments'");
    if ($table_check->rowCount() == 0) {
        $pdo->exec("CREATE TABLE payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_date DATE NOT NULL,
            payment_method VARCHAR(50) NOT NULL DEFAULT 'Cash',
            reference_number VARCHAR(100),
            status ENUM('paid', 'pending', 'failed') DEFAULT 'paid',
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        // Insert sample data
        $pdo->exec("INSERT INTO payments (student_id, amount, payment_date, payment_method, reference_number, status, description) VALUES 
            (1, 1500.00, '2024-01-15', 'Bank Transfer', 'TXN001', 'paid', 'Tuition Fee Payment'),
            (2, 2000.00, '2024-01-20', 'Cash', 'CASH002', 'paid', 'Full Semester Fee'),
            (3, 750.00, '2024-02-01', 'Mobile Money', 'MM003', 'paid', 'Partial Payment')");
    }
    
    // Create student_fees table without foreign key constraints
    $table_check = $pdo->query("SHOW TABLES LIKE 'student_fees'");
    if ($table_check->rowCount() == 0) {
        $pdo->exec("CREATE TABLE student_fees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            fee_type VARCHAR(100) NOT NULL,
            amount_due DECIMAL(10,2) NOT NULL,
            amount_paid DECIMAL(10,2) DEFAULT 0,
            balance DECIMAL(10,2) AS (amount_due - amount_paid) STORED,
            due_date DATE,
            academic_year VARCHAR(20),
            semester VARCHAR(20),
            status ENUM('pending', 'partial', 'paid', 'overdue') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        // Insert sample data
        $pdo->exec("INSERT INTO student_fees (student_id, fee_type, amount_due, amount_paid, due_date, academic_year, semester, status) VALUES 
            (1, 'Tuition Fee', 3000.00, 1500.00, '2024-03-31', '2024', 'Semester 1', 'partial'),
            (2, 'Tuition Fee', 3000.00, 2000.00, '2024-03-31', '2024', 'Semester 1', 'partial'),
            (3, 'Library Fee', 200.00, 0.00, '2024-02-28', '2024', 'Semester 1', 'pending'),
            (4, 'Laboratory Fee', 500.00, 500.00, '2024-01-31', '2024', 'Semester 1', 'paid')");
    }
    
    // Create expenses table
    $table_check = $pdo->query("SHOW TABLES LIKE 'expenses'");
    if ($table_check->rowCount() == 0) {
        $pdo->exec("CREATE TABLE expenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(100) NOT NULL,
            description TEXT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            expense_date DATE NOT NULL,
            payment_method VARCHAR(50) NOT NULL DEFAULT 'Cash',
            receipt_number VARCHAR(100),
            approved_by INT,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        // Insert sample data
        $pdo->exec("INSERT INTO expenses (category, description, amount, expense_date, payment_method, receipt_number, status) VALUES 
            ('Office Supplies', 'Stationery and printing materials', 150.00, '2024-01-10', 'Cash', 'RCP001', 'approved'),
            ('Utilities', 'Electricity bill for January', 800.00, '2024-01-31', 'Bank Transfer', 'ELEC002', 'approved'),
            ('Maintenance', 'Computer lab equipment repair', 1200.00, '2024-02-05', 'Cheque', 'CHQ003', 'approved'),
            ('Transportation', 'Staff transport allowance', 300.00, '2024-02-01', 'Cash', 'TRANS004', 'approved')");
    }
    
} catch (PDOException $e) {
    echo "<div style='color: red; padding: 10px; margin: 10px; border: 1px solid red; background: #ffe6e6;'>";
    echo "<strong>Database Error:</strong> " . $e->getMessage() . "<br>";
    echo "<small>Tables will be created without sample data. Please check your database configuration.</small>";
    echo "</div>";
    error_log("Error creating finance tables: " . $e->getMessage());
}

// Get sub admin profile
$stmt = $pdo->prepare("SELECT ap.full_name, ap.staff_id FROM admin_profile ap WHERE ap.user_id = ?");
$stmt->execute([currentUserId()]);
$admin = $stmt->fetch();

// Get dashboard statistics relevant to finance
$stats = [];

// Count total students
$stmt = $pdo->query("SELECT COUNT(*) as count FROM student_profile");
$stats['total_students'] = $stmt->fetch()['count'];

// Total fees collected (assuming a payments table exists; adjust query as needed)
$stmt = $pdo->query("SELECT SUM(amount) as total FROM payments WHERE status = 'paid'");
$stats['total_fees_collected'] = $stmt->fetch()['total'] ?? 0;

// Total outstanding fees (assuming a fees table; adjust query as needed)
$stmt = $pdo->query("SELECT SUM(balance) as total FROM student_fees");
$stats['total_outstanding'] = $stmt->fetch()['total'] ?? 0;

// Total expenses (assuming an expenses table; adjust query as needed)
$stmt = $pdo->query("SELECT SUM(amount) as total FROM expenses");
$stats['total_expenses'] = $stmt->fetch()['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Dashboard - LSC SRMS</title>
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
                <a href="manage_programme_fees.php" class="nav-link" title="Programme Fees">
                    <i class="fas fa-file-invoice-dollar"></i>
                </a>
                
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
            <a href="dashboard.php" class="nav-item active">
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
                <a href="manage_programme_fees.php" class="nav-item">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Programme Fees</span>
                </a>
                <a href="results_access.php" class="nav-item">
                    <i class="fas fa-lock"></i>
                    <span>Manage Results Access</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Financial Operations</h4>
                <a href="manage_programme_fees.php" class="nav-item">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Programme Fees</span>
                </a>
                <a href="income_expenses.php" class="nav-item">
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
            <h1><i class="fas fa-tachometer-alt"></i> Finance Dashboard Overview</h1>
            <p>Welcome to the Lusaka South College Student Records Management System - Finance Module</p>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total_students']); ?></h3>
                    <p>Total Students</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-money-check"></i>
                </div>
                <div class="stat-info">
                    <h3>K<?php echo number_format($stats['total_fees_collected'], 2); ?></h3>
                    <p>Fees Collected</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-info">
                    <h3>K<?php echo number_format($stats['total_outstanding'], 2); ?></h3>
                    <p>Outstanding Fees</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3>K<?php echo number_format($stats['total_expenses'], 2); ?></h3>
                    <p>Total Expenses</p>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
            <div class="actions-grid">
                <a href="manage_fees.php" class="action-card orange">
                    <i class="fas fa-money-bill"></i>
                    <h3>Manage Student Fees</h3>
                    <p>Update fees, payments, and balances</p>
                </a>
                
                <a href="results_access.php" class="action-card green">
                    <i class="fas fa-lock-open"></i>
                    <h3>Manage Results Access</h3>
                    <p>Control results view based on balances</p>
                </a>
                
                <a href="income_expenses.php" class="action-card orange">
                    <i class="fas fa-chart-pie"></i>
                    <h3>Manage Income/Expenses</h3>
                    <p>Record and track financial transactions</p>
                </a>
                
                <a href="finance_reports.php" class="action-card green">
                    <i class="fas fa-print"></i>
                    <h3>Generate Reports</h3>
                    <p>Create and print finance reports</p>
                </a>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="recent-activity">
            <h2><i class="fas fa-clock"></i> Recent Activity</h2>
            <div class="activity-list">
                <div class="activity-item">
                    <div class="activity-icon green">
                        <i class="fas fa-money-check"></i>
                    </div>
                    <div class="activity-content">
                        <h4>Payment received</h4>
                        <p>Student fee payment processed</p>
                        <span class="activity-time">1 hour ago</span>
                    </div>
                </div>
                
                <div class="activity-item">
                    <div class="activity-icon orange">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="activity-content">
                        <h4>Report generated</h4>
                        <p>Monthly finance report created</p>
                        <span class="activity-time">3 hours ago</span>
                    </div>
                </div>
                
                <div class="activity-item">
                    <div class="activity-icon green">
                        <i class="fas fa-lock-open"></i>
                    </div>
                    <div class="activity-content">
                        <h4>Results access granted</h4>
                        <p>Access approved for 3 students</p>
                        <span class="activity-time">5 hours ago</span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
</body>
</html>