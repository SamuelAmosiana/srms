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

// Fetch HR statistics
$stats = [
    'total_employees' => 0,
    'active_employees' => 0,
    'pending_payroll' => 0,
    'departments' => 0,
    'payroll_processed' => 0,
    'tax_brackets' => 0
];

try {
    // Get total employees
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees");
    $stats['total_employees'] = $stmt->fetch()['count'];
    
    // Get active employees
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
    $stats['active_employees'] = $stmt->fetch()['count'];
    
    // Get pending payroll
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM payroll WHERE status = 'pending'");
    $stats['pending_payroll'] = $stmt->fetch()['count'];
    
    // Get departments
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM departments");
    $stats['departments'] = $stmt->fetch()['count'];
    
    // Get processed payroll
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM payroll WHERE status = 'paid'");
    $stats['payroll_processed'] = $stmt->fetch()['count'];
    
    // Get tax brackets
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tax_brackets WHERE active = 1");
    $stats['tax_brackets'] = $stmt->fetch()['count'];
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
    <title>HR Dashboard - Human Resources</title>
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
            background: var(--white);
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
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .action-card {
            background: var(--white);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px var(--shadow);
            text-align: center;
            transition: transform 0.3s ease;
            cursor: pointer;
        }
        
        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px var(--shadow);
        }
        
        .action-icon {
            font-size: 36px;
            margin-bottom: 15px;
            color: var(--primary-green);
        }
        
        .action-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 5px;
        }
        
        .action-desc {
            font-size: 12px;
            color: var(--text-light);
        }
        
        .section-title {
            color: var(--primary-green);
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-green);
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
        
        .chart-wrapper {
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            border-radius: 8px;
            position: relative;
        }
        
        .chart-placeholder {
            text-align: center;
            color: #6c757d;
        }
        
        .chart-placeholder i {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .hr-cards {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
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
            <a href="dashboard" class="nav-item active">
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
            <h1><i class="fas fa-users"></i> HR Dashboard</h1>
            <p>Manage employees, payroll, and human resources</p>
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
                    <i class="fas fa-clock"></i>
                </div>
                <div class="hr-content">
                    <h3><?php echo number_format($stats['pending_payroll']); ?></h3>
                    <p>Pending Payroll</p>
                    <div class="metric-label">Awaiting processing</div>
                </div>
            </div>
            
            <div class="hr-card">
                <div class="hr-icon bg-purple">
                    <i class="fas fa-building"></i>
                </div>
                <div class="hr-content">
                    <h3><?php echo number_format($stats['departments']); ?></h3>
                    <p>Departments</p>
                    <div class="metric-label">Organizational units</div>
                </div>
            </div>
        </div>
        
        <div class="hr-cards">
            <div class="hr-card">
                <div class="hr-icon bg-teal">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="hr-content">
                    <h3><?php echo number_format($stats['payroll_processed']); ?></h3>
                    <p>Payroll Processed</p>
                    <div class="metric-label">Completed this month</div>
                </div>
            </div>
            
            <div class="hr-card">
                <div class="hr-icon bg-red">
                    <i class="fas fa-percent"></i>
                </div>
                <div class="hr-content">
                    <h3><?php echo number_format($stats['tax_brackets']); ?></h3>
                    <p>Tax Brackets</p>
                    <div class="metric-label">Active brackets</div>
                </div>
            </div>
            
            <div class="hr-card">
                <div class="hr-icon bg-blue">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="hr-content">
                    <h3>$<?php echo number_format(0, 2); ?></h3>
                    <p>Monthly Payroll</p>
                    <div class="metric-label">Estimated amount</div>
                </div>
            </div>
            
            <div class="hr-card">
                <div class="hr-icon bg-green">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="hr-content">
                    <h3><?php echo number_format(0); ?></h3>
                    <p>Leaves Pending</p>
                    <div class="metric-label">Awaiting approval</div>
                </div>
            </div>
        </div>
        
        <h2 class="section-title">Quick Actions</h2>
        <div class="quick-actions">
            <div class="action-card" onclick="location.href='add_employee.php'">
                <div class="action-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="action-title">Add Employee</div>
                <div class="action-desc">Register new staff member</div>
            </div>
            
            <div class="action-card" onclick="location.href='payroll.php'">
                <div class="action-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="action-title">Process Payroll</div>
                <div class="action-desc">Calculate and process salaries</div>
            </div>
            
            <div class="action-card" onclick="location.href='employees.php'">
                <div class="action-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="action-title">Employee List</div>
                <div class="action-desc">View all employees</div>
            </div>
            
            <div class="action-card" onclick="location.href='tax_brackets.php'">
                <div class="action-icon">
                    <i class="fas fa-percent"></i>
                </div>
                <div class="action-title">Tax Brackets</div>
                <div class="action-desc">Manage tax calculations</div>
            </div>
        </div>
        
        <div class="chart-container">
            <div class="chart-header">
                <div class="chart-title">Employee Distribution by Department</div>
                <div class="chart-actions">
                    <select class="form-control" style="width: auto; padding: 5px 10px; font-size: 14px;">
                        <option>This Month</option>
                        <option>Last 3 Months</option>
                        <option>Last 6 Months</option>
                        <option>Last Year</option>
                    </select>
                </div>
            </div>
            <div class="chart-wrapper">
                <div class="chart-placeholder">
                    <i class="fas fa-chart-pie"></i>
                    <h3>Department Distribution</h3>
                    <p>Visual representation of employee distribution across departments</p>
                </div>
            </div>
        </div>
        
        <div class="chart-container">
            <div class="chart-header">
                <div class="chart-title">Payroll Summary</div>
                <div class="chart-actions">
                    <select class="form-control" style="width: auto; padding: 5px 10px; font-size: 14px;">
                        <option>This Month</option>
                        <option>Last Month</option>
                        <option>Current Quarter</option>
                        <option>Current Year</option>
                    </select>
                </div>
            </div>
            <div class="chart-wrapper">
                <div class="chart-placeholder">
                    <i class="fas fa-chart-bar"></i>
                    <h3>Payroll Summary</h3>
                    <p>Summary of payroll processing and tax calculations</p>
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