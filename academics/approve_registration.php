<?php
require '../config.php';
require '../auth/auth.php';

// Check if user is logged in
if (!currentUserId()) {
    header('Location: ../auth/login.php');
    exit;
}

// Check if user has Academics Coordinator role
requireRole('Academics Coordinator', $pdo);

// Get admin profile
$stmt = $pdo->prepare("SELECT ap.full_name, ap.staff_id FROM admin_profile ap WHERE ap.user_id = ?");
$stmt->execute([currentUserId()]);
$admin = $stmt->fetch();

// Handle form submission for approving registration
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status']) && isset($_POST['registration_id'])) {
    $registration_id = intval($_POST['registration_id']);
    $status = trim($_POST['status']);
    $notes = trim($_POST['notes']);
    
    // Validation
    if (empty($registration_id) || empty($status)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    } elseif (!in_array(strtolower($status), ['approved', 'rejected', 'pending'])) {
        $message = 'Invalid status selected.';
        $message_type = 'error';
    } else {
        try {
            // Update registration status
            $stmt = $pdo->prepare("
                UPDATE course_registration 
                SET status = ?, notes = ?, reviewed_by = ?, reviewed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([strtolower($status), $notes, currentUserId(), $registration_id]);
            
            $message = 'Registration status updated successfully!';
            $message_type = 'success';
        } catch (Exception $e) {
            $message = 'Error updating registration status: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Fetch pending course registrations with student and course details
$stmt = $pdo->prepare("
    SELECT cr.*, s.id as student_id, 
           COALESCE(sp.full_name, s.student_name) as student_name,
           CONCAT(COALESCE(sp.full_name, s.student_name), ' (', COALESCE(sp.student_number, s.student_number), ')') as display_name,
           c.name as course_name, p.name as programme_name, 
           CONCAT(ap.full_name, ' (', ap.staff_id, ')') as reviewer_name,
           u.username as lecturer_username
    FROM course_registration cr
    LEFT JOIN registered_students s ON cr.student_id = s.id
    LEFT JOIN student_profile sp ON s.student_id = sp.user_id
    LEFT JOIN course c ON cr.course_id = c.id
    LEFT JOIN programme p ON c.programme_id = p.id
    LEFT JOIN admin_profile ap ON cr.reviewed_by = ap.user_id
    LEFT JOIN lecturer_courses lc ON c.id = lc.course_id
    LEFT JOIN users u ON lc.lecturer_id = u.id
    WHERE cr.status = 'pending'
    ORDER BY cr.submitted_at DESC
");
$stmt->execute();
$pending_registrations = $stmt->fetchAll();

// Fetch approved course registrations
$stmt = $pdo->prepare("
    SELECT cr.*, s.id as student_id, 
           COALESCE(sp.full_name, s.student_name) as student_name,
           CONCAT(COALESCE(sp.full_name, s.student_name), ' (', COALESCE(sp.student_number, s.student_number), ')') as display_name,
           c.name as course_name, p.name as programme_name, 
           CONCAT(ap.full_name, ' (', ap.staff_id, ')') as reviewer_name,
           u.username as lecturer_username
    FROM course_registration cr
    LEFT JOIN registered_students s ON cr.student_id = s.id
    LEFT JOIN student_profile sp ON s.student_id = sp.user_id
    LEFT JOIN course c ON cr.course_id = c.id
    LEFT JOIN programme p ON c.programme_id = p.id
    LEFT JOIN admin_profile ap ON cr.reviewed_by = ap.user_id
    LEFT JOIN lecturer_courses lc ON c.id = lc.course_id
    LEFT JOIN users u ON lc.lecturer_id = u.id
    WHERE cr.status = 'approved'
    ORDER BY cr.reviewed_at DESC
    LIMIT 10
");
$stmt->execute();
$approved_registrations = $stmt->fetchAll();

// Fetch rejected course registrations
$stmt = $pdo->prepare("
    SELECT cr.*, s.id as student_id, 
           COALESCE(sp.full_name, s.student_name) as student_name,
           CONCAT(COALESCE(sp.full_name, s.student_name), ' (', COALESCE(sp.student_number, s.student_number), ')') as display_name,
           c.name as course_name, p.name as programme_name, 
           CONCAT(ap.full_name, ' (', ap.staff_id, ')') as reviewer_name,
           u.username as lecturer_username
    FROM course_registration cr
    LEFT JOIN registered_students s ON cr.student_id = s.id
    LEFT JOIN student_profile sp ON s.student_id = sp.user_id
    LEFT JOIN course c ON cr.course_id = c.id
    LEFT JOIN programme p ON c.programme_id = p.id
    LEFT JOIN admin_profile ap ON cr.reviewed_by = ap.user_id
    LEFT JOIN lecturer_courses lc ON c.id = lc.course_id
    LEFT JOIN users u ON lc.lecturer_id = u.id
    WHERE cr.status = 'rejected'
    ORDER BY cr.reviewed_at DESC
    LIMIT 10
");
$stmt->execute();
$rejected_registrations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Course Registration - Academics Dashboard</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/school_logo.jpg">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-card {
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 8px var(--shadow);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            background-color: var(--primary-green);
            color: white;
        }
        
        .card-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 500;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        
        .form-group {
            flex: 1 0 300px;
            padding: 0 10px;
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            flex: 1 0 100%;
        }
        
        .form-group.half-width {
            flex: 1 0 45%;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-dark);
        }
        
        .form-group.required label::after {
            content: " *";
            color: #ea4335;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 2px rgba(34, 139, 34, 0.2);
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-decoration: none;
            text-align: center;
        }
        
        .btn-primary {
            background-color: var(--primary-green);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--dark-green);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .registration-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px var(--shadow);
        }
        
        .registration-table th,
        .registration-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .registration-table th {
            background-color: var(--primary-green);
            color: white;
            font-weight: 600;
        }
        
        .registration-table tbody tr:hover {
            background-color: rgba(34, 139, 34, 0.05);
        }
        
        .registration-table .actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-icon {
            padding: 8px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-edit {
            background-color: #ffc107;
            color: #212529;
        }
        
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-view {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-approve {
            background-color: #28a745;
            color: white;
        }
        
        .btn-reject {
            background-color: #dc3545;
            color: white;
        }
        
        .section-title {
            color: var(--primary-green);
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-green);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-light);
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ccc;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .badge-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .registration-stats {
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
        
        .bg-blue { background-color: #4285f4; }
        .bg-green { background-color: #34a853; }
        .bg-orange { background-color: #fbbc05; }
        .bg-purple { background-color: #673ab7; }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: var(--white);
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-header h3 {
            margin: 0;
            color: var(--primary-green);
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        @media (max-width: 768px) {
            .form-group {
                flex: 1 0 100%;
            }
            
            .form-group.half-width {
                flex: 1 0 100%;
            }
            
            .registration-table {
                font-size: 14px;
            }
            
            .registration-table th,
            .registration-table td {
                padding: 8px 10px;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
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
                <a href="approve_registration.php" class="nav-item active">
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
                <a href="analytics.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Performance Analytics</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-clipboard-check"></i> Approve Course Registration</h1>
            <p>Review and approve student course registration requests</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="registration-stats">
            <div class="stat-card">
                <div class="stat-icon bg-blue">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo count($pending_registrations); ?></h3>
                    <p>Pending Registrations</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon bg-green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo count($approved_registrations); ?></h3>
                    <p>Recently Approved</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon bg-orange">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo count($rejected_registrations); ?></h3>
                    <p>Recently Rejected</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon bg-purple">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <h3>1,248</h3>
                    <p>Total Students</p>
                </div>
            </div>
        </div>
        
        <h2 class="section-title">Pending Registrations</h2>
        
        <?php if (count($pending_registrations) > 0): ?>
            <div class="table-responsive">
                <table class="registration-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Programme</th>
                            <th>Course</th>
                            <th>Registration Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_registrations as $registration): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($registration['display_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($registration['programme_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($registration['course_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M j, Y', strtotime($registration['submitted_at'])); ?></td>
                                <td><span class="badge badge-warning">Pending</span></td>
                                <td class="actions">
                                    <a href="#" class="btn-icon btn-view" title="View Details" onclick="showDetails(<?php echo $registration['id']; ?>, '<?php echo addslashes(htmlspecialchars($registration['display_name'] ?? 'Unknown')); ?>', '<?php echo addslashes(htmlspecialchars($registration['course_name'] ?? 'N/A')); ?>', '<?php echo addslashes(htmlspecialchars($registration['programme_name'] ?? 'N/A')); ?>', '<?php echo date('M j, Y', strtotime($registration['submitted_at'])); ?>', '<?php echo addslashes(htmlspecialchars($registration['notes'] ?? '')); ?>')">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="#" class="btn-icon btn-approve" title="Approve" onclick="approveRegistration(<?php echo $registration['id']; ?>)">
                                        <i class="fas fa-check"></i>
                                    </a>
                                    <a href="#" class="btn-icon btn-reject" title="Reject" onclick="rejectRegistration(<?php echo $registration['id']; ?>)">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-check"></i>
                <h3>No Pending Registrations</h3>
                <p>All course registration requests have been processed.</p>
            </div>
        <?php endif; ?>
        
        <h2 class="section-title">Recently Approved</h2>
        
        <?php if (count($approved_registrations) > 0): ?>
            <div class="table-responsive">
                <table class="registration-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Programme</th>
                            <th>Course</th>
                            <th>Registration Date</th>
                            <th>Reviewed By</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approved_registrations as $registration): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($registration['display_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($registration['programme_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($registration['course_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M j, Y', strtotime($registration['submitted_at'])); ?></td>
                                <td><?php echo htmlspecialchars($registration['reviewer_name'] ?? 'N/A'); ?></td>
                                <td><span class="badge badge-success">Approved</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>No Recently Approved Registrations</h3>
                <p>No course registrations have been approved recently.</p>
            </div>
        <?php endif; ?>
        
        <h2 class="section-title">Recently Rejected</h2>
        
        <?php if (count($rejected_registrations) > 0): ?>
            <div class="table-responsive">
                <table class="registration-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Programme</th>
                            <th>Course</th>
                            <th>Registration Date</th>
                            <th>Reviewed By</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rejected_registrations as $registration): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($registration['display_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($registration['programme_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($registration['course_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M j, Y', strtotime($registration['submitted_at'])); ?></td>
                                <td><?php echo htmlspecialchars($registration['reviewer_name'] ?? 'N/A'); ?></td>
                                <td><span class="badge badge-danger">Rejected</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-times-circle"></i>
                <h3>No Recently Rejected Registrations</h3>
                <p>No course registrations have been rejected recently.</p>
            </div>
        <?php endif; ?>
    </main>

    <!-- Modal for viewing registration details -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Registration Details</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="modalContent"></div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Modal for approving/rejecting registration -->
    <div id="approvalModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="approvalModalTitle">Approve Registration</h3>
                <span class="close" onclick="closeApprovalModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="approvalForm">
                    <input type="hidden" name="registration_id" id="registrationId" value="">
                    <input type="hidden" name="status" id="statusInput" value="">
                    
                    <div class="form-group full-width">
                        <label for="notes">Notes (Optional)</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3" 
                                  placeholder="Additional notes about this decision"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeApprovalModal()">Cancel</button>
                <button class="btn btn-primary" onclick="submitApproval()">Submit</button>
            </div>
        </div>
    </div>

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
        
        // Show registration details modal
        function showDetails(id, student, course, programme, date, notes) {
            const modal = document.getElementById('detailsModal');
            const content = document.getElementById('modalContent');
            
            content.innerHTML = `
                <h4>Registration Details</h4>
                <p><strong>Student:</strong> ${student}</p>
                <p><strong>Programme:</strong> ${programme}</p>
                <p><strong>Course:</strong> ${course}</p>
                <p><strong>Registration Date:</strong> ${date}</p>
                <p><strong>Notes:</strong> ${notes || 'No notes provided'}</p>
            `;
            
            modal.style.display = 'block';
        }
        
        // Approve registration
        function approveRegistration(id) {
            document.getElementById('registrationId').value = id;
            document.getElementById('statusInput').value = 'approved';
            document.getElementById('approvalModalTitle').textContent = 'Approve Registration';
            document.getElementById('notes').value = '';
            
            document.getElementById('approvalModal').style.display = 'block';
        }
        
        // Reject registration
        function rejectRegistration(id) {
            document.getElementById('registrationId').value = id;
            document.getElementById('statusInput').value = 'rejected';
            document.getElementById('approvalModalTitle').textContent = 'Reject Registration';
            document.getElementById('notes').value = '';
            
            document.getElementById('approvalModal').style.display = 'block';
        }
        
        // Submit approval/rejection
        function submitApproval() {
            document.getElementById('approvalForm').submit();
        }
        
        // Close details modal
        function closeModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }
        
        // Close approval modal
        function closeApprovalModal() {
            document.getElementById('approvalModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const detailsModal = document.getElementById('detailsModal');
            const approvalModal = document.getElementById('approvalModal');
            
            if (event.target === detailsModal) {
                detailsModal.style.display = 'none';
            }
            
            if (event.target === approvalModal) {
                approvalModal.style.display = 'none';
            }
        }
    </script>
</body>
</html>