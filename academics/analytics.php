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

// Fetch analytics data
$analytics = [
    'total_students' => 0,
    'active_courses' => 0,
    'completed_courses' => 0,
    'lecturers' => 0,
    'attendance_rate' => 0,
    'pass_rate' => 0,
    'avg_gpa' => 0,
    'pending_registrations' => 0
];

try {
    // Get total students
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM registered_students");
    $analytics['total_students'] = $stmt->fetch()['count'];
    
    // Get active courses
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM course WHERE status = 'active'");
    $analytics['active_courses'] = $stmt->fetch()['count'];
    
    // Get completed courses
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM course WHERE status = 'completed'");
    $analytics['completed_courses'] = $stmt->fetch()['count'];
    
    // Get lecturers count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users u JOIN user_roles ur ON u.id = ur.user_id JOIN roles r ON ur.role_id = r.id WHERE r.name = 'Lecturer'");
    $analytics['lecturers'] = $stmt->fetch()['count'];
    
    // Get pending registrations
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM course_registration WHERE status = 'pending'");
    $analytics['pending_registrations'] = $stmt->fetch()['count'];
    
    // Get average attendance rate (from lecturer_attendance)
    $stmt = $pdo->query("
        SELECT 
            (COUNT(CASE WHEN status = 'Present' THEN 1 END) * 100.0 / COUNT(*)) as attendance_rate
        FROM lecturer_attendance
    ");
    $result = $stmt->fetch();
    $analytics['attendance_rate'] = round($result['attendance_rate'] ?? 0, 2);
    
    // Get pass rate (from results table)
    $stmt = $pdo->query("
        SELECT 
            (COUNT(CASE WHEN grade IN ('A', 'B', 'C', 'D') THEN 1 END) * 100.0 / COUNT(*)) as pass_rate
        FROM results
    ");
    $result = $stmt->fetch();
    $analytics['pass_rate'] = round($result['pass_rate'] ?? 0, 2);
    
    // Get average GPA (from results table)
    $stmt = $pdo->query("
        SELECT 
            AVG(CASE 
                WHEN grade = 'A' THEN 4.0
                WHEN grade = 'B' THEN 3.0
                WHEN grade = 'C' THEN 2.0
                WHEN grade = 'D' THEN 1.0
                ELSE 0
            END) as avg_gpa
        FROM results
    ");
    $result = $stmt->fetch();
    $analytics['avg_gpa'] = round($result['avg_gpa'] ?? 0, 2);
} catch (Exception $e) {
    // Handle error gracefully
    error_log("Error fetching analytics data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Analytics - Academics Dashboard</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .analytics-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .analytics-card {
            background: var(--white);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px var(--shadow);
            display: flex;
            align-items: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .analytics-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 16px var(--shadow);
        }
        
        .analytics-icon {
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
        
        .analytics-content h3 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-dark);
        }
        
        .analytics-content p {
            color: var(--text-light);
            margin: 0;
            font-size: 14px;
        }
        
        .bg-blue { background-color: #4285f4; }
        .bg-green { background-color: #34a853; }
        .bg-orange { background-color: #fbbc05; }
        .bg-purple { background-color: #673ab7; }
        .bg-red { background-color: #ea4335; }
        .bg-teal { background-color: #00897b; }
        .bg-pink { background-color: #e91e63; }
        .bg-indigo { background-color: #3f51b5; }
        
        .chart-container {
            background: var(--white);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px var(--shadow);
            margin-bottom: 30px;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .chart-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .chart-actions {
            display: flex;
            gap: 10px;
        }
        
        .chart-wrapper {
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            border-radius: 8px;
            position: relative;
        }
        
        .chart-placeholder {
            text-align: center;
            color: #6c757d;
        }
        
        .chart-placeholder i {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .analytics-section {
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 8px var(--shadow);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .section-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            background-color: var(--primary-green);
            color: white;
        }
        
        .section-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 500;
        }
        
        .section-body {
            padding: 20px;
        }
        
        .analytics-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .analytics-item {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            background: var(--white);
            transition: box-shadow 0.3s ease;
        }
        
        .analytics-item:hover {
            box-shadow: 0 4px 12px var(--shadow);
        }
        
        .analytics-item h3 {
            margin: 0 0 10px 0;
            color: var(--text-dark);
            font-size: 18px;
        }
        
        .analytics-item p {
            color: var(--text-light);
            margin: 0 0 15px 0;
            font-size: 14px;
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
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background-color: var(--primary-green);
            transition: width 0.3s ease;
        }
        
        .metric-value {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .metric-label {
            font-size: 14px;
            color: var(--text-light);
            margin-top: 5px;
        }
        
        .trend-up {
            color: #28a745;
        }
        
        .trend-down {
            color: #dc3545;
        }
        
        .trend-neutral {
            color: #6c757d;
        }
        
        @media (max-width: 768px) {
            .analytics-cards {
                grid-template-columns: 1fr;
            }
            
            .analytics-list {
                grid-template-columns: 1fr;
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
            <a href="dashboard" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Academic Planning</h4>
                <a href="create_calendar.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Create Academic Calendars</span>
                </a>
                <a href="timetable.php" class="nav-item">
                    <i class="fas fa-clock"></i>
                    <span>Generate Timetables</span>
                </a>
                <a href="schedule_exams.php" class="nav-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Schedule Programs & Exams</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Academic Operations</h4>
                <a href="publish_results.php" class="nav-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Publish Results</span>
                </a>
                <a href="lecturer_attendance.php" class="nav-item">
                    <i class="fas fa-user-clock"></i>
                    <span>Track Lecturer Attendance</span>
                </a>
                <a href="approve_registration.php" class="nav-item">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Approve Course Registration</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Reports</h4>
                <a href="reports.php" class="nav-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Academic Reports</span>
                </a>
                <a href="analytics.php" class="nav-item active">
                    <i class="fas fa-chart-bar"></i>
                    <span>Performance Analytics</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-chart-bar"></i> Performance Analytics</h1>
            <p>Comprehensive analytics dashboard for academic performance monitoring</p>
        </div>
        
        <div class="analytics-cards">
            <div class="analytics-card">
                <div class="analytics-icon bg-blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="analytics-content">
                    <h3><?php echo number_format($analytics['total_students']); ?></h3>
                    <p>Total Students</p>
                    <div class="metric-label">Enrolled across all programmes</div>
                </div>
            </div>
            
            <div class="analytics-card">
                <div class="analytics-icon bg-green">
                    <i class="fas fa-book"></i>
                </div>
                <div class="analytics-content">
                    <h3><?php echo number_format($analytics['active_courses']); ?></h3>
                    <p>Active Courses</p>
                    <div class="metric-label">Currently running courses</div>
                </div>
            </div>
            
            <div class="analytics-card">
                <div class="analytics-icon bg-orange">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="analytics-content">
                    <h3><?php echo number_format($analytics['completed_courses']); ?></h3>
                    <p>Completed Courses</p>
                    <div class="metric-label">Successfully completed courses</div>
                </div>
            </div>
            
            <div class="analytics-card">
                <div class="analytics-icon bg-purple">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="analytics-content">
                    <h3><?php echo number_format($analytics['lecturers']); ?></h3>
                    <p>Lecturers</p>
                    <div class="metric-label">Total teaching staff</div>
                </div>
            </div>
        </div>
        
        <div class="analytics-cards">
            <div class="analytics-card">
                <div class="analytics-icon bg-teal">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="analytics-content">
                    <h3><?php echo $analytics['pass_rate']; ?>%</h3>
                    <p>Pass Rate</p>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $analytics['pass_rate']; ?>%"></div>
                    </div>
                    <div class="metric-label">
                        <span class="trend-up"><i class="fas fa-arrow-up"></i> 2.5% from last term</span>
                    </div>
                </div>
            </div>
            
            <div class="analytics-card">
                <div class="analytics-icon bg-pink">
                    <i class="fas fa-star"></i>
                </div>
                <div class="analytics-content">
                    <h3><?php echo $analytics['avg_gpa']; ?></h3>
                    <p>Average GPA</p>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo ($analytics['avg_gpa'] / 4 * 100); ?>%"></div>
                    </div>
                    <div class="metric-label">
                        <span class="trend-up"><i class="fas fa-arrow-up"></i> 0.3 from last term</span>
                    </div>
                </div>
            </div>
            
            <div class="analytics-card">
                <div class="analytics-icon bg-indigo">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="analytics-content">
                    <h3><?php echo $analytics['attendance_rate']; ?>%</h3>
                    <p>Lecturer Attendance</p>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $analytics['attendance_rate']; ?>%"></div>
                    </div>
                    <div class="metric-label">
                        <span class="trend-neutral"><i class="fas fa-minus"></i> 0.2% from last term</span>
                    </div>
                </div>
            </div>
            
            <div class="analytics-card">
                <div class="analytics-icon bg-red">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="analytics-content">
                    <h3><?php echo number_format($analytics['pending_registrations']); ?></h3>
                    <p>Pending Registrations</p>
                    <div class="metric-label">Awaiting approval</div>
                </div>
            </div>
        </div>
        
        <div class="chart-container">
            <div class="chart-header">
                <div class="chart-title">Student Performance Trend</div>
                <div class="chart-actions">
                    <select class="form-control" style="width: auto; padding: 5px 10px; font-size: 14px;">
                        <option>Last 30 Days</option>
                        <option>Last 60 Days</option>
                        <option>Last 90 Days</option>
                        <option>Last Year</option>
                    </select>
                </div>
            </div>
            <div class="chart-wrapper">
                <div class="chart-placeholder">
                    <i class="fas fa-chart-line"></i>
                    <h3>Performance Trend Chart</h3>
                    <p>Interactive chart showing student performance trends over time</p>
                </div>
            </div>
        </div>
        
        <div class="chart-container">
            <div class="chart-header">
                <div class="chart-title">Course Completion Rate by Programme</div>
                <div class="chart-actions">
                    <select class="form-control" style="width: auto; padding: 5px 10px; font-size: 14px;">
                        <option>Current Academic Year</option>
                        <option>Previous Academic Year</option>
                        <option>Custom Range</option>
                    </select>
                </div>
            </div>
            <div class="chart-wrapper">
                <div class="chart-placeholder">
                    <i class="fas fa-chart-pie"></i>
                    <h3>Completion Rate Chart</h3>
                    <p>Visual representation of course completion rates by programme</p>
                </div>
            </div>
        </div>
        
        <div class="chart-container">
            <div class="chart-header">
                <div class="chart-title">Academic Performance by Course</div>
                <div class="chart-actions">
                    <select class="form-control" style="width: auto; padding: 5px 10px; font-size: 14px;">
                        <option>Top Performing</option>
                        <option>Bottom Performing</option>
                        <option>All Courses</option>
                    </select>
                </div>
            </div>
            <div class="chart-wrapper">
                <div class="chart-placeholder">
                    <i class="fas fa-chart-bar"></i>
                    <h3>Course Performance Chart</h3>
                    <p>Bar chart showing academic performance across different courses</p>
                </div>
            </div>
        </div>
        
        <div class="analytics-section">
            <div class="section-header">
                <h2><i class="fas fa-bullseye"></i> Key Performance Indicators</h2>
            </div>
            <div class="section-body">
                <div class="analytics-list">
                    <div class="analytics-item">
                        <h3><i class="fas fa-user-graduate"></i> Student Retention Rate</h3>
                        <p>Percentage of students who continue their studies from one term to the next</p>
                        <div class="metric-value">87.5%</div>
                        <div class="metric-label">
                            <span class="trend-up"><i class="fas fa-arrow-up"></i> 3.2% improvement</span>
                        </div>
                    </div>
                    
                    <div class="analytics-item">
                        <h3><i class="fas fa-book-open"></i> Course Completion Rate</h3>
                        <p>Percentage of enrolled students who successfully complete their courses</p>
                        <div class="metric-value">92.3%</div>
                        <div class="metric-label">
                            <span class="trend-up"><i class="fas fa-arrow-up"></i> 1.8% improvement</span>
                        </div>
                    </div>
                    
                    <div class="analytics-item">
                        <h3><i class="fas fa-graduation-cap"></i> Graduation Rate</h3>
                        <p>Percentage of students who complete their programme within the expected timeframe</p>
                        <div class="metric-value">78.9%</div>
                        <div class="metric-label">
                            <span class="trend-neutral"><i class="fas fa-minus"></i> 0.5% change</span>
                        </div>
                    </div>
                    
                    <div class="analytics-item">
                        <h3><i class="fas fa-user-check"></i> Student Satisfaction</h3>
                        <p>Average satisfaction score based on course evaluations and feedback</p>
                        <div class="metric-value">4.2/5.0</div>
                        <div class="metric-label">
                            <span class="trend-up"><i class="fas fa-arrow-up"></i> 0.3 improvement</span>
                        </div>
                    </div>
                    
                    <div class="analytics-item">
                        <h3><i class="fas fa-chalkboard-teacher"></i> Faculty Performance</h3>
                        <p>Overall performance rating of teaching staff based on evaluations</p>
                        <div class="metric-value">4.5/5.0</div>
                        <div class="metric-label">
                            <span class="trend-up"><i class="fas fa-arrow-up"></i> 0.2 improvement</span>
                        </div>
                    </div>
                    
                    <div class="analytics-item">
                        <h3><i class="fas fa-chart-line"></i> Academic Growth</h3>
                        <p>Year-over-year growth in academic performance metrics</p>
                        <div class="metric-value">+12.7%</div>
                        <div class="metric-label">
                            <span class="trend-up"><i class="fas fa-arrow-up"></i> Consistent growth</span>
                        </div>
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