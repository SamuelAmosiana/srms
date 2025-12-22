<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

// Check if user has Academics Coordinator role
requireRole('Academics Coordinator', $pdo);

// Get admin profile
$stmt = $pdo->prepare("SELECT ap.full_name, ap.staff_id FROM admin_profile ap WHERE ap.user_id = ?");
$stmt->execute([currentUserId()]);
$admin = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academics Dashboard - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Dashboard Stats */
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--white);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px var(--shadow);
            display: flex;
            align-items: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 16px var(--shadow);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 24px;
            color: white;
        }
        
        .stat-content h3 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-dark);
        }
        
        .stat-content p {
            color: var(--text-light);
            margin: 0;
            font-size: 14px;
        }
        
        /* Card Styles */
        .card {
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 8px var(--shadow);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            background-color: var(--primary-green);
            color: white;
        }
        
        .card-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 500;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Menu Grid */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .menu-item {
            display: flex;
            align-items: flex-start;
            padding: 15px;
            border-radius: 6px;
            text-decoration: none;
            transition: background-color 0.3s ease;
            border: 1px solid var(--border-color);
        }
        
        .menu-item:hover {
            background-color: var(--background-light);
            border-color: var(--primary-green);
        }
        
        .menu-icon {
            width: 40px;
            height: 40px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 18px;
            color: white;
            flex-shrink: 0;
        }
        
        .menu-text h4 {
            margin: 0 0 5px 0;
            font-size: 15px;
            color: var(--text-dark);
        }
        
        .menu-text p {
            margin: 0;
            font-size: 13px;
            color: var(--text-light);
            line-height: 1.4;
        }
        
        /* Activity List */
        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .activity-list li {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .activity-list li:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 14px;
            color: white;
            flex-shrink: 0;
        }
        
        .activity-content p {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: var(--text-dark);
        }
        
        .activity-content small {
            color: var(--text-light);
        }
        
        /* Utility Classes */
        .mt-4 {
            margin-top: 20px;
        }
        
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        
        .col-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 0 10px;
        }
        
        /* Background Colors */
        .bg-blue { background-color: #4285f4; }
        .bg-green { background-color: #34a853; }
        .bg-orange { background-color: #fbbc05; }
        .bg-purple { background-color: #673ab7; }
        .bg-red { background-color: #ea4335; }
        .bg-teal { background-color: #00acc1; }
        .bg-indigo { background-color: #5c6bc0; }
        .bg-pink { background-color: #e91e63; }
        
        /* Responsive */
        @media (max-width: 992px) {
            .col-6 {
                flex: 0 0 100%;
                max-width: 100%;
                margin-bottom: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .menu-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 576px) {
            .dashboard-stats {
                grid-template-columns: 1fr;
            }
            
            .stat-card {
                flex-direction: column;
                text-align: center;
            }
            
            .stat-icon {
                margin-right: 0;
                margin-bottom: 10px;
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
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($admin['full_name'] ?? 'Academics Coordinator'); ?></span>
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
            <h3><i class="fas fa-graduation-cap"></i> Academics Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard" class="nav-item active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Academic Planning</h4>
                <a href="#" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Create Academic Calendars</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-clock"></i>
                    <span>Generate Timetables</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Schedule Programs & Exams</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Academic Operations</h4>
                <a href="#" class="nav-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Publish Results</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-user-clock"></i>
                    <span>Track Lecturer Attendance</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Approve Course Registration</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Reports</h4>
                <a href="#" class="nav-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Academic Reports</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Performance Analytics</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-graduation-cap"></i> Academics Dashboard</h1>
            <p>Welcome to the Academics Coordinator Panel</p>
        </div>
        
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon bg-blue">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3>12</h3>
                    <p>Active Programs</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon bg-green">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-content">
                    <h3>142</h3>
                    <p>Courses Managed</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon bg-orange">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3>8</h3>
                    <p>Lecturers</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon bg-purple">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <h3>89%</h3>
                    <p>Results Processed</p>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-tasks"></i> Academic Operations</h3>
                    </div>
                    <div class="card-body">
                        <div class="menu-grid">
                            <a href="#" class="menu-item">
                                <div class="menu-icon bg-blue">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="menu-text">
                                    <h4>Create Academic Calendars</h4>
                                    <p>Define semesters, holidays, and key dates</p>
                                </div>
                            </a>
                            
                            <a href="#" class="menu-item">
                                <div class="menu-icon bg-green">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="menu-text">
                                    <h4>Generate Timetables</h4>
                                    <p>Automated schedule creation</p>
                                </div>
                            </a>
                            
                            <a href="#" class="menu-item">
                                <div class="menu-icon bg-orange">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="menu-text">
                                    <h4>Schedule Programs & Exams</h4>
                                    <p>Plan events and examinations</p>
                                </div>
                            </a>
                            
                            <a href="#" class="menu-item">
                                <div class="menu-icon bg-purple">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="menu-text">
                                    <h4>Publish Results</h4>
                                    <p>Upload and verify student results</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-6">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-cogs"></i> Administrative Functions</h3>
                    </div>
                    <div class="card-body">
                        <div class="menu-grid">
                            <a href="#" class="menu-item">
                                <div class="menu-icon bg-red">
                                    <i class="fas fa-user-clock"></i>
                                </div>
                                <div class="menu-text">
                                    <h4>Track Lecturer Attendance</h4>
                                    <p>Monitor attendance and generate reports</p>
                                </div>
                            </a>
                            
                            <a href="#" class="menu-item">
                                <div class="menu-icon bg-teal">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <div class="menu-text">
                                    <h4>Approve Course Registration</h4>
                                    <p>Review student registrations</p>
                                </div>
                            </a>
                            
                            <a href="#" class="menu-item">
                                <div class="menu-icon bg-indigo">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="menu-text">
                                    <h4>Academic Reports</h4>
                                    <p>Generate performance reports</p>
                                </div>
                            </a>
                            
                            <a href="#" class="menu-item">
                                <div class="menu-icon bg-pink">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                                <div class="menu-text">
                                    <h4>Performance Analytics</h4>
                                    <p>Analyze academic trends</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h3><i class="fas fa-bell"></i> Recent Activities</h3>
                    </div>
                    <div class="card-body">
                        <ul class="activity-list">
                            <li>
                                <div class="activity-icon bg-green">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="activity-content">
                                    <p>Approved 15 course registrations</p>
                                    <small class="text-muted">2 hours ago</small>
                                </div>
                            </li>
                            <li>
                                <div class="activity-icon bg-blue">
                                    <i class="fas fa-calendar-plus"></i>
                                </div>
                                <div class="activity-content">
                                    <p>Updated academic calendar for 2024</p>
                                    <small class="text-muted">1 day ago</small>
                                </div>
                            </li>
                            <li>
                                <div class="activity-icon bg-orange">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="activity-content">
                                    <p>Published semester results</p>
                                    <small class="text-muted">2 days ago</small>
                                </div>
                            </li>
                        </ul>
                    </div>
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
    </script>
</body>
</html>