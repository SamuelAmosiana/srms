<?php
require '../config.php';
require '../auth/auth.php';

// Check if user is logged in
if (!currentUserId()) {
    header('Location: ../auth/login.php');
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

// Fetch HR analytics data
$analytics = [
    'total_employees' => 0,
    'active_employees' => 0,
    'terminated_employees' => 0,
    'departments' => 0,
    'avg_tenure' => 0,
    'turnover_rate' => 0,
    'new_hires' => 0,
    'avg_salary' => 0,
    'total_payroll' => 0,
    'cost_per_employee' => 0
];

try {
    // Get total employees
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees");
    $analytics['total_employees'] = $stmt->fetch()['count'];
    
    // Get active employees
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
    $analytics['active_employees'] = $stmt->fetch()['count'];
    
    // Get terminated employees
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees WHERE status = 'terminated'");
    $analytics['terminated_employees'] = $stmt->fetch()['count'];
    
    // Get departments
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM department");
    $analytics['departments'] = $stmt->fetch()['count'];
    
    // Get average tenure
    $stmt = $pdo->query("SELECT AVG(DATEDIFF(NOW(), hire_date)) as avg_days FROM employees WHERE status = 'active'");
    $avg_days = $stmt->fetch()['avg_days'];
    $analytics['avg_tenure'] = $avg_days ? round($avg_days / 365, 1) : 0;
    
    // Get turnover rate
    $terminated_count = $analytics['terminated_employees'];
    $analytics['turnover_rate'] = $analytics['total_employees'] > 0 ? round(($terminated_count / $analytics['total_employees']) * 100, 2) : 0;
    
    // Get new hires this month
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees WHERE hire_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
    $analytics['new_hires'] = $stmt->fetch()['count'];
    
    // Get average salary
    $stmt = $pdo->query("SELECT AVG(net_salary) as avg_salary FROM payroll");
    $analytics['avg_salary'] = $stmt->fetch()['avg_salary'] ?? 0;
    
    // Get total payroll
    $stmt = $pdo->query("SELECT SUM(net_salary) as total FROM payroll WHERE status = 'paid'");
    $analytics['total_payroll'] = $stmt->fetch()['total'] ?? 0;
    
    // Get cost per employee
    $analytics['cost_per_employee'] = $analytics['active_employees'] > 0 ? round($analytics['total_payroll'] / $analytics['active_employees'], 2) : 0;
} catch (Exception $e) {
    // Handle error gracefully
    error_log("Error fetching HR analytics: " . $e->getMessage());
}

// Get department analytics
$stmt = $pdo->query("
    SELECT 
        d.name as department_name,
        COUNT(e.id) as employee_count,
        AVG(p.net_salary) as avg_salary
    FROM department d
    LEFT JOIN employees e ON d.id = e.department_id
    LEFT JOIN payroll p ON e.id = p.employee_id
    GROUP BY d.id, d.name
    ORDER BY employee_count DESC
");
$dept_analytics = $stmt->fetchAll();

// Get employee status breakdown
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM employees GROUP BY status");
$status_breakdown = $stmt->fetchAll();

// Get monthly hires
$stmt = $pdo->query("SELECT DATE_FORMAT(hire_date, '%Y-%m') as month, COUNT(*) as count FROM employees GROUP BY DATE_FORMAT(hire_date, '%Y-%m') ORDER BY month DESC LIMIT 12");
$monthly_hires = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Analytics - HR</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .analytics-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .analytics-card {
            background: var(--white);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px var(--shadow);
            display: flex;
            align-items: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .analytics-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 16px var(--shadow);
        }
        
        .analytics-icon {
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
        
        .analytics-content h3 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-dark);
        }
        
        .analytics-content p {
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
        
        .chart-placeholder {
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            border-radius: 8px;
            text-align: center;
            color: #6c757d;
        }
        
        .chart-placeholder i {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .analytics-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px var(--shadow);
        }
        
        .analytics-table th,
        .analytics-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .analytics-table th {
            background-color: var(--primary-green);
            color: white;
            font-weight: 600;
        }
        
        .analytics-table tbody tr:hover {
            background-color: rgba(34, 139, 34, 0.05);
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
        
        .form-control {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
        }
        
        .trend-up {
            color: #28a745;
        }
        
        .trend-down {
            color: #dc3545;
        }
        
        .trend-neutral {
            color: #6c757d;
        }
        
        .section-title {
            color: var(--primary-green);
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-green);
        }
        
        .metric-label {
            font-size: 12px;
            color: var(--text-light);
        }
        
        .trend-indicator {
            font-size: 14px;
            margin-left: 5px;
        }
        
        @media (max-width: 768px) {
            .analytics-cards {
                grid-template-columns: 1fr;
            }
            
            .analytics-table {
                font-size: 14px;
            }
            
            .analytics-table th,
            .analytics-table td {
                padding: 8px 10px;
            }
            
            .chart-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
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
                <a href="hr_reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>HR Reports</span>
                </a>
                <a href="analytics.php" class="nav-item active">
                    <i class="fas fa-chart-line"></i>
                    <span>HR Analytics</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-chart-line"></i> HR Analytics</h1>
            <p>Comprehensive analytics and insights for HR management</p>
        </div>
        
        <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
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
                <i class="fas fa-sync-alt"></i> Refresh
            </a>
        </div>
        
        <div class="analytics-cards">
            <div class="analytics-card">
                <div class="analytics-icon bg-blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="analytics-content">
                    <h3><?php echo number_format($analytics['total_employees']); ?></h3>
                    <p>Total Employees</p>
                    <div class="metric-label">All staff members</div>
                </div>
            </div>
            
            <div class="analytics-card">
                <div class="analytics-icon bg-green">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="analytics-content">
                    <h3><?php echo number_format($analytics['active_employees']); ?></h3>
                    <p>Active Employees</p>
                    <div class="metric-label">Currently employed</div>
                </div>
            </div>
            
            <div class="analytics-card">
                <div class="analytics-icon bg-orange">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="analytics-content">
                    <h3><?php echo number_format($analytics['terminated_employees']); ?></h3>
                    <p>Terminated</p>
                    <div class="metric-label">No longer employed</div>
                </div>
            </div>
            
            <div class="analytics-card">
                <div class="analytics-icon bg-purple">
                    <i class="fas fa-building"></i>
                </div>
                <div class="analytics-content">
                    <h3><?php echo number_format($analytics['departments']); ?></h3>
                    <p>Departments</p>
                    <div class="metric-label">Organizational units</div>
                </div>
            </div>
        </div>
        
        <div class="analytics-cards">
            <div class="analytics-card">
                <div class="analytics-icon bg-teal">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="analytics-content">
                    <h3><?php echo $analytics['avg_tenure']; ?> yrs</h3>
                    <p>Avg Tenure</p>
                    <div class="metric-label">Average employment length</div>
                </div>
            </div>
            
            <div class="analytics-card">
                <div class="analytics-icon bg-red">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="analytics-content">
                    <h3><?php echo $analytics['turnover_rate']; ?>%</h3>
                    <p>Turnover Rate</p>
                    <div class="metric-label">Annual turnover percentage</div>
                </div>
            </div>
            
            <div class="analytics-card">
                <div class="analytics-icon bg-pink">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="analytics-content">
                    <h3><?php echo number_format($analytics['new_hires']); ?></h3>
                    <p>New Hires</p>
                    <div class="metric-label">This month</div>
                </div>
            </div>
            
            <div class="analytics-card">
                <div class="analytics-icon bg-indigo">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="analytics-content">
                    <h3>$<?php echo number_format($analytics['avg_salary'], 2); ?></h3>
                    <p>Avg Salary</p>
                    <div class="metric-label">Average compensation</div>
                </div>
            </div>
        </div>
        
        <div class="chart-container">
            <div class="chart-header">
                <div class="chart-title">Employee Distribution by Status</div>
                <div class="chart-actions">
                    <select class="form-control" style="width: auto; padding: 5px 10px; font-size: 14px;">
                        <option>All Time</option>
                        <option>Last 30 Days</option>
                        <option>Last 90 Days</option>
                        <option>Last Year</option>
                    </select>
                </div>
            </div>
            <div class="chart-placeholder">
                <div>
                    <i class="fas fa-chart-pie"></i>
                    <h3>Employee Status Distribution</h3>
                    <p>
                        <?php foreach ($status_breakdown as $status): ?>
                            <span><?php echo ucfirst($status['status']); ?>: <?php echo $status['count']; ?> employees</span><br>
                        <?php endforeach; ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="chart-container">
            <div class="chart-header">
                <div class="chart-title">Employee Distribution by Department</div>
                <div class="chart-actions">
                    <select class="form-control" style="width: auto; padding: 5px 10px; font-size: 14px;">
                        <option>All Time</option>
                        <option>Last 30 Days</option>
                        <option>Last 90 Days</option>
                        <option>Last Year</option>
                    </select>
                </div>
            </div>
            <?php if (count($dept_analytics) > 0): ?>
                <div class="table-responsive">
                    <table class="analytics-table">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Employee Count</th>
                                <th>Average Salary</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dept_analytics as $dept): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                                    <td><?php echo number_format($dept['employee_count']); ?></td>
                                    <td>$<?php echo number_format($dept['avg_salary'] ?? 0, 2); ?></td>
                                    <td>
                                        <?php
                                        $percentage = $analytics['total_employees'] > 0 ? round(($dept['employee_count'] / $analytics['total_employees']) * 100, 2) : 0;
                                        echo $percentage . '%';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="chart-placeholder">
                    <i class="fas fa-building"></i>
                    <h3>No Department Data</h3>
                    <p>No department information available</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="chart-container">
            <div class="chart-header">
                <div class="chart-title">Monthly Hiring Trends</div>
                <div class="chart-actions">
                    <select class="form-control" style="width: auto; padding: 5px 10px; font-size: 14px;">
                        <option>Last 12 Months</option>
                        <option>Last 6 Months</option>
                        <option>Last 3 Months</option>
                        <option>Current Year</option>
                    </select>
                </div>
            </div>
            <?php if (count($monthly_hires) > 0): ?>
                <div class="table-responsive">
                    <table class="analytics-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>New Hires</th>
                                <th>Trend</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $prev_count = 0;
                            foreach ($monthly_hires as $i => $hire): 
                                $trend_class = '';
                                $trend_text = '';
                                
                                if ($i > 0) {
                                    if ($hire['count'] > $prev_count) {
                                        $trend_class = 'trend-up';
                                        $trend_text = '<i class="fas fa-arrow-up"></i> Increase';
                                    } elseif ($hire['count'] < $prev_count) {
                                        $trend_class = 'trend-down';
                                        $trend_text = '<i class="fas fa-arrow-down"></i> Decrease';
                                    } else {
                                        $trend_class = 'trend-neutral';
                                        $trend_text = '<i class="fas fa-minus"></i> No Change';
                                    }
                                } else {
                                    $trend_text = 'N/A';
                                }
                                
                                $prev_count = $hire['count'];
                            ?>
                                <tr>
                                    <td><?php echo $hire['month']; ?></td>
                                    <td><?php echo number_format($hire['count']); ?></td>
                                    <td class="<?php echo $trend_class; ?>"><?php echo $trend_text; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="chart-placeholder">
                    <i class="fas fa-user-plus"></i>
                    <h3>No Hiring Data</h3>
                    <p>No hiring information available</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="chart-container">
            <div class="chart-header">
                <div class="chart-title">Payroll Analytics</div>
                <div class="chart-actions">
                    <select class="form-control" style="width: auto; padding: 5px 10px; font-size: 14px;">
                        <option>Current Month</option>
                        <option>Last 30 Days</option>
                        <option>Last 90 Days</option>
                        <option>Last Year</option>
                    </select>
                </div>
            </div>
            <div class="chart-placeholder">
                <div>
                    <i class="fas fa-chart-bar"></i>
                    <h3>Payroll Analytics</h3>
                    <p>Total Payroll: $<?php echo number_format($analytics['total_payroll'], 2); ?></p>
                    <p>Cost per Employee: $<?php echo number_format($analytics['cost_per_employee'], 2); ?></p>
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