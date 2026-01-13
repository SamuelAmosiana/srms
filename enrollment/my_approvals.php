<?php
require '../config.php';
require '../auth/auth.php';

// Check if user is logged in and has enrollment officer role
if (!currentUserId()) {
    header('Location: ../auth/login.php');
    exit;
}

requireRole('Enrollment Officer', $pdo);

// Create registered_students table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS registered_students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        student_name VARCHAR(150) NOT NULL,
        student_email VARCHAR(255) NOT NULL,
        student_number VARCHAR(20) UNIQUE,
        programme_name VARCHAR(255),
        intake_name VARCHAR(255),
        payment_amount DECIMAL(10,2),
        registration_type ENUM('course', 'first_time') NOT NULL,
        status ENUM('pending_notification', 'email_sent') DEFAULT 'pending_notification',
        email_sent_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    error_log("Error creating registered_students table: " . $e->getMessage());
}

// Handle CSV export for personal approvals
if (isset($_GET['export']) && $_GET['export'] === 'personal') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="my_approvals_' . date('Y-m-d') . '.csv"');
    
    // Get all applications processed by current enrollment officer
    $stmt = $pdo->prepare("
        SELECT a.*, a.phone, a.mode_of_learning, p.name as programme_name, i.name as intake_name, 
               CASE 
                   WHEN p.name LIKE '%Business%' OR p.name LIKE '%Admin%' OR p.name LIKE '%Diploma%' THEN 'Undergraduate'
                   WHEN p.name LIKE '%Computer%' OR p.name LIKE '%IT%' OR p.name LIKE '%Certificate%' THEN 'Short Course'
                   WHEN p.name LIKE '%Corporate%' OR p.name LIKE '%Training%' THEN 'Corporate Training'
                   ELSE 'Other'
               END as category,
               u.username as processed_by_username,
               sp.full_name as processed_by_name
        FROM applications a
        LEFT JOIN programme p ON a.programme_id = p.id
        LEFT JOIN intake i ON a.intake_id = i.id
        LEFT JOIN users u ON a.processed_by = u.id
        LEFT JOIN staff_profile sp ON u.id = sp.user_id
        WHERE a.processed_by = ?
        ORDER BY a.updated_at DESC
    ");
    $stmt->execute([currentUserId()]);
    $personalApplications = $stmt->fetchAll();
    
    // Output CSV headers
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Full Name', 'Email', 'Phone', 'Mode of Learning', 'Category', 'Programme', 'Intake', 'Status', 'Submitted Date', 'Processed Date', 'Rejection Reason']);
    
    // Output data
    foreach ($personalApplications as $app) {
        fputcsv($output, [
            $app['id'],
            $app['full_name'],
            $app['email'],
            $app['phone'] ?? '',
            $app['mode_of_learning'] ?? '',
            $app['category'],
            $app['programme_name'],
            $app['intake_name'],
            $app['status'],
            date('Y-m-d', strtotime($app['created_at'])),
            $app['updated_at'] ? date('Y-m-d', strtotime($app['updated_at'])) : '',
            $app['rejection_reason'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

// Get enrollment officer profile
$stmt = $pdo->prepare("SELECT sp.full_name, sp.staff_id FROM staff_profile sp WHERE sp.user_id = ?");
$stmt->execute([currentUserId()]);
$enrollmentOfficer = $stmt->fetch();

// Get current user's approvals and rejections
// Get all applications processed by current enrollment officer
$stmt = $pdo->prepare("
    SELECT a.*, a.phone, a.mode_of_learning, p.name as programme_name, i.name as intake_name, 
           CASE 
               WHEN p.name LIKE '%Business%' OR p.name LIKE '%Admin%' OR p.name LIKE '%Diploma%' THEN 'undergraduate'
               WHEN p.name LIKE '%Computer%' OR p.name LIKE '%IT%' OR p.name LIKE '%Certificate%' THEN 'short_course'
               ELSE 'other'
           END as category
    FROM applications a
    LEFT JOIN programme p ON a.programme_id = p.id
    LEFT JOIN intake i ON a.intake_id = i.id
    WHERE a.processed_by = ?
    ORDER BY a.updated_at DESC
");
$stmt->execute([currentUserId()]);
$processedApplications = $stmt->fetchAll();

// Group applications by status and category
$approvalsByCategory = [
    'undergraduate' => [],
    'short_course' => [],
    'other' => []
];

$rejectionsByCategory = [
    'undergraduate' => [],
    'short_course' => [],
    'other' => []
];

foreach ($processedApplications as $application) {
    if ($application['status'] === 'approved') {
        $approvalsByCategory[$application['category']][] = $application;
    } elseif ($application['status'] === 'rejected') {
        $rejectionsByCategory[$application['category']][] = $application;
    }
}

// Calculate totals for statistics
$totalApprovals = 0;
$totalRejections = 0;
foreach ($approvalsByCategory as $apps) {
    $totalApprovals += count($apps);
}
foreach ($rejectionsByCategory as $apps) {
    $totalRejections += count($apps);
}

// Function to check if acceptance letter exists
function checkAcceptanceLetterExists($application_id) {
    $letter_filename = 'acceptance_letter_' . $application_id . '.docx';
    $letter_path = __DIR__ . '/../letters/' . $letter_filename;
    return file_exists($letter_path);
}

// Function to format mode of learning
function formatModeOfLearning($mode) {
    switch ($mode) {
        case 'online':
            return 'Online';
        case 'physical':
            return 'Physical';
        default:
            return 'Not specified';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Approvals - LSC SRMS</title>
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
            </div>
            
            <div class="nav-section">
                <h4>My Approvals</h4>
                <a href="my_approvals.php" class="nav-item active">
                    <i class="fas fa-thumbs-up"></i>
                    <span>My Approvals</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Registered Students</h4>
                <a href="registered_students.php" class="nav-item">
                    <i class="fas fa-user-graduate"></i>
                    <span>Registered Students</span>
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
            <h1><i class="fas fa-thumbs-up"></i> My Approvals & Rejections</h1>
            <p>View and export your processed applications</p>
        </div>
        
        <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Export Button -->
        <div class="action-bar">
            <a href="?export=personal" class="btn green">
                <i class="fas fa-file-csv"></i> Export My Approvals (CSV)
            </a>
            <a href="../finance/manage_programme_fees.php" class="btn blue" target="_blank">
                <i class="fas fa-file-invoice-dollar"></i> Manage Programme Fees
            </a>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($totalApprovals); ?></h3>
                    <p>Total Approvals</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($totalRejections); ?></h3>
                    <p>Total Rejections</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format(count($approvalsByCategory['undergraduate']) + count($rejectionsByCategory['undergraduate'])); ?></h3>
                    <p>Undergraduate</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format(count($approvalsByCategory['short_course']) + count($rejectionsByCategory['short_course'])); ?></h3>
                    <p>Short Courses</p>
                </div>
            </div>
        </div>
        
        <!-- Acceptance Letters Section -->
        <div class="applications-section">
            <h2><i class="fas fa-file-pdf"></i> Acceptance Letters</h2>
            
            <div class="data-panel">
                <div class="panel-header">
                    <h3><i class="fas fa-envelope-open-text"></i> Generated Acceptance Letters</h3>
                </div>
                <div class="panel-content">
                    <?php 
                    // Get all approved applications for this enrollment officer
                    $approvedApps = array_merge(
                        $approvalsByCategory['undergraduate'],
                        $approvalsByCategory['short_course'],
                        $approvalsByCategory['other']
                    );
                    
                    if (empty($approvedApps)): ?>
                        <div class="empty-state">
                            <i class="fas fa-file-pdf"></i>
                            <p>No acceptance letters generated</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Applicant Name</th>
                                        <th>Programme</th>
                                        <th>Intake</th>
                                        <th>Mode of Study</th>
                                        <th>Phone Number</th>
                                        <th>Acceptance Letter</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($approvedApps as $app): 
                                        $letterExists = checkAcceptanceLetterExists($app['id']);
                                        $phoneNumber = $app['phone'] ?? 'N/A';
                                        $modeOfStudy = formatModeOfLearning($app['mode_of_learning']);
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($app['programme_name']); ?></td>
                                            <td><?php echo htmlspecialchars($app['intake_name']); ?></td>
                                            <td><?php echo htmlspecialchars($modeOfStudy); ?></td>
                                            <td><?php echo htmlspecialchars($phoneNumber); ?></td>
                                            <td>
                                                <?php if ($letterExists): ?>
                                                    <a href="../download_letter.php?file=<?php echo urlencode('acceptance_letter_' . $app['id'] . '.docx'); ?>" 
                                                       class="btn blue small" target="_blank">
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                <?php else: ?>
                                                    <span class="status-badge yellow">Not Available</span>
                                                <?php endif; ?>
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
        
        <!-- Approvals by Category -->
        <div class="applications-section">
            <h2><i class="fas fa-check-circle"></i> My Approved Applications</h2>
            
            <!-- Undergraduate Approvals -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3><i class="fas fa-graduation-cap"></i> Undergraduate Approvals (<?php echo count($approvalsByCategory['undergraduate']); ?>)</h3>
                </div>
                <div class="panel-content">
                    <?php if (empty($approvalsByCategory['undergraduate'])): ?>
                        <div class="empty-state">
                            <i class="fas fa-graduation-cap"></i>
                            <p>No undergraduate approvals</p>
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
                                        <th>Approved Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($approvalsByCategory['undergraduate'] as $app): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($app['email']); ?></td>
                                            <td><?php echo htmlspecialchars($app['programme_name']); ?></td>
                                            <td><?php echo htmlspecialchars($app['intake_name']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($app['updated_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Short Courses Approvals -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3><i class="fas fa-book"></i> Short Courses Approvals (<?php echo count($approvalsByCategory['short_course']); ?>)</h3>
                </div>
                <div class="panel-content">
                    <?php if (empty($approvalsByCategory['short_course'])): ?>
                        <div class="empty-state">
                            <i class="fas fa-book"></i>
                            <p>No short courses approvals</p>
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
                                        <th>Approved Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($approvalsByCategory['short_course'] as $app): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($app['email']); ?></td>
                                            <td><?php echo htmlspecialchars($app['programme_name']); ?></td>
                                            <td><?php echo htmlspecialchars($app['intake_name']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($app['updated_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Rejections by Category -->
        <div class="applications-section">
            <h2><i class="fas fa-times-circle"></i> My Rejected Applications</h2>
            
            <!-- Undergraduate Rejections -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3><i class="fas fa-graduation-cap"></i> Undergraduate Rejections (<?php echo count($rejectionsByCategory['undergraduate']); ?>)</h3>
                </div>
                <div class="panel-content">
                    <?php if (empty($rejectionsByCategory['undergraduate'])): ?>
                        <div class="empty-state">
                            <i class="fas fa-graduation-cap"></i>
                            <p>No undergraduate rejections</p>
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
                                        <th>Rejected Date</th>
                                        <th>Rejection Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rejectionsByCategory['undergraduate'] as $app): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($app['email']); ?></td>
                                            <td><?php echo htmlspecialchars($app['programme_name']); ?></td>
                                            <td><?php echo htmlspecialchars($app['intake_name']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($app['updated_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($app['rejection_reason'] ?? 'No reason provided'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Short Courses Rejections -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3><i class="fas fa-book"></i> Short Courses Rejections (<?php echo count($rejectionsByCategory['short_course']); ?>)</h3>
                </div>
                <div class="panel-content">
                    <?php if (empty($rejectionsByCategory['short_course'])): ?>
                        <div class="empty-state">
                            <i class="fas fa-book"></i>
                            <p>No short courses rejections</p>
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
                                        <th>Rejected Date</th>
                                        <th>Rejection Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rejectionsByCategory['short_course'] as $app): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($app['email']); ?></td>
                                            <td><?php echo htmlspecialchars($app['programme_name']); ?></td>
                                            <td><?php echo htmlspecialchars($app['intake_name']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($app['updated_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($app['rejection_reason'] ?? 'No reason provided'); ?></td>
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
        // Initialize any needed JavaScript functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Any additional initialization can go here
        });
    </script>
</body>
</html>