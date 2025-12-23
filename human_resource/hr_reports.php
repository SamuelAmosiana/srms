<?php
require '../config.php';
require '../auth.php';

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

// Fetch HR report statistics
$stats = [
    'total_employees' => 0,
    'active_employees' => 0,
    'departments' => 0,
    'pending_payroll' => 0,
    'monthly_payroll' => 0,
    'turnover_rate' => 0,
    'average_tenure' => 0,
    'new_hires' => 0
];

try {
    // Get total employees
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees");
    $stats['total_employees'] = $stmt->fetch()['count'];
    
    // Get active employees
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
    $stats['active_employees'] = $stmt->fetch()['count'];
    
    // Get departments
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM department");
    $stats['departments'] = $stmt->fetch()['count'];
    
    // Get pending payroll
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM payroll WHERE status = 'pending'");
    $stats['pending_payroll'] = $stmt->fetch()['count'];
    
    // Get monthly payroll (current month)
    $current_month = date('Y-m');
    $stmt = $pdo->query("SELECT SUM(net_salary) as total FROM payroll WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$current_month'");
    $stats['monthly_payroll'] = $stmt->fetch()['total'] ?? 0;
    
    // Get turnover rate (terminated employees in last 12 months)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees WHERE status = 'terminated' AND updated_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)");
    $terminated_count = $stmt->fetch()['count'];
    $stats['turnover_rate'] = $stats['total_employees'] > 0 ? round(($terminated_count / $stats['total_employees']) * 100, 2) : 0;
    
    // Get average tenure
    $stmt = $pdo->query("SELECT AVG(DATEDIFF(NOW(), hire_date)) as avg_days FROM employees WHERE status = 'active'");
    $avg_days = $stmt->fetch()['avg_days'];
    $stats['average_tenure'] = $avg_days ? round($avg_days / 365, 1) : 0;
    
    // Get new hires this month
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees WHERE hire_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
    $stats['new_hires'] = $stmt->fetch()['count'];
} catch (Exception $e) {
    // Handle error gracefully
    error_log("Error fetching HR statistics: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Reports - HR</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hr-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .hr-card {
            background: var(--White);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px var(--shadow);
            display: flex;
            align-items: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .hr-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 16px var(--shadow);
        }
        
        .hr-icon {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 24px;
            color: white;
        }
        
        .hr-content h3 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-dark);
        }
        
        .hr-content p {
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
        
        .report-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .report-item {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            background: var(--white);
            transition: box-shadow 0.3s ease;
        }
        
        .report-item:hover {
            box-shadow: 0 4px 12px var(--shadow);
        }
        
        .report-item h3 {
            margin: 0 0 10px 0;
            color: var(--text-dark);
            font-size: 18px;
        }
        
        .report-item p {
            color: var(--text-light);
            margin: 0 0 15px 0;
            font-size: 14px;
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
        
        @media (max-width: 768px) {
            .hr-cards {
                grid-template-columns: 1fr;
            }
            
            .report-list {
                grid-template-columns: 1fr;
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
                <a href="payroll_reports.php" class="nav-item">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Payroll Reports</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Reports</h4>
                <a href="hr_reports.php" class="nav-item active">
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
            <h1><i class="fas fa-chart-bar"></i> HR Reports</h1>
            <p>Generate and view comprehensive HR reports</p>
        </div>
        
        <div class="hr-cards">
            <div class="hr-card">
                <div class="hr-icon bg-blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="hr-content">
                    <h3><?php echo number_format($stats['total_employees']); ?></h3>
                    <p>Total Employees</p>
                    <div class="metric-label">All staff members</div>
                </div>
            </div>
            
            <div class="hr-card">
                <div class="hr-icon bg-green">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="hr-content">
                    <h3><?php echo number_format($stats['active_employees']); ?></h3>
                    <p>Active Employees</p>
                    <div class="metric-label">Currently employed</div>
                </div>
            </div>
            
            <div class="hr-card">
                <div class="hr-icon bg-orange">
                    <i class="fas fa-building"></i>
                </div>
                <div class="hr-content">
                    <h3><?php echo number_format($stats['departments']); ?></h3>
                    <p>Departments</p>
                    <div class="metric-label">Organizational units</div>
                </div>
            </div>
            
            <div class="hr-card">
                <div class="hr-icon bg-purple">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="hr-content">
                    <h3>$<?php echo number_format($stats['monthly_payroll'], 2); ?></h3>
                    <p>Monthly Payroll</p>
                    <div class="metric-label">This month's expenses</div>
                </div>
            </div>
        </div>
        
        <div class="hr-cards">
            <div class="hr-card">
                <div class="hr-icon bg-teal">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="hr-content">
                    <h3><?php echo $stats['turnover_rate']; ?>%</h3>
                    <p>Turnover Rate</p>
                    <div class="metric-label">Annual turnover percentage</div>
                </div>
            </div>
            
            <div class="hr-card">
                <div class="hr-icon bg-red">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="hr-content">
                    <h3><?php echo $stats['average_tenure']; ?> yrs</h3>
                    <p>Avg Tenure</p>
                    <div class="metric-label">Average employment length</div>
                </div>
            </div>
            
            <div class="hr-card">
                <div class="hr-icon bg-blue">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="hr-content">
                    <h3><?php echo number_format($stats['new_hires']); ?></h3>
                    <p>New Hires</p>
                    <div class="metric-label">This month</div>
                </div>
            </div>
            
            <div class="hr-card">
                <div class="hr-icon bg-green">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="hr-content">
                    <h3><?php echo number_format($stats['pending_payroll']); ?></h3>
                    <p>Pending Payroll</p>
                    <div class="metric-label">Awaiting processing</div>
                </div>
            </div>
        </div>
        
        <div class="report-section">
            <div class="section-header">
                <h2><i class="fas fa-file-invoice"></i> Available Reports</h2>
            </div>
            <div class="section-body">
                <div class="report-list">
                    <div class="report-item">
                        <h3><i class="fas fa-user-graduate"></i> Employee Summary Report</h3>
                        <p>Comprehensive report of all employees with their details and employment status</p>
                        <a href="#" class="btn btn-primary">Generate Report</a>
                    </div>
                    
                    <div class="report-item">
                        <h3><i class="fas fa-building"></i> Department Summary Report</h3>
                        <p>Detailed analysis of employees distribution across departments</p>
                        <a href="#" class="btn btn-primary">Generate Report</a>
                    </div>
                    
                    <div class="report-item">
                        <h3><i class="fas fa-chart-line"></i> Employee Turnover Report</h3>
                        <p>Track employee departures and retention rates</p>
                        <a href="#" class="btn btn-primary">Generate Report</a>
                    </div>
                    
                    <div class="report-item">
                        <h3><i class="fas fa-money-bill-wave"></i> Payroll Summary Report</h3>
                        <p>Overview of payroll expenses and tax calculations</p>
                        <a href="#" class="btn btn-primary">Generate Report</a>
                    </div>
                    
                    <div class="report-item">
                        <h3><i class="fas fa-user-clock"></i> Attendance Summary Report</h3>
                        <p>Detailed attendance records for all employees</p>
                        <a href="#" class="btn btn-primary">Generate Report</a>
                    </div>
                    
                    <div class="report-item">
                        <h3><i class="fas fa-clipboard-list"></i> Performance Summary Report</h3>
                        <p>Track employee performance metrics and evaluations</p>
                        <a href="#" class="btn btn-primary">Generate Report</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="report-section">
            <div class="section-header">
                <h2><i class="fas fa-download"></i> Downloadable Reports</h2>
            </div>
            <div class="section-body">
                <div class="report-list">
                    <div class="report-item">
                        <h3><i class="fas fa-file-pdf"></i> Annual HR Report</h3>
                        <p>Comprehensive annual report with all HR metrics and achievements</p>
                        <a href="#" class="btn btn-primary">Download PDF</a>
                        <a href="#" class="btn btn-secondary">Download Excel</a>
                    </div>
                    
                    <div class="report-item">
                        <h3><i class="fas fa-file-pdf"></i> Employee Directory</h3>
                        <p>Complete employee directory with contact information</p>
                        <a href="#" class="btn btn-primary">Download PDF</a>
                        <a href="#" class="btn btn-secondary">Download Excel</a>
                    </div>
                    
                    <div class="report-item">
                        <h3><i class="fas fa-file-pdf"></i> Payroll Tax Report</h3>
                        <p>Detailed report of payroll taxes and deductions</p>
                        <a href="#" class="btn btn-primary">Download PDF</a>
                        <a href="#" class="btn btn-secondary">Download Excel</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="chart-container">
            <div class="chart-header">
                <div class="chart-title">Employee Distribution by Department</div>
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
                    <i class="fas fa-chart-bar" style="font-size: 48px; margin-bottom: 15px;"></i>
                    <h3>Department Distribution</h3>
                    <p>Visual representation of employee distribution by department</p>
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