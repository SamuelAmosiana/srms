<?php
require '../config.php';
require '../auth/auth.php';

// Check if user is logged in and has lecturer role
if (!currentUserId()) {
    header('Location: ../auth/login.php');
    exit;
}

requireRole('Lecturer', $pdo);

// Get lecturer profile
$stmt = $pdo->prepare("SELECT sp.full_name, sp.staff_id FROM staff_profile sp WHERE sp.user_id = ?");
$stmt->execute([currentUserId()]);
$lecturer = $stmt->fetch();

// Get dashboard statistics for lecturer
$stats = [];

// Count courses taught by lecturer using course_assignment table
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM course_assignment WHERE lecturer_id = ? AND is_active = 1");
$stmt->execute([currentUserId()]);
$stats['total_courses'] = $stmt->fetch()['count'];

// Count total students enrolled in lecturer's courses
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT ce.student_user_id) as count 
    FROM course_enrollment ce 
    JOIN course_assignment ca ON ce.course_id = ca.course_id 
    WHERE ca.lecturer_id = ? AND ca.is_active = 1 AND ce.status = 'enrolled'
");
$stmt->execute([currentUserId()]);
$stats['total_students'] = $stmt->fetch()['count'];

// Count results that need grading (where total_score is 0 or null)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM results r
    JOIN course_enrollment ce ON r.enrollment_id = ce.id
    JOIN course_assignment ca ON ce.course_id = ca.course_id
    WHERE ca.lecturer_id = ? AND ca.is_active = 1 
    AND (r.total_score = 0 OR r.total_score IS NULL)
");
$stmt->execute([currentUserId()]);
$stats['pending_results'] = $stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css"> <!-- Reuse styles, adjust if needed -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="admin-layout" data-theme="light"> <!-- Keeping class for styling consistency -->
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
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($lecturer['full_name'] ?? 'Lecturer'); ?></span>
                <span class="staff-id">(<?php echo htmlspecialchars($lecturer['staff_id'] ?? 'N/A'); ?>)</span>
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
                        <a href="profile"><i class="fas fa-user"></i> View Profile</a>
                        <a href="settings"><i class="fas fa-cog"></i> Settings</a>
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
            <h3><i class="fas fa-tachometer-alt"></i> Lecturer Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard" class="nav-item active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Results Management</h4>
                <a href="upload_results" class="nav-item">
                    <i class="fas fa-upload"></i>
                    <span>Upload Results</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Student Management</h4>
                <a href="view_students" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>View Students</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Reports</h4>
                <a href="manage_reports" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Manage Reports</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Profile</h4>
                <a href="profile" class="nav-item">
                    <i class="fas fa-user"></i>
                    <span>View Profile</span>
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
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total_courses']); ?></h3>
                    <p>Courses Taught</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total_students']); ?></h3>
                    <p>Enrolled Students</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-file-upload"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['pending_results']); ?></h3>
                    <p>Pending Results</p>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
            <div class="actions-grid">
                <a href="upload_results" class="action-card orange">
                    <i class="fas fa-upload"></i>
                    <h3>Upload Results</h3>
                    <p>Upload CA and Exam results via Excel</p>
                </a>
                
                <a href="view_students" class="action-card green">
                    <i class="fas fa-users"></i>
                    <h3>View Students</h3>
                    <p>View students enrolled in your courses</p>
                </a>
                
                <a href="manage_reports" class="action-card orange">
                    <i class="fas fa-chart-line"></i>
                    <h3>Manage Reports</h3>
                    <p>Generate and print student performance reports</p>
                </a>
                
                <a href="profile" class="action-card green">
                    <i class="fas fa-user"></i>
                    <h3>View Profile</h3>
                    <p>View and update your profile information</p>
                </a>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="recent-activity">
            <h2><i class="fas fa-clock"></i> Recent Activity</h2>
            <div class="activity-list">
                <div class="activity-item">
                    <div class="activity-icon green">
                        <i class="fas fa-upload"></i>
                    </div>
                    <div class="activity-content">
                        <h4>Results uploaded</h4>
                        <p>CA results for Course XYZ uploaded successfully</p>
                        <span class="activity-time">1 hour ago</span>
                    </div>
                </div>
                
                <div class="activity-item">
                    <div class="activity-icon orange">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="activity-content">
                        <h4>Report generated</h4>
                        <p>Performance report for Semester 1 printed</p>
                        <span class="activity-time">3 hours ago</span>
                    </div>
                </div>
                
                <div class="activity-item">
                    <div class="activity-icon green">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="activity-content">
                        <h4>Students viewed</h4>
                        <p>Enrolled students list for Course ABC accessed</p>
                        <span class="activity-time">5 hours ago</span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script> <!-- Reuse script, adjust if needed -->
</body>
</html>