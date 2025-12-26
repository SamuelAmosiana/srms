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

// Create settings table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    // Table might already exist
}

// Get admin profile
$stmt = $pdo->prepare("SELECT ap.full_name, ap.staff_id FROM admin_profile ap WHERE ap.user_id = ?");
$stmt->execute([currentUserId()]);
$admin = $stmt->fetch();

// Get current settings from database
function getSetting($key, $default = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Save setting to database
function saveSetting($key, $value) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Handle form submissions
$message = '';
$messageType = '';

// System settings variables
$system_name = getSetting('system_name', 'Lusaka South College SRMS');
$system_email = getSetting('system_email', 'admin@lsc.ac.zm');
$timezone = getSetting('timezone', 'Africa/Lusaka');
$maintenance_mode = getSetting('maintenance_mode', '0') === '1';
$maintenance_end_time = getSetting('maintenance_end_time', '');

// Email settings variables
$email_host = getSetting('email_host', 'smtp.gmail.com');
$email_port = getSetting('email_port', '587');
$email_username = getSetting('email_username', 'noreply@lsc.ac.zm');
$email_password = '********'; // We don't retrieve passwords for security

// Handle form submission for general settings
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_general'])) {
    $system_name = $_POST['system_name'];
    $system_email = $_POST['system_email'];
    $timezone = $_POST['timezone'];
    $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
    
    // Save settings to database
    saveSetting('system_name', $system_name);
    saveSetting('system_email', $system_email);
    saveSetting('timezone', $timezone);
    saveSetting('maintenance_mode', $maintenance_mode);
    
    // Handle maintenance end time
    if ($maintenance_mode === '1') {
        $maintenance_end_date = $_POST['maintenance_end_date'] ?? '';
        $maintenance_end_time = $_POST['maintenance_end_time'] ?? '';
        
        if ($maintenance_end_date) {
            $end_datetime = $maintenance_end_date . ' ' . ($maintenance_end_time ?: '23:59:59');
            saveSetting('maintenance_end_time', $end_datetime);
        }
    } else {
        // Clear maintenance end time when maintenance mode is disabled
        saveSetting('maintenance_end_time', '');
    }
    
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
    
    // Save settings to database
    saveSetting('email_host', $email_host);
    saveSetting('email_port', $email_port);
    saveSetting('email_username', $email_username);
    
    $message = "Email settings saved successfully!";
    $messageType = 'success';
}

