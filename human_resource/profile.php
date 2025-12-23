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

// Get HR profile information
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.email, ap.full_name, ap.staff_id, ap.phone, ap.email as profile_email, 
           ap.department, ap.position, ap.hire_date, ap.status, ap.profile_image
    FROM users u
    LEFT JOIN admin_profile ap ON u.id = ap.user_id
    WHERE u.id = ?
");
$stmt->execute([currentUserId()]);
$hr = $stmt->fetch();

// Handle profile update
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $position = trim($_POST['position']);
    
    try {
        // Update user table
        $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->execute([$email, currentUserId()]);
        
        // Update admin_profile table
        $stmt = $pdo->prepare("
            UPDATE admin_profile 
            SET full_name = ?, phone = ?, email = ?, position = ? 
            WHERE user_id = ?
        ");
        $stmt->execute([$full_name, $phone, $email, $position, currentUserId()]);
        
        $message = 'Profile updated successfully!';
        $message_type = 'success';
        
        // Refresh profile data
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.email, ap.full_name, ap.staff_id, ap.phone, ap.email as profile_email, 
                   ap.department, ap.position, ap.hire_date, ap.status, ap.profile_image
            FROM users u
            LEFT JOIN admin_profile ap ON u.id = ap.user_id
            WHERE u.id = ?
        ");
        $stmt->execute([currentUserId()]);
        $hr = $stmt->fetch();
    } catch (Exception $e) {
        $message = 'Error updating profile: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate passwords
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = 'Please fill in all password fields.';
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = 'New passwords do not match.';
        $message_type = 'error';
    } elseif (strlen($new_password) < 6) {
        $message = 'New password must be at least 6 characters long.';
        $message_type = 'error';
    } else {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([currentUserId()]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($current_password, $user['password_hash'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hashed_password, currentUserId()]);
            
            $message = 'Password changed successfully!';
            $message_type = 'success';
        } else {
            $message = 'Current password is incorrect.';
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - HR</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .profile-container {
            max-width: 800px;
            margin: 20px auto;
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 8px var(--shadow);
            overflow: hidden;
        }
        
        .profile-header {
            background-color: var(--primary-green);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .profile-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.3);
            margin: 0 auto 15px;
            background-color: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #666;
        }
        
        .profile-header h1 {
            margin: 0;
            font-size: 24px;
        }
        
        .profile-header p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
        
        .profile-content {
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
        
        .profile-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-item {
            background: var(--white);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
        }
        
        .info-item h4 {
            margin: 0 0 10px 0;
            color: var(--primary-green);
            font-size: 16px;
        }
        
        .info-item p {
            margin: 0;
            color: var(--text-dark);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .tab-nav {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        
        .tab-nav button {
            padding: 12px 20px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-light);
            border-bottom: 2px solid transparent;
        }
        
        .tab-nav button.active {
            color: var(--primary-green);
            border-bottom: 2px solid var(--primary-green);
        }
        
        @media (max-width: 768px) {
            .profile-container {
                margin: 10px;
            }
            
            .profile-header {
                padding: 20px;
            }
            
            .profile-content {
                padding: 20px;
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
                        <a href="profile.php" class="active"><i class="fas fa-user"></i> View Profile</a>
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
            <h1><i class="fas fa-user"></i> My Profile</h1>
            <p>Manage your profile information and settings</p>
        </div>
        
        <div class="profile-container">
            <div class="profile-header">
                <div class="profile-image">
                    <i class="fas fa-user"></i>
                </div>
                <h1><?php echo htmlspecialchars($hr['full_name'] ?? 'HR Manager'); ?></h1>
                <p><?php echo htmlspecialchars($hr['position'] ?? 'HR Manager'); ?></p>
            </div>
            
            <div class="profile-content">
                <?php if ($message): ?>
                    <div class="message <?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <div class="tab-nav">
                    <button class="tab-btn active" onclick="showTab('profile')">Profile Information</button>
                    <button class="tab-btn" onclick="showTab('password')">Change Password</button>
                </div>
                
                <div id="profile-tab" class="tab-content active">
                    <form method="POST">
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($hr['full_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" class="form-control" 
                                   value="<?php echo htmlspecialchars($hr['username'] ?? ''); ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($hr['email'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($hr['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="position">Position</label>
                            <input type="text" id="position" name="position" class="form-control" 
                                   value="<?php echo htmlspecialchars($hr['position'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="staff_id">Staff ID</label>
                            <input type="text" id="staff_id" name="staff_id" class="form-control" 
                                   value="<?php echo htmlspecialchars($hr['staff_id'] ?? ''); ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label for="hire_date">Hire Date</label>
                            <input type="text" id="hire_date" name="hire_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($hr['hire_date'] ?? ''); ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <input type="text" id="status" name="status" class="form-control" 
                                   value="<?php echo htmlspecialchars(ucfirst($hr['status'] ?? 'active')); ?>" disabled>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
                
                <div id="password-tab" class="tab-content">
                    <form method="POST">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                    </form>
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