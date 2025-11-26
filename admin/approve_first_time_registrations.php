<?php
session_start();
require_once '../config.php';
require_once '../auth.php';
require_once '../send_temporary_credentials.php';

// Check if user is logged in and has permission
if (!currentUserId()) {
    header('Location: ../login.php');
    exit();
}

// Check if user has admin role
if (!currentUserHasRole('Super Admin', $pdo) && !currentUserHasRole('Sub Admin (Finance)', $pdo)) {
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
            case 'approve_registration':
                $pending_student_id = $_POST['pending_student_id'];
                
                try {
                    // Get pending student details
                    $stmt = $pdo->prepare("
                        SELECT ps.*, p.name as programme_name, i.name as intake_name 
                        FROM pending_students ps 
                        LEFT JOIN programme p ON ps.programme_id = p.id 
                        LEFT JOIN intake i ON ps.intake_id = i.id 
                        WHERE ps.id = ?
                    ");
                    $stmt->execute([$pending_student_id]);
                    $pending_student = $stmt->fetch();
                    
                    if (!$pending_student) {
                        throw new Exception("Pending student not found!");
                    }
                    
                    // Generate student number
                    $student_number = generateStudentNumber($pdo);
                    
                    // Generate temporary password
                    $temp_password = 'LSC@' . date('Y') . rand(1000, 9999);
                    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
                    
                    // Update pending student with student number and approved status
                    // Handle case where student_number column might not exist yet
                    try {
                        $stmt = $pdo->prepare("UPDATE pending_students SET student_number = ?, temp_password = ?, registration_status = 'approved', updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$student_number, $hashed_password, $pending_student_id]);
                    } catch (Exception $e) {
                        // If student_number column doesn't exist, update without it
                        $stmt = $pdo->prepare("UPDATE pending_students SET temp_password = ?, registration_status = 'approved', updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$hashed_password, $pending_student_id]);
                    }
                    
                    // Create user account
                    $pdo->beginTransaction();
                    
                    try {
                        // Create user account
                        $user_stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, contact, is_active) VALUES (?, ?, ?, ?, 1)");
                        $user_stmt->execute([
                            $student_number,
                            $hashed_password,
                            $pending_student['email'],
                            '' // No contact info available
                        ]);
                        
                        $user_id = $pdo->lastInsertId();
                        
                        // Assign student role
                        $role_stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'Student'");
                        $role_stmt->execute();
                        $student_role = $role_stmt->fetch();
                        
                        if ($student_role) {
                            $user_role_stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                            $user_role_stmt->execute([$user_id, $student_role['id']]);
                        }
                        
                        // Create student profile
                        $profile_stmt = $pdo->prepare("INSERT INTO student_profile (user_id, full_name, student_number, programme_id, intake_id) VALUES (?, ?, ?, ?, ?)");
                        $profile_stmt->execute([
                            $user_id,
                            $pending_student['full_name'],
                            $student_number,
                            $pending_student['programme_id'],
                            $pending_student['intake_id']
                        ]);
                        
                        $pdo->commit();
                        
                        // Send registration confirmation email
                        sendRegistrationConfirmationEmail(
                            $pending_student['email'],
                            $pending_student['full_name'],
                            $student_number,
                            $temp_password
                        );
                        
                        $message = "Registration approved successfully! Student account created and email sent.";
                        $messageType = "success";
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        throw new Exception("Failed to create student account: " . $e->getMessage());
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
                
            case 'reject_registration':
                $pending_student_id = $_POST['pending_student_id'];
                $rejection_reason = trim($_POST['rejection_reason']);
                
                if (empty($rejection_reason)) {
                    $message = "Please provide a rejection reason!";
                    $messageType = "error";
                } else {
                    try {
                        $stmt = $pdo->prepare("UPDATE pending_students SET registration_status = 'rejected', rejection_reason = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$rejection_reason, $pending_student_id]);
                        
                        $message = "Registration rejected successfully!";
                        $messageType = "success";
                    } catch (Exception $e) {
                        $message = "Error: " . $e->getMessage();
                        $messageType = "error";
                    }
                }
                break;
        }
    }
}

// Get pending registrations with 'pending_approval' status
try {
    $stmt = $pdo->prepare("
        SELECT ps.*, p.name as programme_name, i.name as intake_name 
        FROM pending_students ps 
        LEFT JOIN programme p ON ps.programme_id = p.id 
        LEFT JOIN intake i ON ps.intake_id = i.id 
        WHERE ps.registration_status = 'pending_approval' 
        ORDER BY ps.created_at DESC
    ");
    $stmt->execute();
    $pending_registrations = $stmt->fetchAll();
} catch (Exception $e) {
    $pending_registrations = [];
}

// Get approved registrations
try {
    $stmt = $pdo->prepare("
        SELECT ps.*, p.name as programme_name, i.name as intake_name 
        FROM pending_students ps 
        LEFT JOIN programme p ON ps.programme_id = p.id 
        LEFT JOIN intake i ON ps.intake_id = i.id 
        WHERE ps.registration_status = 'approved' 
        ORDER BY ps.updated_at DESC
    ");
    $stmt->execute();
    $approved_registrations = $stmt->fetchAll();
} catch (Exception $e) {
    $approved_registrations = [];
}

// Get rejected registrations
try {
    $stmt = $pdo->prepare("
        SELECT ps.*, p.name as programme_name, i.name as intake_name 
        FROM pending_students ps 
        LEFT JOIN programme p ON ps.programme_id = p.id 
        LEFT JOIN intake i ON ps.intake_id = i.id 
        WHERE ps.registration_status = 'rejected' 
        ORDER BY ps.updated_at DESC
    ");
    $stmt->execute();
    $rejected_registrations = $stmt->fetchAll();
} catch (Exception $e) {
    $rejected_registrations = [];
}

// Function to generate student number
function generateStudentNumber($pdo) {
    // Format: LSC + 6-digit sequential number
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(student_number, 4) AS UNSIGNED)) as max_num FROM student_profile WHERE student_number LIKE 'LSC%'");
    $result = $stmt->fetch();
    $next_num = ($result['max_num'] ?? 0) + 1;
    return sprintf("LSC%06d", $next_num);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve First Time Registrations - LSC SRMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 5px;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-info {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .payment-proof-link {
            color: #007bff;
            text-decoration: none;
        }
        
        .payment-proof-link:hover {
            text-decoration: underline;
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
            </div>
            
            <div class="nav-section">
                <h4>Academic Operations</h4>
                <a href="enrollment_approvals.php" class="nav-item">
                    <i class="fas fa-user-check"></i>
                    <span>Enrollment Approvals</span>
                </a>
                <a href="course_registrations.php" class="nav-item">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Course Registrations</span>
                </a>
                <a href="approve_first_time_registrations.php" class="nav-item active">
                    <i class="fas fa-user-graduate"></i>
                    <span>First Time Registrations</span>
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
            <h1><i class="fas fa-user-graduate"></i> Approve First Time Registrations</h1>
            <p>Review and approve first time student registrations</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Pending Registrations -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-clock"></i> Pending Registrations (<?php echo count($pending_registrations); ?>)</h3>
            </div>
            <div class="panel-content">
                <?php if (empty($pending_registrations)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clock"></i>
                        <p>No pending registrations</p>
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
                                    <th>Payment Method</th>
                                    <th>Amount</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_registrations as $reg): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($reg['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($reg['email']); ?></td>
                                        <td><?php echo htmlspecialchars($reg['programme_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($reg['intake_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($reg['payment_method'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($reg['payment_amount'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($reg['created_at'])); ?></td>
                                        <td>
                                            <?php if (!empty($reg['payment_proof'])): ?>
                                                <a href="../<?php echo htmlspecialchars($reg['payment_proof']); ?>" target="_blank" class="btn btn-sm btn-info payment-proof-link">
                                                    <i class="fas fa-receipt"></i> View Proof
                                                </a>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-success" onclick="openApproveModal(<?php echo $reg['id']; ?>)">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="openRejectModal(<?php echo $reg['id']; ?>)">
                                                <i class="fas fa-times"></i> Reject
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

        <!-- Approved Registrations -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-check-circle"></i> Approved Registrations (<?php echo count($approved_registrations); ?>)</h3>
            </div>
            <div class="panel-content">
                <?php if (empty($approved_registrations)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No approved registrations</p>
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
                                    <th>Student Number</th>
                                    <th>Approved Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($approved_registrations as $reg): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($reg['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($reg['email']); ?></td>
                                        <td><?php echo htmlspecialchars($reg['programme_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($reg['intake_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($reg['student_number'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($reg['updated_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewStudentDetails(<?php echo $reg['id']; ?>)">
                                                <i class="fas fa-eye"></i> Details
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

        <!-- Rejected Registrations -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-times-circle"></i> Rejected Registrations (<?php echo count($rejected_registrations); ?>)</h3>
            </div>
            <div class="panel-content">
                <?php if (empty($rejected_registrations)): ?>
                    <div class="empty-state">
                        <i class="fas fa-times-circle"></i>
                        <p>No rejected registrations</p>
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
                                    <th>Reason</th>
                                    <th>Rejected Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rejected_registrations as $reg): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($reg['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($reg['email']); ?></td>
                                        <td><?php echo htmlspecialchars($reg['programme_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($reg['intake_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($reg['rejection_reason'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($reg['updated_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Approve Modal -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('approveModal')">&times;</span>
            <h2>Approve Registration</h2>
            <p>Are you sure you want to approve this registration?</p>
            <form method="POST">
                <input type="hidden" name="action" value="approve_registration">
                <input type="hidden" id="approve_registration_id" name="pending_student_id">
                <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Approve</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('approveModal')"><i class="fas fa-times"></i> Cancel</button>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('rejectModal')">&times;</span>
            <h2>Reject Registration</h2>
            <form method="POST">
                <input type="hidden" name="action" value="reject_registration">
                <input type="hidden" id="reject_registration_id" name="pending_student_id">
                <div class="form-group">
                    <label for="rejection_reason">Rejection Reason *</label>
                    <textarea id="rejection_reason" name="rejection_reason" rows="3" required placeholder="Enter the reason for rejection..."></textarea>
                </div>
                <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> Reject</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')"><i class="fas fa-times"></i> Cancel</button>
            </form>
        </div>
    </div>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script>
        function openApproveModal(id) {
            document.getElementById('approve_registration_id').value = id;
            document.getElementById('approveModal').style.display = 'block';
        }
        
        function openRejectModal(id) {
            document.getElementById('reject_registration_id').value = id;
            document.getElementById('rejectModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function viewStudentDetails(id) {
            // In a real implementation, you would fetch and display student details
            alert('Student details for ID: ' + id + '\n\nIn a real implementation, this would show detailed student information including credentials for manual distribution.');
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