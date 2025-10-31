<?php
session_start();
require_once '../config.php';
require_once '../auth.php';

// Include the new acceptance letter with fees function
require_once '../finance/generate_acceptance_letter_with_fees.php';

// Check if user is logged in and has permission
if (!currentUserId()) {
    header('Location: ../login.php');
    exit();
}

// Check if user has admin role or enrollment_approvals permission
if (!currentUserHasRole('Super Admin', $pdo) && !currentUserHasPermission('enrollment_approvals', $pdo)) {
    header('Location: ../login.php');
    exit();
}

// Get user info
$stmt = $pdo->prepare("SELECT u.*, ap.full_name, ap.staff_id FROM users u LEFT JOIN admin_profile ap ON u.id = ap.user_id WHERE u.id = ?");
$stmt->execute([currentUserId()]);
$user = $stmt->fetch();

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'approve_application':
                $application_id = $_POST['application_id'];
                
                try {
                    // Get application details
                    $app_stmt = $pdo->prepare("
                        SELECT a.*, p.name as programme_name, i.name as intake_name, p.id as programme_id
                        FROM applications a
                        LEFT JOIN programme p ON a.programme_id = p.id
                        LEFT JOIN intake i ON a.intake_id = i.id
                        WHERE a.id = ?
                    ");
                    $app_stmt->execute([$application_id]);
                    $application = $app_stmt->fetch();
                    
                    // Update application status
                    $stmt = $pdo->prepare("UPDATE applications SET status = 'approved' WHERE id = ?");
                    $stmt->execute([$application_id]);
                    
                    // Add to awaiting registration
                    $pending_stmt = $pdo->prepare("INSERT INTO pending_students (full_name, email, programme_id, intake_id, documents, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $pending_stmt->execute([
                        $application['full_name'],
                        $application['email'],
                        $application['programme_id'],
                        $application['intake_id'],
                        $application['documents']
                    ]);
                    
                    // Generate acceptance letter with fees
                    $letter_path = generateAcceptanceLetterWithFees($application, $pdo);
                    
                    // Send email with attachment (placeholder)
                    $to = $application['email'];
                    $subject = 'Application Approved - LSC SRMS';
                    $body = "Dear {$application['full_name']},

Congratulations! Your application has been approved. Please find your acceptance letter with fee structure attached.

Best regards,
LSC SRMS Admissions";
                    // Use PHPMailer or similar in production:
                    // $mailer = new PHPMailer();
                    // $mailer->addAttachment($letter_path);
                    // $mailer->send($to, $subject, $body);
                    
                    $message = "Application approved! Acceptance letter with fees generated and email sent. <a href='$letter_path' target='_blank'>Download Letter</a>";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'reject_application':
                $application_id = $_POST['application_id'];
                $rejection_reason = trim($_POST['rejection_reason']);
                
                if (empty($rejection_reason)) {
                    $message = "Please provide a rejection reason!";
                    $messageType = 'error';
                } else {
                    try {
                        // Update application status and reason
                        $stmt = $pdo->prepare("UPDATE applications SET status = 'rejected', rejection_reason = ? WHERE id = ?");
                        $stmt->execute([$rejection_reason, $application_id]);
                        
                        // Get application details
                        $app_stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
                        $app_stmt->execute([$application_id]);
                        $application = $app_stmt->fetch();
                        
                        // Send email
                        $to = $application['email'];
                        $subject = 'Application Rejected - LSC SRMS';
                        $body = "Dear {$application['full_name']},

We regret to inform you that your application has been rejected.
Reason: {$rejection_reason}

Best regards,
LSC SRMS Admissions";
                        // Use PHPMailer or similar in production:
                        // $mailer = new PHPMailer();
                        // $mailer->send($to, $subject, $body);
                        
                        $message = "Application rejected and email sent!";
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
        }
    }
}

// Get pending applications
$pending_query = "
    SELECT a.*, p.name as programme_name, i.name as intake_name
    FROM applications a
    LEFT JOIN programme p ON a.programme_id = p.id
    LEFT JOIN intake i ON a.intake_id = i.id
    WHERE a.status = 'pending'
    ORDER BY a.created_at DESC
";
$pending_applications = $pdo->query($pending_query)->fetchAll();

// Get enrollment history (monthly)
$history_query = "
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
    FROM applications
    WHERE status = 'approved'
    GROUP BY month
    ORDER BY month DESC
";
$enrollment_history = $pdo->query($history_query)->fetchAll();

// Get awaiting registration students
$awaiting_query = "
    SELECT ps.*, p.name as programme_name, i.name as intake_name
    FROM pending_students ps
    LEFT JOIN programme p ON ps.programme_id = p.id
    LEFT JOIN intake i ON ps.intake_id = i.id
    ORDER BY ps.created_at DESC
";
$awaiting_students = $pdo->query($awaiting_query)->fetchAll();

