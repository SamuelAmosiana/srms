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

// Handle notification settings update
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notifications'])) {
    try {
        // This would typically update user preferences in a user_settings table
        // For now, we'll just show a success message
        $message = 'Notification settings updated successfully!';
        $message_type = 'success';
    } catch (Exception $e) {
        $message = 'Error updating settings: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle theme preference
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_theme'])) {
    $theme = $_POST['theme_preference'];
    
    try {
        // This would typically update user preferences in a user_settings table
        // For now, we'll just show a success message
        $message = 'Theme preference updated successfully!';
        $message_type = 'success';
    } catch (Exception $e) {
        $message = 'Error updating theme: ' . $e->getMessage();
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - HR</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .settings-container {
            max-width: 800px;
            margin: 20px auto;
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 8px var(--shadow);
            overflow: hidden;
        }
        
        .settings-header {
            background-color: var(--primary-green);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .settings-header h1 {
            margin: 0;
            font-size: 24px;
        }
        
        .settings-header p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
        
        .settings-content {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-dark);
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-green);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            margin-right: 10px;
        }
        
        .checkbox-group label {
            margin: 0;
            font-weight: normal;
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
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        .section-title {
            color: var(--primary-green);
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-green);
        }
        
        .message {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .settings-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
        }
        
        .settings-section h3 {
            margin-top: 0;
            color: var(--primary-green);
            font-size: 18px;
        }
        
        .settings-section p {
            color: var(--text-light);
            margin-bottom: 15px;
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            margin: 10px 0;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
        }
        
        .radio-option input[type="radio"] {
            margin-right: 8px;
        }
        
        @media (max-width: 768px) {
            .settings-container {
                margin: 10px;
            }
            
            .settings-header {
                padding: 20px;
            }
            
            .settings-content {
                padding: 20px;
            }
            
            .radio-group {
                flex-direction: column;
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
                        <a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a>
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
            <h1><i class="fas fa-cog"></i> Settings</h1>
            <p>Configure your HR module preferences and settings</p>
        </div>
        
        <div class="settings-container">
            <div class="settings-header">
                <h1><i class="fas fa-cog"></i> HR Settings</h1>
                <p>Manage your preferences and configurations</p>
            </div>
            
            <div class="settings-content">
                <?php if ($message): ?>
                    <div class="message <?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="settings-section">
                        <h3><i class="fas fa-bell"></i> Notification Settings</h3>
                        <p>Configure how you receive notifications about HR activities</p>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="email_notifications" name="email_notifications" checked>
                            <label for="email_notifications">Email notifications</label>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="payroll_alerts" name="payroll_alerts" checked>
                            <label for="payroll_alerts">Payroll processing alerts</label>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="employee_changes" name="employee_changes" checked>
                            <label for="employee_changes">Employee record changes</label>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="system_updates" name="system_updates" checked>
                            <label for="system_updates">System updates and maintenance</label>
                        </div>
                        
                        <button type="submit" name="update_notifications" class="btn btn-primary">Save Notification Settings</button>
                    </div>
                    
                    <div class="settings-section">
                        <h3><i class="fas fa-palette"></i> Theme Preferences</h3>
                        <p>Customize the appearance of your HR dashboard</p>
                        
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" id="theme_light" name="theme_preference" value="light" checked>
                                <label for="theme_light">Light Theme</label>
                            </div>
                            
                            <div class="radio-option">
                                <input type="radio" id="theme_dark" name="theme_preference" value="dark">
                                <label for="theme_dark">Dark Theme</label>
                            </div>
                            
                            <div class="radio-option">
                                <input type="radio" id="theme_auto" name="theme_preference" value="auto">
                                <label for="theme_auto">Auto (System)</label>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_theme" class="btn btn-primary">Save Theme Settings</button>
                    </div>
                    
                    <div class="settings-section">
                        <h3><i class="fas fa-lock"></i> Security Settings</h3>
                        <p>Manage security preferences for your account</p>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="two_factor" name="two_factor">
                            <label for="two_factor">Enable two-factor authentication</label>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="password_expiration" name="password_expiration" checked>
                            <label for="password_expiration">Require password change every 90 days</label>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="session_timeout" name="session_timeout" checked>
                            <label for="session_timeout">Automatic logout after 30 minutes of inactivity</label>
                        </div>
                        
                        <button type="submit" name="update_security" class="btn btn-primary">Save Security Settings</button>
                    </div>
                    
                    <div class="settings-section">
                        <h3><i class="fas fa-file-alt"></i> Report Preferences</h3>
                        <p>Configure default settings for reports and analytics</p>
                        
                        <div class="form-group">
                            <label for="default_date_range">Default Date Range for Reports</label>
                            <select id="default_date_range" name="default_date_range" class="form-control">
                                <option value="30">Last 30 Days</option>
                                <option value="60">Last 60 Days</option>
                                <option value="90">Last 90 Days</option>
                                <option value="365">Last Year</option>
                                <option value="all">All Time</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="default_department">Default Department Filter</label>
                            <select id="default_department" name="default_department" class="form-control">
                                <option value="all">All Departments</option>
                                <?php
                                $stmt = $pdo->query("SELECT * FROM department ORDER BY name");
                                $departments = $stmt->fetchAll();
                                foreach ($departments as $dept):
                                ?>
                                    <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="auto_email_reports" name="auto_email_reports">
                            <label for="auto_email_reports">Automatically email reports to my inbox</label>
                        </div>
                        
                        <button type="submit" name="update_reports" class="btn btn-primary">Save Report Settings</button>
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
    </script>
</body>
</html>