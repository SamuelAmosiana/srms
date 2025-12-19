<?php
session_start();
require_once '../config.php';
require_once '../auth.php';

// Check if user is logged in and has student role
if (!currentUserId() || !currentUserHasRole('Student', $pdo)) {
    header('Location: ../student_login.php');
    exit();
}

// Get student profile
$stmt = $pdo->prepare("SELECT sp.*, u.email, u.contact, p.name as programme_name, i.name as intake_name FROM student_profile sp JOIN users u ON sp.user_id = u.id LEFT JOIN programme p ON sp.programme_id = p.id LEFT JOIN intake i ON sp.intake_id = i.id WHERE sp.user_id = ?");
$stmt->execute([currentUserId()]);
$student = $stmt->fetch();

if (!$student) {
    // Student profile not found
    header('Location: ../student_login.php');
    exit();
}

// Get dashboard statistics
$stats = [];

// Count enrolled courses
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM course_enrollment WHERE student_user_id = ?");
$stmt->execute([currentUserId()]);
$stats['enrolled_courses'] = $stmt->fetch()['count'];

// Get current GPA (calculate from results table with proper grade conversion)
$stmt = $pdo->prepare("
    SELECT AVG(
        CASE 
            WHEN r.grade = 'A' THEN 4.0
            WHEN r.grade = 'B+' THEN 3.5
            WHEN r.grade = 'B' THEN 3.0
            WHEN r.grade = 'C+' THEN 2.5
            WHEN r.grade = 'C' THEN 2.0
            WHEN r.grade = 'D+' THEN 1.5
            WHEN r.grade = 'D' THEN 1.0
            ELSE 0
        END
    ) as gpa 
    FROM results r
    INNER JOIN course_enrollment ce ON r.enrollment_id = ce.id
    WHERE ce.student_user_id = ?
");
$stmt->execute([currentUserId()]);
$stats['gpa'] = number_format($stmt->fetch()['gpa'] ?? 0, 2);

// Get fee balance from student profile
$stats['fee_balance'] = $student['balance'] ?? 0;

// Get accommodation status (no table exists yet, placeholder)
$stats['accommodation_status'] = 'Not Applied';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Font Awesome icons are now used throughout the application */
    </style>
</head>
<body class="student-layout" data-theme="light">
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
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($student['full_name'] ?? 'Student'); ?></span>
                <span class="student-id">(<?php echo htmlspecialchars($student['student_number'] ?? 'N/A'); ?>)</span>
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
            <h3><i class="fas fa-tachometer-alt"></i> Student Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard" class="nav-item active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Academic</h4>
                <a href="view_results" class="nav-item">
                    <i class="fas fa-chart-line"></i>
                    <span>View Results</span>
                </a>
                <a href="register_courses" class="nav-item">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Register Courses</span>
                </a>
                <a href="view_docket" class="nav-item">
                    <i class="fas fa-file-alt"></i>
                    <span>View Docket</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Finance & Accommodation</h4>
                <a href="view_fee_balance" class="nav-item">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>View Fee Balance</span>
                </a>
                <a href="accommodation" class="nav-item">
                    <i class="fas fa-bed"></i>
                    <span>Accommodation</span>
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
                    <h3><?php echo number_format($stats['enrolled_courses']); ?></h3>
                    <p>Enrolled Courses</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['gpa']; ?></h3>
                    <p>Current GPA</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-info">
                    <h3>K<?php echo number_format($stats['fee_balance'], 2); ?></h3>
                    <p>Fee Balance</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-bed"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo htmlspecialchars($stats['accommodation_status']); ?></h3>
                    <p>Accommodation Status</p>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
            <div class="actions-grid">
                <a href="view_results" class="action-card orange">
                    <i class="fas fa-chart-line"></i>
                    <h3>View Results</h3>
                    <p>Check your CA and exam results</p>
                </a>
                
                <a href="view_fee_balance" class="action-card green">
                    <i class="fas fa-money-bill-wave"></i>
                    <h3>View Fee Balance</h3>
                    <p>Check your outstanding fees</p>
                </a>
                
                <a href="view_docket" class="action-card orange">
                    <i class="fas fa-file-alt"></i>
                    <h3>View Docket</h3>
                    <p>Access your academic docket</p>
                </a>
                
                <a href="register_courses" class="action-card green">
                    <i class="fas fa-clipboard-check"></i>
                    <h3>Register Courses</h3>
                    <p>Register for your courses</p>
                </a>
                
                <a href="accommodation" class="action-card orange">
                    <i class="fas fa-bed"></i>
                    <h3>Accommodation</h3>
                    <p>Apply or view status</p>
                </a>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="recent-activity">
            <h2><i class="fas fa-clock"></i> Recent Activity</h2>
            <div class="activity-list">
                <div class="activity-item">
                    <div class="activity-icon green">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="activity-content">
                        <h4>Course Registered</h4>
                        <p>Successfully registered for new course</p>
                        <span class="activity-time">2 hours ago</span>
                    </div>
                </div>
                
                <div class="activity-item">
                    <div class="activity-icon orange">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="activity-content">
                        <h4>Results Updated</h4>
                        <p>New exam results available</p>
                        <span class="activity-time">4 hours ago</span>
                    </div>
                </div>
                
                <div class="activity-item">
                    <div class="activity-icon green">
                        <i class="fas fa-bed"></i>
                    </div>
                    <div class="activity-content">
                        <h4>Accommodation Status</h4>
                        <p>Application status updated</p>
                        <span class="activity-time">6 hours ago</span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/student-dashboard.js"></script>
</body>
</html>