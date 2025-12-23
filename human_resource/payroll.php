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

// Handle form submission for payroll processing
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payroll'])) {
    $employee_id = intval($_POST['employee_id']);
    $pay_period_start = trim($_POST['pay_period_start']);
    $pay_period_end = trim($_POST['pay_period_end']);
    $basic_salary = floatval($_POST['basic_salary']);
    $allowances = floatval($_POST['allowances']);
    $deductions = floatval($_POST['deductions']);
    
    // Validation
    if (empty($employee_id) || empty($pay_period_start) || empty($pay_period_end)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    } elseif (strtotime($pay_period_start) > strtotime($pay_period_end)) {
        $message = 'Pay period start date cannot be after end date.';
        $message_type = 'error';
    } else {
        try {
            // Calculate tax using tax brackets
            $tax_amount = 0;
            $tax_calculation = [];
            
            // Get tax brackets
            $stmt = $pdo->query("SELECT * FROM tax_brackets WHERE active = 1 ORDER BY min_income");
            $tax_brackets = $stmt->fetchAll();
            
            $taxable_income = $basic_salary + $allowances - $deductions;
            
            // Calculate tax based on brackets
            foreach ($tax_brackets as $bracket) {
                if ($taxable_income > $bracket['min_income']) {
                    $taxable_in_bracket = min($taxable_income, $bracket['max_income'] ?? PHP_INT_MAX) - $bracket['min_income'];
                    $tax_in_bracket = $taxable_in_bracket * ($bracket['tax_rate'] / 100) + $bracket['fixed_amount'];
                    $tax_amount += $tax_in_bracket;
                    
                    $tax_calculation[] = [
                        'bracket' => $bracket['bracket_name'],
                        'amount' => $tax_in_bracket,
                        'rate' => $bracket['tax_rate']
                    ];
                }
            }
            
            // Calculate net salary
            $net_salary = $taxable_income - $tax_amount;
            
            // Insert payroll record
            $stmt = $pdo->prepare("
                INSERT INTO payroll 
                (employee_id, pay_period_start, pay_period_end, basic_salary, allowances, deductions, tax_amount, net_salary, tax_calculation) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $employee_id, $pay_period_start, $pay_period_end, $basic_salary, $allowances, $deductions, $tax_amount, $net_salary, json_encode($tax_calculation)
            ]);
            
            $message = 'Payroll processed successfully!';
            $message_type = 'success';
            
            // Clear form values
            $employee_id = $pay_period_start = $pay_period_end = '';
            $basic_salary = $allowances = $deductions = 0;
        } catch (Exception $e) {
            $message = 'Error processing payroll: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Fetch employees for dropdown
$stmt = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name, ' (', employee_id, ')') as full_name FROM employees WHERE status = 'active' ORDER BY first_name");
$employees = $stmt->fetchAll();

