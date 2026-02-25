<?php
session_start();
require_once '../config.php';
require_once '../auth/auth.php';

// Check if user is logged in and has lecturer role
if (!currentUserId() || !currentUserHasRole('Lecturer', $pdo)) {
    header('Location: ../auth/login.php');
    exit();
}

// Get lecturer profile
$stmt = $pdo->prepare("SELECT sp.*, u.email, u.contact FROM staff_profile sp JOIN users u ON sp.user_id = u.id WHERE sp.user_id = ?");
$stmt->execute([currentUserId()]);
$lecturer = $stmt->fetch();

if (!$lecturer) {
    // Lecturer profile not found
    header('Location: ../auth/login.php');
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
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($lecturer['full_name'] ?? 'Lecturer'); ?></span>
                <span class="user-id">(<?php echo htmlspecialchars($lecturer['staff_id'] ?? 'N/A'); ?>)</span>
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
            <h3><i class="fas fa-chalkboard-teacher"></i> Lecturer Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Academic</h4>
                <a href="view_students.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>View Students</span>
                </a>
                <a href="upload_results.php" class="nav-item">
                    <i class="fas fa-upload"></i>
                    <span>Upload Results</span>
                </a>
                <a href="manage_reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Manage Reports</span>
                </a>
                <a href="elearning.php" class="nav-item active">
                    <i class="fas fa-graduation-cap"></i>
                    <span>E-Learning (Moodle)</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Resources</h4>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-graduation-cap"></i> E-Learning Platform Access</h1>
            <p>Access the Moodle learning management system for online courses and teaching resources</p>
        </div>
        
        <div class="elearning-container">
            <div class="elearning-info">
                <div class="info-card">
                    <i class="fas fa-info-circle"></i>
                    <h3>About Our E-Learning Platform</h3>
                    <p>Lusaka South College uses Moodle as its primary e-learning platform. As a lecturer, you can manage your courses, upload resources, grade assignments, and communicate with students through this platform.</p>
                </div>
                
                <div class="access-card">
                    <i class="fas fa-external-link-alt"></i>
                    <h3>Access Moodle</h3>
                    <p>Click the button below to access the e-learning platform. Your Moodle account is linked to your lecturer portal credentials.</p>
                    
                    <a href="https://moodle.lsuclms.com/login/index.php" target="_blank" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Go to Moodle Platform
                    </a>
                </div>
                
                <div class="tips-card">
                    <i class="fas fa-lightbulb"></i>
                    <h3>Teaching Resources</h3>
                    <ul>
                        <li>Create and manage online courses</li>
                        <li>Upload course materials and resources</li>
                        <li>Post assignments and track submissions</li>
                        <li>Grade student work and provide feedback</li>
                        <li>Communicate with students through forums and messaging</li>
                        <li>Track student progress and engagement</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script>
        // Make sure the current page is highlighted in the sidebar
        document.addEventListener('DOMContentLoaded', function() {
            // Remove active class from all nav items
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => item.classList.remove('active'));
            
            // Add active class to current page
            const currentPage = document.querySelector('.nav-item[href="elearning.php"]');
            if (currentPage) {
                currentPage.classList.add('active');
            }
        });
    </script>
</body>
</html>