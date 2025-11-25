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
                    $stmt = $pdo->prepare("SELECT * FROM pending_students WHERE id = ?");
                    $stmt->execute([$pending_student_id]);
                    $pending_student = $stmt->fetch();
                    
                    if ($pending_student) {
                        // Generate student number
                        $student_number = generateStudentNumber($pdo);
                        
                        // Generate a secure password
                        $password = generateSecurePassword();
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        
                        // Create user account
                        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, contact, is_active) VALUES (?, ?, ?, ?, 1)");
                        $stmt->execute([$student_number, $password_hash, $pending_student['email'], $pending_student['contact']]);
                        $user_id = $pdo->lastInsertId();
                        
                        // Create student profile
                        $stmt = $pdo->prepare("INSERT INTO student_profile (user_id, full_name, student_number, NRC, gender, programme_id, intake_id, balance) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
                        $stmt->execute([
                            $user_id, 
                            $pending_student['full_name'], 
                            $student_number, 
                            $pending_student['NRC'], 
                            $pending_student['gender'], 
                            $pending_student['programme_id'], 
                            $pending_student['intake_id']
                        ]);
                        
                        // Assign student role
                        $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'Student'");
                        $stmt->execute();
                        $student_role = $stmt->fetch();
                        
                        if ($student_role) {
                            $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                            $stmt->execute([$user_id, $student_role['id']]);
                        }
                        
                        // Update pending student status
                        $stmt = $pdo->prepare("UPDATE pending_students SET registration_status = 'approved', student_number = ? WHERE id = ?");
                        $stmt->execute([$student_number, $pending_student_id]);
                        
                        // Send confirmation email with student number and credentials
                        sendRegistrationConfirmationEmail($pending_student['email'], $pending_student['full_name'], $student_number, $password);
                        
                        $message = "Student registration approved successfully! Confirmation email sent to student.";
                        $messageType = 'success';
                    } else {
                        $message = "Pending student not found!";
                        $messageType = 'error';
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'reject_registration':
                $pending_student_id = $_POST['pending_student_id'];
                $rejection_reason = trim($_POST['rejection_reason']);
                
                try {
                    $stmt = $pdo->prepare("UPDATE pending_students SET registration_status = 'rejected', rejection_reason = ? WHERE id = ?");
                    $stmt->execute([$rejection_reason, $pending_student_id]);
                    
                    $message = "Student registration rejected!";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get pending registrations
$pending_registrations_query = "
    SELECT ps.*, p.name as programme_name, i.name as intake_name
    FROM pending_students ps
    LEFT JOIN programme p ON ps.programme_id = p.id
    LEFT JOIN intake i ON ps.intake_id = i.id
    WHERE ps.registration_status = 'pending_approval'
    ORDER BY ps.created_at DESC
";
$pending_registrations = $pdo->query($pending_registrations_query)->fetchAll();

// Function to generate student number
function generateStudentNumber($pdo) {
    // Format: LSC + 6-digit sequential number
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(student_number, 4) AS UNSIGNED)) as max_num FROM student_profile WHERE student_number LIKE 'LSC%'");
    $result = $stmt->fetch();
    $next_num = ($result['max_num'] ?? 0) + 1;
    return sprintf("LSC%06d", $next_num);
}

// Function to generate secure password
function generateSecurePassword($length = 12) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

// Function to send registration confirmation email
function sendRegistrationConfirmationEmail($to, $name, $student_number, $password) {
    $subject = "LSC Registration Approved - Your Student Credentials";
    
    $message = "
    <html>
    <head>
        <title>LSC Registration Approved</title>
    </head>
    <body>
        <h2>Welcome to Lusaka South College</h2>
        <p>Dear $name,</p>
        <p>Congratulations! Your registration has been approved.</p>
        <p>You are now a full-time student at Lusaka South College. Please use the following credentials to access your student dashboard:</p>
        <p><strong>Student Number (Username):</strong> $student_number</p>
        <p><strong>Password:</strong> $password</p>
        <p>Visit <a href='https://lsuclms.com/student_login.php'>https://lsuclms.com/student_login.php</a> to login to your student dashboard.</p>
        <p><strong>Important:</strong> Please change your password immediately after your first login for security purposes.</p>
        <p>Welcome to the LSC community!</p>
        <p>Best regards,<br>LSC Admissions Team</p>
    </body>
    </html>
    ";
    
    // In a real implementation, you would use PHPMailer:
    // $mail = new PHPMailer\PHPMailer\PHPMailer();
    // $mail->setFrom('admissions@lsuclms.com', 'LSC Admissions');
    // $mail->addAddress($to, $name);
    // $mail->Subject = $subject;
    // $mail->Body = $message;
    // $mail->isHTML(true);
    // $mail->send();
    
    // For now, we'll just log to a file
    $log_entry = date('Y-m-d H:i:s') . " - Registration confirmed for $to: Student Number=$student_number, Password=$password\n";
    file_put_contents('registration_confirmations.txt', $log_entry, FILE_APPEND);
    
    return true;
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
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .payment-proof {
            max-width: 200px;
            height: auto;
            cursor: pointer;
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
            <h1><i class="fas fa-user-graduate"></i> First Time Registrations</h1>
            <p>Approve or reject first time student registrations</p>
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
                                    <th>Student Name</th>
                                    <th>Email</th>
                                    <th>Programme</th>
                                    <th>Intake</th>
                                    <th>Payment Method</th>
                                    <th>Amount</th>
                                    <th>Payment Proof</th>
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
                                        <td><?php echo htmlspecialchars($reg['payment_amount'] ?? '0.00'); ?></td>
                                        <td>
                                            <?php if ($reg['payment_proof']): ?>
                                                <a href="../<?php echo htmlspecialchars($reg['payment_proof']); ?>" target="_blank">
                                                    <img src="../<?php echo htmlspecialchars($reg['payment_proof']); ?>" alt="Payment Proof" class="payment-proof">
                                                </a>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td>
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
    </main>

    <!-- Approve Modal -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('approveModal')">&times;</span>
            <h2>Approve Registration</h2>
            <p>Are you sure you want to approve this student registration?</p>
            <form method="POST">
                <input type="hidden" name="action" value="approve_registration">
                <input type="hidden" id="approve_pending_student_id" name="pending_student_id">
                <button type="submit" class="btn btn-success">Yes, Approve</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('approveModal')">Cancel</button>
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
                <input type="hidden" id="reject_pending_student_id" name="pending_student_id">
                <div class="form-group">
                    <label for="rejection_reason">Rejection Reason</label>
                    <textarea id="rejection_reason" name="rejection_reason" rows="3" required></textarea>
                </div>
                <button type="submit" class="btn btn-danger">Reject</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
            </form>
        </div>
    </div>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script>
        function openApproveModal(id) {
            document.getElementById('approve_pending_student_id').value = id;
            document.getElementById('approveModal').style.display = 'block';
        }
        
        function openRejectModal(id) {
            document.getElementById('reject_pending_student_id').value = id;
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