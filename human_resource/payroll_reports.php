<?php
require '../config.php';
require '../auth/auth.php';

// Check if user is logged in
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

// Check if user has HR Manager role
$stmt = $pdo->prepare("
    SELECT r.name 
    FROM users u 
    JOIN user_roles ur ON u.id = ur.user_id 
    JOIN roles r ON ur.role_id = r.id 
    WHERE u.id = ? AND r.name = 'HR Manager'
");
$stmt->execute([currentUserId()]);
$hrRole = $stmt->fetch();

if (!$hrRole) {
    header('Location: ../unauthorized.php');
    exit;
}

// Get HR profile
$stmt = $pdo->prepare("SELECT ap.full_name, ap.staff_id FROM admin_profile ap WHERE ap.user_id = ?");
$stmt->execute([currentUserId()]);
$hr = $stmt->fetch();

// Fetch payroll report statistics
$stats = [
    'total_payroll' => 0,
    'total_tax' => 0,
    'total_deductions' => 0,
    'total_employees_paid' => 0,
    'pending_payroll' => 0,
    'monthly_expenses' => 0,
    'average_salary' => 0,
    'highest_salary' => 0,
    'lowest_salary' => 0
];

try {
    // Get total payroll (current month)
    $current_month = date('Y-m');
    $stmt = $pdo->query("SELECT SUM(net_salary) as total FROM payroll WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$current_month'");
    $stats['total_payroll'] = $stmt->fetch()['total'] ?? 0;
    
    // Get total tax (current month)
    $stmt = $pdo->query("SELECT SUM(tax_amount) as total FROM payroll WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$current_month'");
    $stats['total_tax'] = $stmt->fetch()['total'] ?? 0;
    
    // Get total deductions (current month)
    $stmt = $pdo->query("SELECT SUM(deductions) as total FROM payroll WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$current_month'");
    $stats['total_deductions'] = $stmt->fetch()['total'] ?? 0;
    
    // Get total employees paid (current month)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM payroll WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$current_month'");
    $stats['total_employees_paid'] = $stmt->fetch()['count'];
    
    // Get pending payroll
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM payroll WHERE status = 'pending'");
    $stats['pending_payroll'] = $stmt->fetch()['count'];
    
    // Get monthly expenses (current month)
    $stmt = $pdo->query("SELECT SUM(basic_salary + allowances) as total FROM payroll WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$current_month'");
    $stats['monthly_expenses'] = $stmt->fetch()['total'] ?? 0;
    
    // Get average salary (current month)
    $stmt = $pdo->query("SELECT AVG(net_salary) as avg_salary FROM payroll WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$current_month'");
    $stats['average_salary'] = $stmt->fetch()['avg_salary'] ?? 0;
    
    // Get highest salary (current month)
    $stmt = $pdo->query("SELECT MAX(net_salary) as max_salary FROM payroll WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$current_month'");
    $stats['highest_salary'] = $stmt->fetch()['max_salary'] ?? 0;
    
    // Get lowest salary (current month)
    $stmt = $pdo->query("SELECT MIN(net_salary) as min_salary FROM payroll WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$current_month'");
    $stats['lowest_salary'] = $stmt->fetch()['min_salary'] ?? 0;
} catch (Exception $e) {
    // Handle error gracefully
    error_log("Error fetching payroll statistics: " . $e->getMessage());
}

// Get recent payroll records
$stmt = $pdo->query("SELECT p.*, CONCAT(e.first_name, ' ', e.last_name) AS full_name FROM payroll p JOIN employees e ON p.employee_id = e.id ORDER BY p.payment_date DESC LIMIT 10");
$recent_payroll = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Reports - HR</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .payroll-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .payroll-card {
            background: var(--white);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px var(--shadow);
            display: flex;
            align-items: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .payroll-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 16px var(--shadow);
        }
        
        .payroll-icon {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
            color: white;
        }
        
        .payroll-content h3 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-dark);
        }
        
        .payroll-content p {
            color: var(--text-light);
            margin: 0;
            font-size: 14px;
        }
        
        .bg-blue { background-color: #4285f4; }
        .bg-green { background-color: #34a853; }
        .bg-orange { background-color: #fbbc05; }
        .bg-purple { background-color: #673ab7; }
        .bg-red { background-color: #ea4335; }
        .bg-teal { background-color: #00897b; }
        .bg-pink { background-color: #e91e63; }
        .bg-indigo { background-color: #3f51b5; }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .payroll-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px var(--shadow);
        }
        
        .payroll-table th,
        .payroll-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .payroll-table th {
            background-color: var(--primary-green);
            color: white;
            font-weight: 600;
        }
        
        .payroll-table tbody tr:hover {
            background-color: rgba(34, 139, 34, 0.05);
        }
        
        .payroll-table .status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-paid {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-failed {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .section-title {
            color: var(--primary-green);
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-green);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-light);
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ccc;
        }
        
        .chart-container {
            background: var(--white);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px var(--shadow);
            margin-bottom: 30px;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .chart-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .chart-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-decoration: none;
            text-align: center;
            color: white;
        }
        
        .btn-primary {
            background-color: var(--primary-green);
        }
        
        .btn-primary:hover {
            background-color: var(--dark-green);
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .btn-success {
            background-color: #28a745;
        }
        
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .btn-danger {
            background-color: #dc3545;
        }
        
        .report-section {
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 8px var(--shadow);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .section-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            background-color: var(--primary-green);
            color: white;
        }
        
        .section-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 500;
        }
        
        .section-body {
            padding: 20px;
        }
        
        .report-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .form-control {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .payroll-cards {
                grid-template-columns: 1fr;
            }
            
            .payroll-table {
                font-size: 14px;
            }
            
            .payroll-table th,
            .payroll-table td {
                padding: 8px 10px;
            }
            
            .report-actions {
                flex-direction: column;
            }
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
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($hr['full_name'] ?? 'HR Manager'); ?></span>
                <span class="staff-id">(<?php echo htmlspecialchars($hr['staff_id'] ?? 'N/A'); ?>)</span>
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
            <h3><i class="fas fa-users"></i> HR Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Employee Management</h4>
                <a href="employees.php" class="nav-item">
                    <i class="fas fa-user-tie"></i>
                    <span>Employee Directory</span>
                </a>
                <a href="departments.php" class="nav-item">
                    <i class="fas fa-building"></i>
                    <span>Departments</span>
                </a>
                <a href="add_employee.php" class="nav-item">
                    <i class="fas fa-user-plus"></i>
                    <span>Add Employee</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Payroll Management</h4>
                <a href="payroll.php" class="nav-item">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Payroll Processing</span>
                </a>
                <a href="tax_brackets.php" class="nav-item">
                    <i class="fas fa-percent"></i>
                    <span>Tax Brackets</span>
                </a>
                <a href="payroll_reports.php" class="nav-item active">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Payroll Reports</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Reports</h4>
                <a href="hr_reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>HR Reports</span>
                </a>
                <a href="analytics.php" class="nav-item">
                    <i class="fas fa-chart-line"></i>
                    <span>HR Analytics</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-file-invoice-dollar"></i> Payroll Reports</h1>
            <p>Generate and view comprehensive payroll reports</p>
        </div>
        
        <div class="report-actions">
            <select class="form-control" style="width: auto; min-width: 150px;">
                <option>Last 30 Days</option>
                <option>Last 60 Days</option>
                <option>Last 90 Days</option>
                <option>Last Year</option>
                <option>Custom Range</option>
            </select>
            
            <select class="form-control" style="width: auto; min-width: 150px;">
                <option>All Departments</option>
                <?php
                $stmt = $pdo->query("SELECT * FROM department ORDER BY name");
                $departments = $stmt->fetchAll();
                foreach ($departments as $dept):
                ?>
                    <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                <?php endforeach; ?>
            </select>
            
            <a href="#" class="btn btn-primary">
                <i class="fas fa-file-pdf"></i> Export PDF
            </a>
            
            <a href="#" class="btn btn-secondary">
                <i class="fas fa-file-excel"></i> Export Excel
            </a>
        </div>
        
        <div class="payroll-cards">
            <div class="payroll-card">
                <div class="payroll-icon bg-blue">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="payroll-content">
                    <h3>$<?php echo number_format($stats['total_payroll'], 2); ?></h3>
                    <p>Total Payroll</p>
                    <div class="metric-label">This month</div>
                </div>
            </div>
            
            <div class="payroll-card">
                <div class="payroll-icon bg-green">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="payroll-content">
                    <h3>$<?php echo number_format($stats['total_tax'], 2); ?></h3>
                    <p>Total Tax</p>
                    <div class="metric-label">This month</div>
                </div>
            </div>
            
            <div class="payroll-card">
                <div class="payroll-icon bg-orange">
                    <i class="fas fa-minus-circle"></i>
                </div>
                <div class="payroll-content">
                    <h3>$<?php echo number_format($stats['total_deductions'], 2); ?></h3>
                    <p>Total Deductions</p>
                    <div class="metric-label">This month</div>
                </div>
            </div>
            
            <div class="payroll-card">
                <div class="payroll-icon bg-purple">
                    <i class="fas fa-users"></i>
                </div>
                <div class="payroll-content">
                    <h3><?php echo number_format($stats['total_employees_paid']); ?></h3>
                    <p>Employees Paid</p>
                    <div class="metric-label">This month</div>
                </div>
            </div>
        </div>
        
        <div class="payroll-cards">
            <div class="payroll-card">
                <div class="payroll-icon bg-red">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="payroll-content">
                    <h3><?php echo number_format($stats['pending_payroll']); ?></h3>
                    <p>Pending Payroll</p>
                    <div class="metric-label">Awaiting processing</div>
                </div>
            </div>
            
            <div class="payroll-card">
                <div class="payroll-icon bg-teal">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="payroll-content">
                    <h3>$<?php echo number_format($stats['average_salary'], 2); ?></h3>
                    <p>Avg Salary</p>
                    <div class="metric-label">This month</div>
                </div>
            </div>
            
            <div class="payroll-card">
                <div class="payroll-icon bg-pink">
                    <i class="fas fa-arrow-up"></i>
                </div>
                <div class="payroll-content">
                    <h3>$<?php echo number_format($stats['highest_salary'], 2); ?></h3>
                    <p>Highest Salary</p>
                    <div class="metric-label">This month</div>
                </div>
            </div>
            
            <div class="payroll-card">
                <div class="payroll-icon bg-indigo">
                    <i class="fas fa-arrow-down"></i>
                </div>
                <div class="payroll-content">
                    <h3>$<?php echo number_format($stats['lowest_salary'], 2); ?></h3>
                    <p>Lowest Salary</p>
                    <div class="metric-label">This month</div>
                </div>
            </div>
        </div>
        
        <div class="report-section">
            <div class="section-header">
                <h2><i class="fas fa-history"></i> Recent Payroll Records</h2>
            </div>
            <div class="section-body">
                <?php if (count($recent_payroll) > 0): ?>
                    <div class="table-responsive">
                        <table class="payroll-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Basic Salary</th>
                                    <th>Allowances</th>
                                    <th>Tax</th>
                                    <th>Deductions</th>
                                    <th>Net Salary</th>
                                    <th>Payment Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_payroll as $payroll): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payroll['full_name']); ?></td>
                                        <td>$<?php echo number_format($payroll['basic_salary'], 2); ?></td>
                                        <td>$<?php echo number_format($payroll['allowances'], 2); ?></td>
                                        <td>$<?php echo number_format($payroll['tax_amount'], 2); ?></td>
                                        <td>$<?php echo number_format($payroll['deductions'], 2); ?></td>
                                        <td><strong>$<?php echo number_format($payroll['net_salary'], 2); ?></strong></td>
                                        <td><?php echo date('M j, Y', strtotime($payroll['payment_date'])); ?></td>
                                        <td><span class="status status-<?php echo $payroll['status']; ?>"><?php echo ucfirst($payroll['status']); ?></span></td>
                                        <td>
                                            <a href="#" class="btn btn-sm btn-primary">View</a>
                                            <a href="#" class="btn btn-sm btn-secondary">Print</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <h3>No Payroll Records Found</h3>
                        <p>There are no payroll records in the system.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="chart-container">
            <div class="chart-header">
                <div class="chart-title">Payroll Distribution by Department</div>
                <div class="chart-actions">
                    <select class="form-control" style="width: auto; padding: 5px 10px; font-size: 14px;">
                        <option>Last 30 Days</option>
                        <option>Last 60 Days</option>
                        <option>Last 90 Days</option>
                        <option>Last Year</option>
                    </select>
                </div>
            </div>
            <div style="height: 300px; display: flex; align-items: center; justify-content: center; background-color: #f8f9fa; border-radius: 8px;">
                <div style="text-align: center; color: #6c757d;">
                    <i class="fas fa-chart-pie" style="font-size: 48px; margin-bottom: 15px;"></i>
                    <h3>Payroll Distribution</h3>
                    <p>Visual representation of payroll expenses by department</p>
                </div>
            </div>
        </div>
        
        <div class="chart-container">
            <div class="chart-header">
                <div class="chart-title">Monthly Payroll Trends</div>
                <div class="chart-actions">
                    <select class="form-control" style="width: auto; padding: 5px 10px; font-size: 14px;">
                        <option>Current Year</option>
                        <option>Previous Year</option>
                        <option>Custom Range</option>
                    </select>
                </div>
            </div>
            <div style="height: 300px; display: flex; align-items: center; justify-content: center; background-color: #f8f9fa; border-radius: 8px;">
                <div style="text-align: center; color: #6c757d;">
                    <i class="fas fa-chart-line" style="font-size: 48px; margin-bottom: 15px;"></i>
                    <h3>Payroll Trends</h3>
                    <p>Monthly payroll expenses trend analysis</p>
                </div>
            </div>
        </div>
        
        <div class="report-section">
            <div class="section-header">
                <h2><i class="fas fa-file-download"></i> Download Reports</h2>
            </div>
            <div class="section-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div style="border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; background: var(--white);">
                        <h3><i class="fas fa-file-pdf"></i> Monthly Payroll Summary</h3>
                        <p>Detailed summary of payroll for the selected period</p>
                        <a href="#" class="btn btn-primary">Download PDF</a>
                        <a href="#" class="btn btn-secondary">Download Excel</a>
                    </div>
                    
                    <div style="border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; background: var(--white);">
                        <h3><i class="fas fa-file-pdf"></i> Tax Report</h3>
                        <p>Comprehensive report of tax calculations and deductions</p>
                        <a href="#" class="btn btn-primary">Download PDF</a>
                        <a href="#" class="btn btn-secondary">Download Excel</a>
                    </div>
                    
                    <div style="border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; background: var(--white);">
                        <h3><i class="fas fa-file-pdf"></i> Department Payroll Report</h3>
                        <p>Payroll breakdown by department</p>
                        <a href="#" class="btn btn-primary">Download PDF</a>
                        <a href="#" class="btn btn-secondary">Download Excel</a>
                    </div>
                    
                    <div style="border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; background: var(--white);">
                        <h3><i class="fas fa-file-pdf"></i> Yearly Payroll Report</h3>
                        <p>Annual payroll summary and analysis</p>
                        <a href="#" class="btn btn-primary">Download PDF</a>
                        <a href="#" class="btn btn-secondary">Download Excel</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Toggle theme function
        function toggleTheme() {
            const body = document.body;
            const themeIcon = document.getElementById('theme-icon');
            
            if (body.getAttribute('data-theme') === 'light') {
                body.setAttribute('data-theme', 'dark');
                themeIcon.className = 'fas fa-sun';
                localStorage.setItem('theme', 'dark');
            } else {
                body.setAttribute('data-theme', 'light');
                themeIcon.className = 'fas fa-moon';
                localStorage.setItem('theme', 'light');
            }
        }
        
        // Toggle sidebar function
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('collapsed');
        }
        
        // Toggle dropdown function
        function toggleDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
        }
        
        // Close dropdown when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('.profile-btn') && !event.target.closest('.dropdown')) {
                const dropdowns = document.getElementsByClassName('dropdown-menu');
                for (let i = 0; i < dropdowns.length; i++) {
                    dropdowns[i].classList.remove('show');
                }
            }
        }
        
        // Load saved theme preference
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            const themeIcon = document.getElementById('theme-icon');
            
            document.body.setAttribute('data-theme', savedTheme);
            themeIcon.className = savedTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        });
    </script>
</body>
</html>