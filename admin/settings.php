<?php
session_start();
require_once '../config.php';
require_once '../auth.php';

// Check if user is logged in
if (!currentUserId()) {
    header('Location: ../login.php');
    exit();
}

// Check if user has admin role or profile access permission
if (!currentUserHasRole('Super Admin', $pdo) && !currentUserHasPermission('profile_access', $pdo)) {
    header('Location: ../login.php');
    exit();
}

// Get admin profile
$stmt = $pdo->prepare("SELECT ap.full_name, ap.staff_id FROM admin_profile ap WHERE ap.user_id = ?");
$stmt->execute([currentUserId()]);
$admin = $stmt->fetch();

// Handle form submissions
$message = '';
$messageType = '';

// System settings variables
$system_name = 'Lusaka South College SRMS';
$system_email = 'admin@lsc.ac.zm';
$timezone = 'Africa/Lusaka';
$maintenance_mode = false;

// Email settings variables
$email_host = 'smtp.gmail.com';
$email_port = 587;
$email_username = 'noreply@lsc.ac.zm';
$email_password = '********';

// Handle form submission for general settings
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_general'])) {
    $system_name = $_POST['system_name'];
    $system_email = $_POST['system_email'];
    $timezone = $_POST['timezone'];
    $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
    
    // In a real implementation, you would save these settings to a database
    $message = "General settings saved successfully!";
    $messageType = 'success';
}

// Handle form submission for email settings
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_email'])) {
    $email_host = $_POST['email_host'];
    $email_port = $_POST['email_port'];
    $email_username = $_POST['email_username'];
    // Note: In a real implementation, you would encrypt the password before saving
    // $email_password = encrypt($_POST['email_password']);
    
    $message = "Email settings saved successfully!";
    $messageType = 'success';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - LSC SRMS</title>
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
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($admin['full_name'] ?? 'Administrator'); ?></span>
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
            <h3><i class="fas fa-tachometer-alt"></i> Admin Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>User Management</h4>
                <a href="manage_users.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>Manage Users</span>
                </a>
                <a href="manage_roles.php" class="nav-item">
                    <i class="fas fa-shield-alt"></i>
                    <span>Roles & Permissions</span>
                </a>
                <a href="upload_users.php" class="nav-item">
                    <i class="fas fa-upload"></i>
                    <span>Bulk Upload</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Academic Structure</h4>
                <a href="manage_schools.php" class="nav-item">
                    <i class="fas fa-university"></i>
                    <span>Schools</span>
                </a>
                <a href="manage_departments.php" class="nav-item">
                    <i class="fas fa-building"></i>
                    <span>Departments</span>
                </a>
                <a href="manage_programmes.php" class="nav-item">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Programmes</span>
                </a>
                <a href="manage_courses.php" class="nav-item">
                    <i class="fas fa-book"></i>
                    <span>Courses</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Academic Operations</h4>
                <a href="manage_results.php" class="nav-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Results Management</span>
                </a>
                <a href="enrollment_approvals.php" class="nav-item">
                    <i class="fas fa-user-check"></i>
                    <span>Enrollment Approvals</span>
                </a>
                <a href="course_registrations.php" class="nav-item">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Course Registrations</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Reports & Analytics</h4>
                <a href="reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
                <a href="analytics.php" class="nav-item">
                    <i class="fas fa-analytics"></i>
                    <span>Analytics</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-cog"></i> System Settings</h1>
            <p>Configure system-wide settings and preferences</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- General Settings -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-sliders-h"></i> General Settings</h3>
            </div>
            <div class="panel-content">
                <form method="POST">
                    <input type="hidden" name="save_general" value="1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="system_name">System Name</label>
                            <input type="text" id="system_name" name="system_name" value="<?php echo htmlspecialchars($system_name); ?>" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="system_email">System Email</label>
                            <input type="email" id="system_email" name="system_email" value="<?php echo htmlspecialchars($system_email); ?>" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="timezone">Timezone</label>
                            <select id="timezone" name="timezone" class="form-control">
                                <option value="Africa/Lusaka" <?php echo ($timezone == 'Africa/Lusaka') ? 'selected' : ''; ?>>Africa/Lusaka</option>
                                <option value="UTC" <?php echo ($timezone == 'UTC') ? 'selected' : ''; ?>>UTC</option>
                                <option value="America/New_York" <?php echo ($timezone == 'America/New_York') ? 'selected' : ''; ?>>America/New York</option>
                                <option value="Europe/London" <?php echo ($timezone == 'Europe/London') ? 'selected' : ''; ?>>Europe/London</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="maintenance_mode">
                                <input type="checkbox" id="maintenance_mode" name="maintenance_mode" <?php echo $maintenance_mode ? 'checked' : ''; ?>>
                                Maintenance Mode
                            </label>
                            <small class="form-text">Enable maintenance mode to restrict access to administrators only</small>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save General Settings</button>
                </form>
            </div>
        </div>

        <!-- Email Settings -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-envelope"></i> Email Settings</h3>
            </div>
            <div class="panel-content">
                <form method="POST">
                    <input type="hidden" name="save_email" value="1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="email_host">SMTP Host</label>
                            <input type="text" id="email_host" name="email_host" value="<?php echo htmlspecialchars($email_host); ?>" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="email_port">SMTP Port</label>
                            <input type="number" id="email_port" name="email_port" value="<?php echo htmlspecialchars($email_port); ?>" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="email_username">Username</label>
                            <input type="text" id="email_username" name="email_username" value="<?php echo htmlspecialchars($email_username); ?>" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="email_password">Password</label>
                            <input type="password" id="email_password" name="email_password" value="<?php echo htmlspecialchars($email_password); ?>" class="form-control">
                            <small class="form-text">Leave blank to keep current password</small>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Email Settings</button>
                </form>
            </div>
        </div>

        <!-- System Information -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-info-circle"></i> System Information</h3>
            </div>
            <div class="panel-content">
                <div class="info-grid">
                    <div class="info-item">
                        <label>PHP Version:</label>
                        <span><?php echo phpversion(); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Server Software:</label>
                        <span><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></span>
                    </div>
                    <div class="info-item">
                        <label>Database Version:</label>
                        <span>
                            <?php 
                            try {
                                $stmt = $pdo->query("SELECT VERSION()");
                                echo $stmt->fetchColumn();
                            } catch (Exception $e) {
                                echo 'N/A';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <label>System Timezone:</label>
                        <span><?php echo date_default_timezone_get(); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <style>
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: bold;
            color: var(--primary-green);
            margin-bottom: 5px;
        }
        
        .form-control {
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-text {
            color: var(--text-light);
            font-size: 12px;
            margin-top: 5px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-item label {
            font-weight: bold;
            color: var(--primary-green);
            margin-bottom: 5px;
        }
        
        .info-item span {
            color: var(--text-dark);
        }
        
        @media (max-width: 768px) {
            .form-grid, .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script src="../assets/js/admin-dashboard.js"></script>
</body>
</html>