// Handle emergency access grants
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['grant_emergency_access'])) {
    $user_id = $_POST['user_id'];
    $reason = $_POST['reason'];
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO maintenance_emergency_access (user_id, granted_by, reason, expires_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE reason = ?, expires_at = ?, granted_by = ?, granted_at = NOW()");
        $stmt->execute([$user_id, currentUserId(), $reason, $expires_at, $reason, $expires_at, currentUserId()]);
        
        $message = "Emergency access granted successfully!";
        $messageType = 'success';
    } catch (Exception $e) {
        $message = "Error granting emergency access: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Handle emergency access revocation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['revoke_emergency_access'])) {
    $user_id = $_POST['user_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM maintenance_emergency_access WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        $message = "Emergency access revoked successfully!";
        $messageType = 'success';
    } catch (Exception $e) {
        $message = "Error revoking emergency access: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Get list of users for emergency access management
$users = [];
try {
    $stmt = $pdo->query("SELECT u.id, u.username, u.email, r.name as role_name, ap.full_name 
                         FROM users u 
                         LEFT JOIN user_roles ur ON u.id = ur.user_id 
                         LEFT JOIN roles r ON ur.role_id = r.id 
                         LEFT JOIN admin_profile ap ON u.id = ap.user_id 
                         LEFT JOIN staff_profile sp ON u.id = sp.user_id 
                         LEFT JOIN student_profile stp ON u.id = stp.user_id 
                         WHERE u.is_active = 1 
                         ORDER BY u.username");
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $users = [];
}

// Get current emergency access grants
$emergency_access_users = [];
try {
    $stmt = $pdo->query("SELECT mea.*, u.username, u.email, 
                         COALESCE(ap.full_name, sp.full_name, stp.full_name, u.username) as full_name,
                         CONCAT(r.name, ' (Granted by: ', admin_ap.full_name, ')') as role_info
                         FROM maintenance_emergency_access mea
                         JOIN users u ON mea.user_id = u.id
                         LEFT JOIN admin_profile ap ON u.id = ap.user_id
                         LEFT JOIN staff_profile sp ON u.id = sp.user_id
                         LEFT JOIN student_profile stp ON u.id = stp.user_id
                         LEFT JOIN user_roles ur ON u.id = ur.user_id
                         LEFT JOIN roles r ON ur.role_id = r.id
                         LEFT JOIN admin_profile admin_ap ON mea.granted_by = admin_ap.user_id
                         ORDER BY mea.granted_at DESC");
    $emergency_access_users = $stmt->fetchAll();
} catch (Exception $e) {
    $emergency_access_users = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
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
                                <input type="checkbox" id="maintenance_mode" name="maintenance_mode" <?php echo $maintenance_mode ? 'checked' : ''; ?> onchange="toggleMaintenanceOptions()">
                                Maintenance Mode
                            </label>
                            <small class="form-text">Enable maintenance mode to restrict access to administrators only</small>
                        </div>
                        
                        <div id="maintenance-options" style="display: <?php echo $maintenance_mode ? 'block' : 'none'; ?>; margin-top: 15px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9;">
                            <h4>Maintenance Duration</h4>
                            <div class="form-group">
                                <label for="maintenance_end_date">End Date</label>
                                <input type="date" id="maintenance_end_date" name="maintenance_end_date" value="<?php echo date('Y-m-d', strtotime($maintenance_end_time ?: '+2 days')); ?>" class="form-control" <?php echo $maintenance_mode ? '' : 'disabled'; ?>>
                            </div>
                            <div class="form-group">
                                <label for="maintenance_end_time">End Time</label>
                                <input type="time" id="maintenance_end_time" name="maintenance_end_time" value="<?php echo date('H:i', strtotime($maintenance_end_time ?: '23:59')); ?>" class="form-control" <?php echo $maintenance_mode ? '' : 'disabled'; ?>>
                            </div>
                            <small class="form-text">Set when the maintenance will end. The countdown will be displayed on the maintenance page.</small>
                        </div>
                        
                        <script>
                        function toggleMaintenanceOptions() {
                            const checkbox = document.getElementById('maintenance_mode');
                            const options = document.getElementById('maintenance-options');
                            
                            if (checkbox.checked) {
                                options.style.display = 'block';
                                // Enable the date and time inputs
                                document.getElementById('maintenance_end_date').disabled = false;
                                document.getElementById('maintenance_end_time').disabled = false;
                            } else {
                                options.style.display = 'none';
                                // Disable the date and time inputs
                                document.getElementById('maintenance_end_date').disabled = true;
                                document.getElementById('maintenance_end_time').disabled = true;
                            }
                        }
                        </script>
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

        <!-- Emergency Access Management -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-user-shield"></i> Emergency Access Management</h3>
            </div>
            <div class="panel-content">
                <p>Grant temporary access to specific users during maintenance mode.</p>
                
                <!-- Grant Emergency Access Form -->
                <form method="POST" class="mb-4">
                    <input type="hidden" name="grant_emergency_access" value="1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="user_id">Select User</label>
                            <select id="user_id" name="user_id" class="form-control" required>
                                <option value="">-- Select a User --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?> 
                                        (<?php echo htmlspecialchars($user['role_name'] ?? 'N/A'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="reason">Reason for Access</label>
                            <input type="text" id="reason" name="reason" class="form-control" placeholder="e.g., Critical bug fix, urgent report generation" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="expires_at">Expiration Date (Optional)</label>
                            <input type="datetime-local" id="expires_at" name="expires_at" class="form-control">
                            <small class="form-text">Leave blank for indefinite access</small>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-warning"><i class="fas fa-user-plus"></i> Grant Emergency Access</button>
                </form>
                
                <!-- Current Emergency Access Grants -->
                <h4><i class="fas fa-list"></i> Current Emergency Access Grants</h4>
                <?php if (empty($emergency_access_users)): ?>
                    <p>No users currently have emergency access.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Granted By</th>
                                    <th>Reason</th>
                                    <th>Granted At.</th>
                                    <th>Expires At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($emergency_access_users as $access): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($access['full_name'] ?? $access['username']); ?></td>
                                        <td><?php echo htmlspecialchars($access['role_info'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($access['granted_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($access['reason']); ?></td>
                                        <td><?php echo $access['expires_at'] ? date('Y-m-d H:i', strtotime($access['expires_at'])) : 'Never'; ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="revoke_emergency_access" value="1">
                                                <input type="hidden" name="user_id" value="<?php echo $access['user_id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to revoke emergency access for this user?')">
                                                    <i class="fas fa-user-times"></i> Revoke
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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
        
        .mb-4 {
            margin-bottom: 1.5rem;
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