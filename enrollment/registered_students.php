<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in and has enrollment officer role
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

requireRole('Enrollment Officer', $pdo);

// Handle sending email to student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_email') {
    $student_id = $_POST['student_id'];
    
    try {
        // Fetch student details
        $stmt = $pdo->prepare("SELECT * FROM registered_students WHERE id = ? AND status = 'pending_notification'");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch();
        
        if ($student) {
            // Generate student number if not already generated
            if (empty($student['student_number']) || $student['student_number'] === 'Not Generated') {
                // Generate a unique student number
                $prefix = 'LSC';
                $year = date('Y');
                // Get the last student number to increment
                $last_stmt = $pdo->query("SELECT student_number FROM registered_students WHERE student_number LIKE 'LSC{$year}%' AND student_number IS NOT NULL AND student_number != '' AND student_number != 'Not Generated' ORDER BY id DESC LIMIT 1");
                $last_record = $last_stmt->fetch();
                
                if ($last_record) {
                    $last_number = intval(substr($last_record['student_number'], -6));
                    $next_number = str_pad($last_number + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $next_number = '000001';
                }
                
                $student_number = $prefix . $year . $next_number;
                
                // Update student number in registered_students
                $update_stmt = $pdo->prepare("UPDATE registered_students SET student_number = ? WHERE id = ?");
                $update_stmt->execute([$student_number, $student_id]);
                $student['student_number'] = $student_number;
            }
            
            // Update status to email_sent
            $stmt = $pdo->prepare("UPDATE registered_students SET status = 'email_sent', email_sent_at = NOW() WHERE id = ?");
            $stmt->execute([$student_id]);
            
            // Here you would typically send an actual email
            // For now, we'll just log that the email should be sent
            error_log("Email should be sent to: " . $student['student_email'] . " with student number: " . $student['student_number']);
            
            $message = "Email sent successfully to " . htmlspecialchars($student['student_name']);
            $messageType = 'success';
        } else {
            $message = "Student not found or email already sent.";
            $messageType = 'error';
        }
    } catch (Exception $e) {
        $message = "Error sending email: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Get enrollment officer profile
$stmt = $pdo->prepare("SELECT sp.full_name, sp.staff_id FROM staff_profile sp WHERE sp.user_id = ?");
$stmt->execute([currentUserId()]);
$enrollmentOfficer = $stmt->fetch();

// Get registered students for pending notifications and completed registrations
try {
    // Get pending notifications (students waiting for email)
    $stmt = $pdo->prepare("SELECT * FROM registered_students WHERE status = 'pending_notification' ORDER BY created_at DESC");
    $stmt->execute();
    $pendingNotifications = $stmt->fetchAll();
    
    // Get completed registrations (students who have received emails)
    $stmt = $pdo->prepare("SELECT * FROM registered_students WHERE status = 'email_sent' ORDER BY email_sent_at DESC");
    $stmt->execute();
    $completedRegistrations = $stmt->fetchAll();
} catch (Exception $e) {
    $pendingNotifications = [];
    $completedRegistrations = [];
    error_log("Error fetching registered students: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registered Students - LSC SRMS</title>
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
                <a href="my_approvals.php" class="nav-item">
                    <i class="fas fa-thumbs-up"></i>
                    <span>My Approvals</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Registered Students</h4>
                <a href="registered_students.php" class="nav-item active">
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
            <h1><i class="fas fa-user-graduate"></i> Registered Students</h1>
            <p>Manage students who have been cleared by finance</p>
        </div>
        
        <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Pending Notifications -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-bell"></i> Pending Notifications (<?php echo count($pendingNotifications); ?>)</h3>
            </div>
            <div class="panel-content">
                <?php if (empty($pendingNotifications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-bell"></i>
                        <p>No pending notifications</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Email</th>
                                    <th>Student Number</th>
                                    <th>Programme</th>
                                    <th>Intake</th>
                                    <th>Payment Amount</th>
                                    <th>Registration Type</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingNotifications as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['student_email']); ?></td>
                                        <td><?php echo htmlspecialchars($student['student_number'] ?? 'Not Generated'); ?></td>
                                        <td><?php echo htmlspecialchars($student['programme_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($student['intake_name'] ?? 'N/A'); ?></td>
                                        <td>K<?php echo number_format($student['payment_amount'] ?? 0, 2); ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $student['registration_type'])); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="send_email">
                                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    <i class="fas fa-paper-plane"></i> Send Email
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Completed Registrations -->
        <div class="data-panel">
            <div class="panel-header">
                <h3><i class="fas fa-check-circle"></i> Completed Registrations (<?php echo count($completedRegistrations); ?>)</h3>
            </div>
            <div class="panel-content">
                <?php if (empty($completedRegistrations)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No completed registrations</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Email</th>
                                    <th>Student Number</th>
                                    <th>Programme</th>
                                    <th>Intake</th>
                                    <th>Payment Amount</th>
                                    <th>Registration Type</th>
                                    <th>Email Sent At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($completedRegistrations as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['student_email']); ?></td>
                                        <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                                        <td><?php echo htmlspecialchars($student['programme_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($student['intake_name'] ?? 'N/A'); ?></td>
                                        <td>K<?php echo number_format($student['payment_amount'] ?? 0, 2); ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $student['registration_type'])); ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($student['email_sent_at'])); ?></td>
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