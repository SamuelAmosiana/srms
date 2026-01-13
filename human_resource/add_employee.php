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

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $employee_id = trim($_POST['employee_id']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $nrc_number = trim($_POST['nrc_number']);
    $tax_pin = trim($_POST['tax_pin']);
    $department_id = intval($_POST['department_id']);
    $position = trim($_POST['position']);
    $hire_date = trim($_POST['hire_date']);
    $salary = floatval($_POST['salary']);
    $status = trim($_POST['status']);
    
    // KYC fields
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $country = trim($_POST['country']);
    $postal_code = trim($_POST['postal_code']);
    $bank_name = trim($_POST['bank_name']);
    $account_number = trim($_POST['account_number']);
    $account_name = trim($_POST['account_name']);
    $branch_code = trim($_POST['branch_code']);
    
    // Handle CV upload
    $cv_path = null;
    if (isset($_FILES['cv']) && $_FILES['cv']['error'] == 0) {
        $upload_dir = '../uploads/cvs/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['pdf', 'doc', 'docx'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $file_name = $employee_id . '_cv.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['cv']['tmp_name'], $file_path)) {
                $cv_path = 'uploads/cvs/' . $file_name;
            } else {
                $message = 'Error uploading CV file.';
                $message_type = 'error';
            }
        } else {
            $message = 'Invalid CV file format. Only PDF, DOC, DOCX files are allowed.';
            $message_type = 'error';
        }
    }

    // Validation
    if (empty($employee_id) || empty($first_name) || empty($last_name) || empty($email)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'error';
    } else {
        try {
            // Check if employee ID already exists
            $stmt = $pdo->prepare("SELECT id FROM employees WHERE employee_id = ?");
            $stmt->execute([$employee_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $message = 'Employee ID already exists.';
                $message_type = 'error';
            } else {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM employees WHERE email = ?");
                $stmt->execute([$email]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $message = 'Email address already exists.';
                    $message_type = 'error';
                } else {
                    // Insert employee record with KYC fields
                    $stmt = $pdo->prepare("
                        INSERT INTO employees 
                        (employee_id, first_name, last_name, email, phone, nrc_number, tax_pin, department_id, position, hire_date, salary, status, 
                         address, city, state, country, postal_code, bank_name, account_number, account_name, branch_code, cv_path) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $employee_id, $first_name, $last_name, $email, $phone, $nrc_number, $tax_pin, $department_id, $position, $hire_date, $salary, $status,
                        $address, $city, $state, $country, $postal_code, $bank_name, $account_number, $account_name, $branch_code, $cv_path
                    ]);
                    
                    $message = 'Employee added successfully!';
                    $message_type = 'success';
                    
                    // Clear form values
                    $employee_id = $first_name = $last_name = $email = $phone = $position = $hire_date = '';
                    $nrc_number = $tax_pin = '';
                    $department_id = $salary = 0;
                    $status = 'active';
                    $address = $city = $state = $country = $postal_code = $bank_name = $account_number = $account_name = $branch_code = $cv_path = '';
                }
            }
        } catch (Exception $e) {
            $message = 'Error adding employee: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Fetch departments for dropdown
$stmt = $pdo->query("SELECT id, name FROM department ORDER BY name");
$departments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee - HR</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-card {
            background: var(--white);
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
        
        .section-title {
            color: var(--primary-green);
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-green);
        }
        
        .tab-nav {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        
        .tab-btn {
            padding: 12px 20px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-light);
            border-bottom: 2px solid transparent;
        }
        
        .tab-btn.active {
            color: var(--primary-green);
            border-bottom: 2px solid var(--primary-green);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @media (max-width: 768px) {
            .form-group {
                flex: 1 0 100%;
            }
            
            .form-group.half-width {
                flex: 1 0 100%;
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
                <a href="add_employee.php" class="nav-item active">
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
            <h1><i class="fas fa-user-plus"></i> Add Employee</h1>
            <p>Register a new employee in the system</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="form-card">
            <div class="card-header">
                <h2><i class="fas fa-user-plus"></i> Employee Information</h2>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="add_employee" value="1">
                    
                    <div class="tab-nav">
                        <button type="button" class="tab-btn active" onclick="showTab('personal')">Personal Info</button>
                        <button type="button" class="tab-btn" onclick="showTab('employment')">Employment</button>
                        <button type="button" class="tab-btn" onclick="showTab('address')">Address</button>
                        <button type="button" class="tab-btn" onclick="showTab('bank')">Bank Details</button>
                    </div>
                    
                    <div id="personal-tab" class="tab-content active">
                        <div class="form-row">
                            <div class="form-group required half-width">
                                <label for="employee_id">Employee ID</label>
                                <input type="text" id="employee_id" name="employee_id" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['employee_id'] ?? ''); ?>" 
                                       placeholder="e.g., EMP001" required>
                            </div>
                            
                            <div class="form-group required half-width">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                       placeholder="employee@lsc.edu" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group required half-width">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" 
                                       placeholder="First name" required>
                            </div>
                            
                            <div class="form-group required half-width">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" 
                                       placeholder="Last name" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group half-width">
                                <label for="phone">Phone</label>
                                <input type="text" id="phone" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                                       placeholder="e.g., +260-900-000-000">
                            </div>
                            
                            <div class="form-group half-width">
                                <label for="nrc_number">NRC Number</label>
                                <input type="text" id="nrc_number" name="nrc_number" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['nrc_number'] ?? ''); ?>" 
                                       placeholder="e.g., 123456/AA/12">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group half-width">
                                <label for="tax_pin">T-PIN</label>
                                <input type="text" id="tax_pin" name="tax_pin" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['tax_pin'] ?? ''); ?>" 
                                       placeholder="e.g., 123456789Z">
                            </div>
                            
                            <div class="form-group half-width">
                                <label for="cv">CV/Resume</label>
                                <input type="file" id="cv" name="cv" class="form-control" accept=".pdf,.doc,.docx">
                                <small class="form-text">Accepted formats: PDF, DOC, DOCX (Max 5MB)</small>
                            </div>
                        </div>
                    </div>
                    
                    <div id="employment-tab" class="tab-content">
                        <div class="form-row">
                            <div class="form-group half-width">
                                <label for="department_id">Department</label>
                                <select id="department_id" name="department_id" class="form-control">
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>" 
                                            <?php echo (($_POST['department_id'] ?? '') == $dept['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group half-width">
                                <label for="position">Position</label>
                                <input type="text" id="position" name="position" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['position'] ?? ''); ?>" 
                                       placeholder="Job title">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group half-width">
                                <label for="hire_date">Hire Date</label>
                                <input type="date" id="hire_date" name="hire_date" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['hire_date'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group half-width">
                                <label for="salary">Salary</label>
                                <input type="number" id="salary" name="salary" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['salary'] ?? ''); ?>" 
                                       placeholder="e.g., 5000.00" step="0.01">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group half-width">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="active" <?php echo (($_POST['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo (($_POST['status'] ?? 'active') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="terminated" <?php echo (($_POST['status'] ?? 'active') === 'terminated') ? 'selected' : ''; ?>>Terminated</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div id="address-tab" class="tab-content">
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="address">Address</label>
                                <input type="text" id="address" name="address" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>" 
                                       placeholder="Street address">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group half-width">
                                <label for="city">City</label>
                                <input type="text" id="city" name="city" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>" 
                                       placeholder="City">
                            </div>
                            
                            <div class="form-group half-width">
                                <label for="state">State/Province</label>
                                <input type="text" id="state" name="state" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['state'] ?? ''); ?>" 
                                       placeholder="State/Province">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group half-width">
                                <label for="country">Country</label>
                                <input type="text" id="country" name="country" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['country'] ?? ''); ?>" 
                                       placeholder="Country">
                            </div>
                            
                            <div class="form-group half-width">
                                <label for="postal_code">Postal Code</label>
                                <input type="text" id="postal_code" name="postal_code" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['postal_code'] ?? ''); ?>" 
                                       placeholder="Postal Code">
                            </div>
                        </div>
                    </div>
                    
                    <div id="bank-tab" class="tab-content">
                        <div class="form-row">
                            <div class="form-group half-width">
                                <label for="bank_name">Bank Name</label>
                                <input type="text" id="bank_name" name="bank_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['bank_name'] ?? ''); ?>" 
                                       placeholder="Bank name">
                            </div>
                            
                            <div class="form-group half-width">
                                <label for="account_number">Account Number</label>
                                <input type="text" id="account_number" name="account_number" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['account_number'] ?? ''); ?>" 
                                       placeholder="Account number">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group half-width">
                                <label for="account_name">Account Name</label>
                                <input type="text" id="account_name" name="account_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['account_name'] ?? ''); ?>" 
                                       placeholder="Account name">
                            </div>
                            
                            <div class="form-group half-width">
                                <label for="branch_code">Branch Code</label>
                                <input type="text" id="branch_code" name="branch_code" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['branch_code'] ?? ''); ?>" 
                                       placeholder="Branch code">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Add Employee
                        </button>
                        <a href="employees.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Employees
                        </a>
                    </div>
                </form>
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
        
        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
    </script>
</body>
</html>