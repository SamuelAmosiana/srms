<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in and has admin role
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

requireRole('Super Admin', $pdo);

// Get admin profile
$stmt = $pdo->prepare("SELECT ap.full_name, ap.staff_id FROM admin_profile ap WHERE ap.user_id = ?");
$stmt->execute([currentUserId()]);
$admin = $stmt->fetch();

// Get dashboard statistics
$stats = [];

// Count total users
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
$stats['total_users'] = $stmt->fetch()['count'];

// Count students
$stmt = $pdo->query("SELECT COUNT(*) as count FROM student_profile");
$stats['total_students'] = $stmt->fetch()['count'];

// Count staff (lecturers and other staff)
$stmt = $pdo->query("SELECT COUNT(*) as count FROM staff_profile");
$stats['total_lecturers'] = $stmt->fetch()['count'];

// Count courses
$stmt = $pdo->query("SELECT COUNT(*) as count FROM course");
$stats['total_courses'] = $stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LSC SRMS</title>
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
            <h3><i class="fas fa-tachometer-alt"></i> Admin Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item active">
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
                <a href="manage_intakes.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Intakes</span>
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
            <h1><i class="fas fa-tachometer-alt"></i> Dashboard Overview</h1>
            <p>Welcome to the Lusaka South College Student Records Management System</p>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total_users']); ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total_students']); ?></h3>
                    <p>Students</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total_lecturers']); ?></h3>
                    <p>Lecturers</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total_courses']); ?></h3>
                    <p>Courses</p>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
            <div class="actions-grid">
                <a href="manage_users.php" class="action-card orange">
                    <i class="fas fa-user-plus"></i>
                    <h3>Add New User</h3>
                    <p>Create new student, lecturer, or admin accounts</p>
                </a>
                
                <a href="upload_users.php" class="action-card green">
                    <i class="fas fa-upload"></i>
                    <h3>Bulk Upload</h3>
                    <p>Import multiple users from Excel file</p>
                </a>
                
                <a href="enrollment_approvals.php" class="action-card orange">
                    <i class="fas fa-user-check"></i>
                    <h3>Approve Enrollments</h3>
                    <p>Review and approve student enrollment requests</p>
                </a>
                
                <a href="manage_results.php" class="action-card green">
                    <i class="fas fa-chart-line"></i>
                    <h3>Manage Results</h3>
                    <p>Input and manage CA and exam results</p>
                </a>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="recent-activity">
            <h2><i class="fas fa-clock"></i> Recent Activity</h2>
            <div class="activity-list">
                <div class="activity-item">
                    <div class="activity-icon green">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="activity-content">
                        <h4>New user registered</h4>
                        <p>Student account created successfully</p>
                        <span class="activity-time">2 hours ago</span>
                    </div>
                </div>
                
                <div class="activity-item">
                    <div class="activity-icon orange">
                        <i class="fas fa-upload"></i>
                    </div>
                    <div class="activity-content">
                        <h4>Bulk upload completed</h4>
                        <p>25 users imported from Excel file</p>
                        <span class="activity-time">4 hours ago</span>
                    </div>
                </div>
                
                <div class="activity-item">
                    <div class="activity-icon green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="activity-content">
                        <h4>Enrollment approved</h4>
                        <p>5 student enrollments approved</p>
                        <span class="activity-time">6 hours ago</span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
</body>
</html>