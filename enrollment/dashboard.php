<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in and has enrollment officer role
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

requireRole('Enrollment Officer', $pdo);

// Get enrollment officer profile
$stmt = $pdo->prepare("SELECT sp.full_name, sp.staff_id FROM staff_profile sp WHERE sp.user_id = ?");
$stmt->execute([currentUserId()]);
$enrollmentOfficer = $stmt->fetch();

// Get dashboard statistics
$stats = [];

// Count pending applications by type
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN p.name LIKE '%Business%' OR p.name LIKE '%Admin%' THEN 1 ELSE 0 END) as undergrad,
        SUM(CASE WHEN p.name LIKE '%Computer%' OR p.name LIKE '%IT%' THEN 1 ELSE 0 END) as short_courses,
        SUM(CASE WHEN p.name LIKE '%Corporate%' OR p.name LIKE '%Training%' THEN 1 ELSE 0 END) as corporate
    FROM applications a
    LEFT JOIN programme p ON a.programme_id = p.id
    WHERE a.status = 'pending'
");
$stmt->execute();
$stats['pending_applications'] = $stmt->fetch();

// Count approved applications
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM applications WHERE status = 'approved'");
$stmt->execute(); // Fixed: Added execute() before fetch()
$stats['approved_applications'] = $stmt->fetchColumn(); // Fixed: Using fetchColumn() for single value

// Count rejected applications
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM applications WHERE status = 'rejected'");
$stmt->execute(); // Fixed: Added execute() before fetch()
$stats['rejected_applications'] = $stmt->fetchColumn(); // Fixed: Using fetchColumn() for single value

// Get pending applications by category
$stmt = $pdo->prepare("
    SELECT a.*, p.name as programme_name, i.name as intake_name,
           CASE 
               WHEN p.name LIKE '%Business%' OR p.name LIKE '%Admin%' OR p.name LIKE '%Diploma%' THEN 'undergraduate'
               WHEN p.name LIKE '%Computer%' OR p.name LIKE '%IT%' OR p.name LIKE '%Certificate%' THEN 'short_course'
               WHEN p.name LIKE '%Corporate%' OR p.name LIKE '%Training%' THEN 'corporate'
               ELSE 'other'
           END as category
    FROM applications a
    LEFT JOIN programme p ON a.programme_id = p.id
    LEFT JOIN intake i ON a.intake_id = i.id
    WHERE a.status = 'pending'
    ORDER BY a.created_at DESC
");
$stmt->execute();
$pendingApplications = $stmt->fetchAll();

// Group applications by category
$applicationsByCategory = [
    'undergraduate' => [],
    'short_course' => [],
    'corporate' => [],
    'other' => []
];

foreach ($pendingApplications as $application) {
    $category = $application['category'];
    if (!isset($applicationsByCategory[$category])) {
        $applicationsByCategory[$category] = [];
    }
    $applicationsByCategory[$category][] = $application;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Dashboard - LSC SRMS</title>
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
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($enrollmentOfficer['full_name'] ?? 'Enrollment Officer'); ?></span>
                <span class="staff-id">(<?php echo htmlspecialchars($enrollmentOfficer['staff_id'] ?? 'N/A'); ?>)</span>
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
            <h3><i class="fas fa-tachometer-alt"></i> Enrollment Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Applications</h4>
                <a href="undergraduate_applications.php" class="nav-item">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Undergraduate</span>
                </a>
                <a href="short_courses_applications.php" class="nav-item">
                    <i class="fas fa-book"></i>
                    <span>Short Courses</span>
                </a>
                <a href="corporate_training_applications.php" class="nav-item">
                    <i class="fas fa-building"></i>
                    <span>Corporate Training</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>My Approvals</h4>
                <a href="my_approvals.php" class="nav-item">
                    <i class="fas fa-thumbs-up"></i>
                    <span>My Approvals</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Reports</h4>
                <a href="reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Enrollment Reports</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-tachometer-alt"></i> Enrollment Dashboard</h1>
            <p>Manage student enrollment applications</p>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['pending_applications']['total']); ?></h3>
                    <p>Pending Applications</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['approved_applications']); ?></h3>
                    <p>Approved Applications</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['rejected_applications']); ?></h3>
                    <p>Rejected Applications</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['pending_applications']['undergrad'] ?? 0); ?></h3>
                    <p>Undergraduate</p>
                </div>
            </div>
        </div>
        
        <!-- Applications by Category -->
        <div class="applications-section">
            <h2><i class="fas fa-folder-open"></i> Applications by Category</h2>
            
            <!-- Undergraduate Applications -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3><i class="fas fa-graduation-cap"></i> Undergraduate Applications (<?php echo count($applicationsByCategory['undergraduate']); ?>)</h3>
                </div>
                <div class="panel-content">
                    <?php if (empty($applicationsByCategory['undergraduate'])): ?>
                        <div class="empty-state">
                            <i class="fas fa-graduation-cap"></i>
                            <p>No pending undergraduate applications</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Programme</th>
                                        <th>Intake</th>
                                        <th>Submitted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($applicationsByCategory['undergraduate'] as $app): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($app['email']); ?></td>
                                            <td><?php echo htmlspecialchars($app['programme_name']); ?></td>
                                            <td><?php echo htmlspecialchars($app['intake_name']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($app['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="viewApplication(<?php echo htmlspecialchars(json_encode($app)); ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Short Courses Applications -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3><i class="fas fa-book"></i> Short Courses Applications (<?php echo count($applicationsByCategory['short_course']); ?>)</h3>
                </div>
                <div class="panel-content">
                    <?php if (empty($applicationsByCategory['short_course'])): ?>
                        <div class="empty-state">
                            <i class="fas fa-book"></i>
                            <p>No pending short courses applications</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Programme</th>
                                        <th>Intake</th>
                                        <th>Submitted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($applicationsByCategory['short_course'] as $app): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($app['email']); ?></td>
                                            <td><?php echo htmlspecialchars($app['programme_name']); ?></td>
                                            <td><?php echo htmlspecialchars($app['intake_name']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($app['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="viewApplication(<?php echo htmlspecialchars(json_encode($app)); ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Corporate Training Applications -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3><i class="fas fa-building"></i> Corporate Training Applications (<?php echo count($applicationsByCategory['corporate']); ?>)</h3>
                </div>
                <div class="panel-content">
                    <?php if (empty($applicationsByCategory['corporate'])): ?>
                        <div class="empty-state">
                            <i class="fas fa-building"></i>
                            <p>No pending corporate training applications</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Programme</th>
                                        <th>Intake</th>
                                        <th>Submitted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($applicationsByCategory['corporate'] as $app): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($app['email']); ?></td>
                                            <td><?php echo htmlspecialchars($app['programme_name']); ?></td>
                                            <td><?php echo htmlspecialchars($app['intake_name']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($app['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="viewApplication(<?php echo htmlspecialchars(json_encode($app)); ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script>
        function viewApplication(app) {
            // In a real implementation, this would open a modal with application details
            // and options to approve or reject
            alert('Viewing application for: ' + app.full_name + '\nThis would open a detailed view in a real implementation.');
        }
    </script>
</body>
</html>