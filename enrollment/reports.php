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

// Get report data
// Overall statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM applications");
$stmt->execute();
$totalApplications = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM applications WHERE status = 'pending'");
$stmt->execute();
$pendingApplications = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM applications WHERE status = 'approved'");
$stmt->execute();
$approvedApplications = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM applications WHERE status = 'rejected'");
$stmt->execute();
$rejectedApplications = $stmt->fetch()['total'];

// Applications by category - updated logic to match actual programme names
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN p.name LIKE '%Business%' OR p.name LIKE '%Admin%' OR p.name LIKE '%Diploma%' THEN 1 ELSE 0 END) as undergrad,
        SUM(CASE WHEN p.name LIKE '%Computer%' OR p.name LIKE '%IT%' OR p.name LIKE '%Certificate%' THEN 1 ELSE 0 END) as short_courses,
        SUM(CASE WHEN p.name LIKE '%Corporate%' OR p.name LIKE '%Training%' OR a.documents LIKE '%corporate_training%' THEN 1 ELSE 0 END) as corporate
    FROM applications a
    LEFT JOIN programme p ON a.programme_id = p.id
");
$stmt->execute();
$categoryStats = $stmt->fetch();

// Monthly enrollment trend
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
    FROM applications 
    GROUP BY month 
    ORDER BY month DESC 
    LIMIT 12
");
$stmt->execute();
$monthlyTrend = $stmt->fetchAll();

// Recent applications
$stmt = $pdo->prepare("
    SELECT a.*, p.name as programme_name, i.name as intake_name,
           CASE 
               WHEN p.name LIKE '%Business%' OR p.name LIKE '%Admin%' OR p.name LIKE '%Diploma%' THEN 'Undergraduate'
               WHEN p.name LIKE '%Computer%' OR p.name LIKE '%IT%' OR p.name LIKE '%Certificate%' THEN 'Short Course'
               WHEN p.name LIKE '%Corporate%' OR p.name LIKE '%Training%' OR a.documents LIKE '%corporate_training%' THEN 'Corporate Training'
               ELSE 'Other'
           END as category
    FROM applications a
    LEFT JOIN programme p ON a.programme_id = p.id
    LEFT JOIN intake i ON a.intake_id = i.id
    ORDER BY a.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recentApplications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Reports - LSC SRMS</title>
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
            <a href="dashboard.php" class="nav-item">
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
                <h4>Reports</h4>
                <a href="reports.php" class="nav-item active">
                    <i class="fas fa-chart-bar"></i>
                    <span>Enrollment Reports</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-chart-bar"></i> Enrollment Reports</h1>
            <p>Track and analyze enrollment application trends</p>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($totalApplications); ?></h3>
                    <p>Total Applications</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($pendingApplications); ?></h3>
                    <p>Pending Applications</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($approvedApplications); ?></h3>
                    <p>Approved Applications</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($rejectedApplications); ?></h3>
                    <p>Rejected Applications</p>
                </div>
            </div>
        </div>
        
        <!-- Category Breakdown -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-folder-open"></i> Applications by Category</h3>
            </div>
            <div class="panel-content">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($categoryStats['undergrad'] ?? 0); ?></h3>
                            <p>Undergraduate</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon yellow">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($categoryStats['short_courses'] ?? 0); ?></h3>
                            <p>Short Courses</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon teal">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($categoryStats['corporate'] ?? 0); ?></h3>
                            <p>Corporate Training</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Monthly Trend -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-chart-line"></i> Monthly Enrollment Trend</h3>
            </div>
            <div class="panel-content">
                <?php if (empty($monthlyTrend)): ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-line"></i>
                        <p>No enrollment data available</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Total Applications</th>
                                    <th>Approved</th>
                                    <th>Approval Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthlyTrend as $trend): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($trend['month']); ?></td>
                                        <td><?php echo number_format($trend['total']); ?></td>
                                        <td><?php echo number_format($trend['approved']); ?></td>
                                        <td>
                                            <?php 
                                            $rate = $trend['total'] > 0 ? round(($trend['approved'] / $trend['total']) * 100, 1) : 0;
                                            echo $rate . '%';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Applications -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-history"></i> Recent Applications</h3>
            </div>
            <div class="panel-content">
                <?php if (empty($recentApplications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <p>No recent applications</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Category</th>
                                    <th>Programme</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentApplications as $app): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($app['email']); ?></td>
                                        <td><?php echo htmlspecialchars($app['category']); ?></td>
                                        <td><?php echo htmlspecialchars($app['programme_name']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $app['status']; ?>">
                                                <?php echo ucfirst($app['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('Y-m-d', strtotime($app['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
</body>
</html>