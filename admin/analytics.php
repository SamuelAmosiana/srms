<?php
session_start();
require_once '../config.php';
require_once '../auth.php';

// Check if user is logged in and has admin role
if (!currentUserId()) {
    header('Location: ../login.php');
    exit();
}

requireRole('Super Admin', $pdo);

// Get admin profile
$stmt = $pdo->prepare("SELECT ap.full_name, ap.staff_id FROM admin_profile ap WHERE ap.user_id = ?");
$stmt->execute([currentUserId()]);
$admin = $stmt->fetch();

// Fetch analytics data
$analytics = [];

// 1. User Statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$analytics['total_users'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM student_profile");
$analytics['total_students'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM staff_profile");
$analytics['total_staff'] = $stmt->fetch()['total'];

// 2. Academic Statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM programme");
$analytics['total_programmes'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM course");
$analytics['total_courses'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM school");
$analytics['total_schools'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM department");
$analytics['total_departments'] = $stmt->fetch()['total'];

// 3. Enrollment Statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM course_enrollment");
$analytics['total_enrollments'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM course_enrollment GROUP BY status");
$enrollmentStatus = $stmt->fetchAll();
$analytics['enrollment_by_status'] = [];
foreach ($enrollmentStatus as $row) {
    $analytics['enrollment_by_status'][$row['status']] = $row['count'];
}

// 4. Results Statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM results");
$analytics['total_results'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT grade, COUNT(*) as count FROM results WHERE grade IS NOT NULL GROUP BY grade ORDER BY grade");
$grades = $stmt->fetchAll();
$analytics['grades_distribution'] = [];
foreach ($grades as $row) {
    $analytics['grades_distribution'][$row['grade']] = $row['count'];
}

// 5. Recent Activity (last 7 days)
$stmt = $pdo->query("SELECT DATE(created_at) as date, COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY date");
$dailyRegistrations = $stmt->fetchAll();
$analytics['daily_registrations'] = [];
foreach ($dailyRegistrations as $row) {
    $analytics['daily_registrations'][$row['date']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - LSC SRMS</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <a href="analytics.php" class="nav-item active">
                    <i class="fas fa-analytics"></i>
                    <span>Analytics</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-analytics"></i> Analytics Dashboard</h1>
            <p>Comprehensive system analytics and insights</p>
        </div>

        <!-- Overview Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($analytics['total_users']); ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($analytics['total_students']); ?></h3>
                    <p>Students</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($analytics['total_staff']); ?></h3>
                    <p>Staff Members</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($analytics['total_courses']); ?></h3>
                    <p>Courses</p>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-chart-pie"></i> System Analytics</h3>
            </div>
            <div class="panel-content">
                <div class="charts-grid">
                    <!-- Enrollment Status Chart -->
                    <div class="chart-container">
                        <h4>Enrollment Status Distribution</h4>
                        <canvas id="enrollmentChart"></canvas>
                    </div>
                    
                    <!-- Grades Distribution Chart -->
                    <div class="chart-container">
                        <h4>Grades Distribution</h4>
                        <canvas id="gradesChart"></canvas>
                    </div>
                    
                    <!-- Daily Registrations Chart -->
                    <div class="chart-container">
                        <h4>Daily User Registrations (Last 7 Days)</h4>
                        <canvas id="registrationsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Statistics -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-table"></i> Detailed Statistics</h3>
            </div>
            <div class="panel-content">
                <div class="stats-details-grid">
                    <div class="detail-card">
                        <h4>Academic Structure</h4>
                        <ul>
                            <li><strong><?php echo $analytics['total_schools']; ?></strong> Schools</li>
                            <li><strong><?php echo $analytics['total_departments']; ?></strong> Departments</li>
                            <li><strong><?php echo $analytics['total_programmes']; ?></strong> Programmes</li>
                        </ul>
                    </div>
                    
                    <div class="detail-card">
                        <h4>Enrollment Statistics</h4>
                        <ul>
                            <li><strong><?php echo $analytics['total_enrollments']; ?></strong> Total Enrollments</li>
                            <li><strong><?php echo $analytics['enrollment_by_status']['enrolled'] ?? 0; ?></strong> Active Enrollments</li>
                            <li><strong><?php echo $analytics['enrollment_by_status']['pending'] ?? 0; ?></strong> Pending Approvals</li>
                        </ul>
                    </div>
                    
                    <div class="detail-card">
                        <h4>Academic Results</h4>
                        <ul>
                            <li><strong><?php echo $analytics['total_results']; ?></strong> Results Recorded</li>
                            <li><strong><?php echo count($analytics['grades_distribution']); ?></strong> Grade Types</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <style>
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .chart-container {
            background: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px var(--shadow);
        }
        
        .chart-container h4 {
            margin-top: 0;
            color: var(--primary-green);
            text-align: center;
        }
        
        .stats-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .detail-card {
            background: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px var(--shadow);
        }
        
        .detail-card h4 {
            margin-top: 0;
            color: var(--primary-green);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }
        
        .detail-card ul {
            list-style-type: none;
            padding: 0;
        }
        
        .detail-card li {
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
        }
        
        .detail-card li:last-child {
            border-bottom: none;
        }
        
        .detail-card strong {
            color: var(--primary-orange);
        }
        
        @media (max-width: 768px) {
            .charts-grid, .stats-details-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script>
        // Enrollment Status Chart
        const enrollmentCtx = document.getElementById('enrollmentChart').getContext('2d');
        const enrollmentChart = new Chart(enrollmentCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_keys($analytics['enrollment_by_status'])); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($analytics['enrollment_by_status'])); ?>,
                    backgroundColor: [
                        '#228B22',
                        '#FF8C00',
                        '#FF6600',
                        '#32CD32'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Grades Distribution Chart
        const gradesCtx = document.getElementById('gradesChart').getContext('2d');
        const gradesChart = new Chart(gradesCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($analytics['grades_distribution'])); ?>,
                datasets: [{
                    label: 'Number of Results',
                    data: <?php echo json_encode(array_values($analytics['grades_distribution'])); ?>,
                    backgroundColor: '#228B22'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Daily Registrations Chart
        const registrationsCtx = document.getElementById('registrationsChart').getContext('2d');
        const registrationsChart = new Chart(registrationsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_keys($analytics['daily_registrations'])); ?>,
                datasets: [{
                    label: 'New Users',
                    data: <?php echo json_encode(array_values($analytics['daily_registrations'])); ?>,
                    borderColor: '#228B22',
                    backgroundColor: 'rgba(34, 139, 34, 0.1)',
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>