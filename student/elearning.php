<?php
session_start();
require_once '../config.php';
require_once '../auth/auth.php';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Learning Access - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            <a href="dashboard" class="nav-item">
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
                <a href="elearning" class="nav-item active">
                    <i class="fas fa-graduation-cap"></i>
                    <span>E-Learning (Moodle)</span>
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
            <h1><i class="fas fa-graduation-cap"></i> E-Learning Platform Access</h1>
            <p>Access the Moodle learning management system for online courses and resources</p>
        </div>
        
        <div class="elearning-container">
            <div class="elearning-info">
                <div class="info-card">
                    <i class="fas fa-info-circle"></i>
                    <h3>About Our E-Learning Platform</h3>
                    <p>Lusaka South College uses Moodle as its primary e-learning platform. Access your online courses, resources, assignments, and participate in discussions with your peers and lecturers.</p>
                </div>
                
                <div class="access-card">
                    <i class="fas fa-external-link-alt"></i>
                    <h3>Access Moodle</h3>
                    <p>Click the button below to access the e-learning platform. Your Moodle account is linked to your student portal credentials.</p>
                    
                    <a href="https://moodle.lsuclms.com/login/index.php" target="_blank" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Go to Moodle Platform
                    </a>
                </div>
                
                <div class="tips-card">
                    <i class="fas fa-lightbulb"></i>
                    <h3>Helpful Tips</h3>
                    <ul>
                        <li>Access course materials, assignments, and resources</li>
                        <li>Participate in online discussions and forums</li>
                        <li>Submit assignments and check grades online</li>
                        <li>Access lecture recordings and supplementary materials</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/student-dashboard.js"></script>
    <script>
        // Make sure the current page is highlighted in the sidebar
        document.addEventListener('DOMContentLoaded', function() {
            // Remove active class from all nav items
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => item.classList.remove('active'));
            
            // Add active class to current page
            const currentPage = document.querySelector('.nav-item[href="elearning"]');
            if (currentPage) {
                currentPage.classList.add('active');
            }
        });
    </script>
</body>
</html>