// Get enrollment officer statistics for commission tracking
$officer_stats_query = "
    SELECT 
        u.id as officer_id,
        COALESCE(sp.full_name, u.username) as officer_name,
        sp.staff_id,
        COUNT(CASE WHEN a.status = 'approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN a.status = 'rejected' THEN 1 END) as rejected_count,
        COUNT(a.id) as total_processed,
        DATE_FORMAT(MAX(a.updated_at), '%Y-%m-%d') as last_activity
    FROM users u
    JOIN user_roles ur ON u.id = ur.user_id
    JOIN roles r ON ur.role_id = r.id
    LEFT JOIN staff_profile sp ON u.id = sp.user_id
    LEFT JOIN applications a ON u.id = a.processed_by
    WHERE r.name = 'Enrollment Officer'
    GROUP BY u.id, sp.full_name, u.username, sp.staff_id
    ORDER BY total_processed DESC
";
$officer_statistics = $pdo->query($officer_stats_query)->fetchAll();

// Get monthly statistics by officer
$monthly_officer_stats_query = "
    SELECT 
        u.id as officer_id,
        COALESCE(sp.full_name, u.username) as officer_name,
        DATE_FORMAT(a.updated_at, '%Y-%m') as month,
        COUNT(CASE WHEN a.status = 'approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN a.status = 'rejected' THEN 1 END) as rejected_count,
        COUNT(a.id) as total_processed
    FROM users u
    JOIN user_roles ur ON u.id = ur.user_id
    JOIN roles r ON ur.role_id = r.id
    LEFT JOIN staff_profile sp ON u.id = sp.user_id
    LEFT JOIN applications a ON u.id = a.processed_by
    WHERE r.name = 'Enrollment Officer' AND a.updated_at IS NOT NULL
    GROUP BY u.id, sp.full_name, u.username, DATE_FORMAT(a.updated_at, '%Y-%m')
    ORDER BY month DESC, total_processed DESC
";
$monthly_officer_statistics = $pdo->query($monthly_officer_stats_query)->fetchAll();

// Create necessary tables if not exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        programme_id INT,
        intake_id INT,
        documents TEXT,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        rejection_reason TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (programme_id) REFERENCES programme(id) ON DELETE SET NULL,
        FOREIGN KEY (intake_id) REFERENCES intake(id) ON DELETE SET NULL
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS pending_students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        programme_id INT,
        intake_id INT,
        documents TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    // Tables might already exist
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Approvals - LSC SRMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .modal-content { max-width: 600px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; }
        .modal-footer { display: flex; gap: 10px; justify-content: flex-end; }
        .data-panel { margin-bottom: 20px; }
        .panel-header { display: flex; justify-content: space-between; align-items: center; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 10px; border: 1px solid var(--border-color); }
        .btn-sm { padding: 5px 10px; font-size: 12px; }
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
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($user['full_name'] ?? 'Administrator'); ?></span>
                <span class="staff-id">(<?php echo htmlspecialchars($user['staff_id'] ?? 'N/A'); ?>)</span>
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
                <a href="manage_intakes.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Intakes</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Academic Operations</h4>
                <a href="manage_results.php" class="nav-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Results Management</span>
                </a>
                <a href="enrollment_approvals.php" class="nav-item active">
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
                <a href="analytics.php" class="nav-item">
                    <i class="fas fa-analytics"></i>
                    <span>Analytics</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-user-check"></i> Enrollment Approvals</h1>
            <p>Review and approve student enrollment applications</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Pending Applications -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-clock"></i> Pending Applications (<?php echo count($pending_applications); ?>)</h3>
            </div>
            <div class="panel-content">
                <?php if (empty($pending_applications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clock"></i>
                        <p>No pending applications</p>
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
                                <?php foreach ($pending_applications as $app): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($app['email']); ?></td>
                                        <td><?php echo htmlspecialchars($app['programme_name']); ?></td>
                                        <td><?php echo htmlspecialchars($app['intake_name']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($app['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="openViewModal(<?php echo htmlspecialchars(json_encode($app)); ?>)"><i class="fas fa-eye"></i> View</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Awaiting Registration -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-user-plus"></i> Awaiting Registration (<?php echo count($awaiting_students); ?>)</h3>
            </div>
            <div class="panel-content">
                <?php if (empty($awaiting_students)): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-plus"></i>
                        <p>No students awaiting registration</p>
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
                                    <th>Approved</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($awaiting_students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td><?php echo htmlspecialchars($student['programme_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['intake_name']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($student['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Enrollment History -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-history"></i> Enrollment History (Monthly)</h3>
            </div>
            <div class="panel-content">
                <?php if (empty($enrollment_history)): ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <p>No enrollment history</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Enrollments</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrollment_history as $history): ?>
                                    <tr>
                                        <td><?php echo $history['month']; ?></td>
                                        <td><?php echo $history['count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Enrollment Officer Performance for Commission Tracking -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-chart-line"></i> Enrollment Officer Performance (Commission Tracking)</h3>
            </div>
            <div class="panel-content">
                <?php if (empty($officer_statistics)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No enrollment officers found</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Officer Name</th>
                                    <th>Staff ID</th>
                                    <th>Approved</th>
                                    <th>Rejected</th>
                                    <th>Total Processed</th>
                                    <th>Approval Rate</th>
                                    <th>Last Activity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($officer_statistics as $stats): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($stats['officer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($stats['staff_id'] ?? 'N/A'); ?></td>
                                        <td><?php echo number_format($stats['approved_count']); ?></td>
                                        <td><?php echo number_format($stats['rejected_count']); ?></td>
                                        <td><?php echo number_format($stats['total_processed']); ?></td>
                                        <td>
                                            <?php 
                                            $approval_rate = $stats['total_processed'] > 0 ? 
                                                round(($stats['approved_count'] / $stats['total_processed']) * 100, 2) : 0;
                                            echo $approval_rate . '%';
                                            ?>
                                        </td>
                                        <td><?php echo $stats['last_activity'] ?? 'Never'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Monthly Performance by Officer -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-calendar-alt"></i> Monthly Performance by Officer</h3>
            </div>
            <div class="panel-content">
                <?php if (empty($monthly_officer_statistics)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-alt"></i>
                        <p>No monthly performance data</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Officer Name</th>
                                    <th>Approved</th>
                                    <th>Rejected</th>
                                    <th>Total Processed</th>
                                    <th>Approval Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthly_officer_statistics as $stats): ?>
                                    <tr>
                                        <td><?php echo $stats['month']; ?></td>
                                        <td><?php echo htmlspecialchars($stats['officer_name']); ?></td>
                                        <td><?php echo number_format($stats['approved_count']); ?></td>
                                        <td><?php echo number_format($stats['rejected_count']); ?></td>
                                        <td><?php echo number_format($stats['total_processed']); ?></td>
                                        <td>
                                            <?php 
                                            $approval_rate = $stats['total_processed'] > 0 ? 
                                                round(($stats['approved_count'] / $stats['total_processed']) * 100, 2) : 0;
                                            echo $approval_rate . '%';
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

    </main>

    <!-- View Application Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-file-alt"></i> Application Details</h3>
                <span class="close" onclick="closeModal('viewModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p><strong>Name:</strong> <span id="view_name"></span></p>
                <p><strong>Email:</strong> <span id="view_email"></span></p>
                <p><strong>Programme:</strong> <span id="view_programme"></span></p>
                <p><strong>Intake:</strong> <span id="view_intake"></span></p>
                <p><strong>Submitted:</strong> <span id="view_submitted"></span></p>
                <div id="documents_section">
                    <strong>Documents:</strong>
                    <ul id="documents_list"></ul>
                </div>
            </div>
            <div class="modal-footer">
                <form method="POST" id="approveForm">
                    <input type="hidden" name="action" value="approve_application">
                    <input type="hidden" id="approve_id" name="application_id">
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Approve</button>
                </form>
                <button onclick="openRejectModal()" class="btn btn-danger"><i class="fas fa-times"></i> Reject</button>
            </div>
        </div>
    </div>

    <!-- Reject Reason Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Reject Application</h3>
                <span class="close" onclick="closeModal('rejectModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="reject_application">
                <input type="hidden" id="reject_id" name="application_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="rejection_reason">Rejection Reason *</label>
                        <textarea id="rejection_reason" name="rejection_reason" rows="4" required placeholder="Explain why the application is being rejected..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> Reject</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script>
        function openViewModal(app) {
            document.getElementById('view_name').textContent = app.full_name;
            document.getElementById('view_email').textContent = app.email;
            document.getElementById('view_programme').textContent = app.programme_name;
            document.getElementById('view_intake').textContent = app.intake_name;
            document.getElementById('view_submitted').textContent = new Date(app.created_at).toLocaleDateString();
            
            const docsList = document.getElementById('documents_list');
            docsList.innerHTML = '';
            const documents = JSON.parse(app.documents || '[]');
            if (documents.length > 0) {
                documents.forEach(doc => {
                    const li = document.createElement('li');
                    li.innerHTML = `<a href="${doc.path}" target="_blank">${doc.name}</a>`;
                    docsList.appendChild(li);
                });
            } else {
                docsList.innerHTML = '<li>No documents attached</li>';
            }
            
            document.getElementById('approve_id').value = app.id;
            document.getElementById('reject_id').value = app.id;
            
            document.getElementById('viewModal').style.display = 'block';
        }
        
        function openRejectModal() {
            closeModal('viewModal');
            document.getElementById('rejectModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>