// Fetch existing payroll records
$stmt = $pdo->prepare("
    SELECT p.*, e.first_name, e.last_name, e.employee_id 
    FROM payroll p 
    JOIN employees e ON p.employee_id = e.id 
    ORDER BY p.pay_period_start DESC
");
$stmt->execute();
$payroll_records = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Processing - HR</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-card {
            background: var(--White);
            border-radius: 8px;
            box-shadow: 0 2px 8px var(--shadow);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            background-color: var(--primary-green);
            color: white;
        }
        
        .card-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 500;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        
        .form-group {
            flex: 1 0 300px;
            padding: 0 10px;
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            flex: 1 0 100%;
        }
        
        .form-group.half-width {
            flex: 1 0 45%;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-dark);
        }
        
        .form-group.required label::after {
            content: " *";
            color: #ea4335;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 2px rgba(34, 139, 34, 0.2);
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-decoration: none;
            text-align: center;
        }
        
        .btn-primary {
            background-color: var(--primary-green);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--dark-green);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
        
        .payroll-table .actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-icon {
            padding: 8px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-edit {
            background-color: #ffc107;
            color: #212529;
        }
        
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-view {
            background-color: #17a2b8;
            color: white;
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
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .badge-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .calculation-breakdown {
            background-color: #f8f9fa;
            border-radius: 4px;
            padding: 15px;
            margin-top: 10px;
            font-size: 14px;
        }
        
        .calculation-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .calculation-total {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            border-top: 1px solid #dee2e6;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        @media (max-width: 768px) {
            .form-group {
                flex: 1 0 100%;
            }
            
            .form-group.half-width {
                flex: 1 0 100%;
            }
            
            .payroll-table {
                font-size: 14px;
            }
            
            .payroll-table th,
            .payroll-table td {
                padding: 8px 10px;
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
                <a href="payroll.php" class="nav-item active">
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
            <h1><i class="fas fa-money-bill-wave"></i> Payroll Processing</h1>
            <p>Process employee salaries with automatic tax calculations</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="form-card">
            <div class="card-header">
                <h2><i class="fas fa-calculator"></i> Process Payroll</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="process_payroll" value="1">
                    <div class="form-row">
                        <div class="form-group required half-width">
                            <label for="employee_id">Employee</label>
                            <select id="employee_id" name="employee_id" class="form-control" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>" 
                                        <?php echo (($_POST['employee_id'] ?? '') == $employee['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($employee['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group required half-width">
                            <label for="basic_salary">Basic Salary</label>
                            <input type="number" id="basic_salary" name="basic_salary" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['basic_salary'] ?? ''); ?>" 
                                   placeholder="e.g., 5000.00" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group half-width">
                            <label for="allowances">Allowances</label>
                            <input type="number" id="allowances" name="allowances" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['allowances'] ?? '0'); ?>" 
                                   placeholder="e.g., 500.00" step="0.01">
                        </div>
                        
                        <div class="form-group half-width">
                            <label for="deductions">Deductions</label>
                            <input type="number" id="deductions" name="deductions" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['deductions'] ?? '0'); ?>" 
                                   placeholder="e.g., 200.00" step="0.01">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group required half-width">
                            <label for="pay_period_start">Pay Period Start</label>
                            <input type="date" id="pay_period_start" name="pay_period_start" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['pay_period_start'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group required half-width">
                            <label for="pay_period_end">Pay Period End</label>
                            <input type="date" id="pay_period_end" name="pay_period_end" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['pay_period_end'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-calculator"></i> Process Payroll
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <h2 class="section-title">Payroll Records</h2>
        
        <?php if (count($payroll_records) > 0): ?>
            <div class="table-responsive">
                <table class="payroll-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Period</th>
                            <th>Basic Salary</th>
                            <th>Allowances</th>
                            <th>Deductions</th>
                            <th>Tax</th>
                            <th>Net Salary</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payroll_records as $payroll): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payroll['first_name'] . ' ' . $payroll['last_name'] . ' (' . $payroll['employee_id'] . ')'); ?></td>
                                <td><?php echo date('M j, Y', strtotime($payroll['pay_period_start'])); ?> - <?php echo date('M j, Y', strtotime($payroll['pay_period_end'])); ?></td>
                                <td>$<?php echo number_format($payroll['basic_salary'], 2); ?></td>
                                <td>$<?php echo number_format($payroll['allowances'], 2); ?></td>
                                <td>$<?php echo number_format($payroll['deductions'], 2); ?></td>
                                <td>$<?php echo number_format($payroll['tax_amount'], 2); ?></td>
                                <td>$<?php echo number_format($payroll['net_salary'], 2); ?></td>
                                <td><span class="badge badge-success"><?php echo ucfirst($payroll['status']); ?></span></td>
                                <td class="actions">
                                    <a href="#" class="btn-icon btn-view" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="#" class="btn-icon btn-edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="#" class="btn-icon btn-delete" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-money-bill-wave"></i>
                <h3>No Payroll Records Found</h3>
                <p>Process your first payroll using the form above.</p>
            </div>
        <?php endif; ?>
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