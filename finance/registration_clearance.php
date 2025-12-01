<?php
require '../config.php';
require '../auth.php';

// Check if user is logged in and has sub admin role
if (!currentUserId()) {
    header('Location: ../login.php');
    exit;
}

requireRole('Sub Admin (Finance)', $pdo);

// Get sub admin profile
$stmt = $pdo->prepare("SELECT ap.full_name, ap.staff_id FROM admin_profile ap WHERE ap.user_id = ?");
$stmt->execute([currentUserId()]);
$admin = $stmt->fetch();

// Handle form actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'clear_registration':
                $student_id = $_POST['student_id'];
                $registration_type = $_POST['registration_type']; // 'course' or 'first_time'
                
                try {
                    if ($registration_type === 'course') {
                        // Mark course registration as cleared for finance
                        $stmt = $pdo->prepare("UPDATE course_registration SET finance_cleared = 1, finance_cleared_at = NOW(), finance_cleared_by = ? WHERE student_id = ? AND status = 'approved' AND finance_cleared = 0");
                        $stmt->execute([currentUserId(), $student_id]);
                        
                        if ($stmt->rowCount() > 0) {
                            $message = "Course registration cleared successfully!";
                            $messageType = 'success';
                        } else {
                            $message = "No pending course registrations found for this student!";
                            $messageType = 'error';
                        }
                    } else {
                        // Mark first-time registration as cleared for finance
                        $stmt = $pdo->prepare("UPDATE pending_students SET finance_cleared = 1, finance_cleared_at = NOW(), finance_cleared_by = ? WHERE id = ? AND registration_status = 'approved' AND finance_cleared = 0");
                        $stmt->execute([currentUserId(), $student_id]);
                        
                        if ($stmt->rowCount() > 0) {
                            $message = "First-time registration cleared successfully!";
                            $messageType = 'success';
                        } else {
                            $message = "No pending first-time registrations found for this student!";
                            $messageType = 'error';
                        }
                    }
                } catch (Exception $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Ensure finance_cleared columns exist in both tables
try {
    // Add finance clearance columns to course_registration table if they don't exist
    $pdo->exec("ALTER TABLE course_registration ADD COLUMN IF NOT EXISTS finance_cleared TINYINT(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE course_registration ADD COLUMN IF NOT EXISTS finance_cleared_at TIMESTAMP NULL");
    $pdo->exec("ALTER TABLE course_registration ADD COLUMN IF NOT EXISTS finance_cleared_by INT NULL");
    
    // Add finance clearance columns to pending_students table if they don't exist
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS finance_cleared TINYINT(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS finance_cleared_at TIMESTAMP NULL");
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS finance_cleared_by INT NULL");
    
    // Add registration_status column to pending_students if it doesn't exist
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS registration_status ENUM('pending','pending_approval','approved','rejected') DEFAULT 'pending'");
    
    // Add student_number column to pending_students if it doesn't exist
    $pdo->exec("ALTER TABLE pending_students ADD COLUMN IF NOT EXISTS student_number VARCHAR(50)");
} catch (Exception $e) {
    // Columns might already exist or there might be a permissions issue
    error_log("Error adding finance clearance columns: " . $e->getMessage());
}

// Get approved course registrations that are not yet cleared by finance
try {
    $course_registrations_query = "
        SELECT cr.*, sp.full_name, sp.student_number, c.name as course_name, i.name as intake_name, p.name as programme_name
        FROM course_registration cr
        JOIN student_profile sp ON cr.student_id = sp.user_id
        JOIN course c ON cr.course_id = c.id
        JOIN intake i ON sp.intake_id = i.id
        LEFT JOIN programme p ON sp.programme_id = p.id
        WHERE cr.status = 'approved' AND cr.finance_cleared = 0
        ORDER BY cr.submitted_at DESC
    ";
    $course_registrations = $pdo->query($course_registrations_query)->fetchAll();
} catch (Exception $e) {
    $course_registrations = [];
    error_log("Error fetching course registrations: " . $e->getMessage());
}

// Get approved first-time registrations that are not yet cleared by finance
try {
    $first_time_registrations_query = "
        SELECT ps.*, p.name as programme_name, i.name as intake_name
        FROM pending_students ps
        LEFT JOIN programme p ON ps.programme_id = p.id
        LEFT JOIN intake i ON ps.intake_id = i.id
        WHERE ps.registration_status = 'approved' AND ps.finance_cleared = 0
        ORDER BY ps.updated_at DESC
    ";
    $first_time_registrations = $pdo->query($first_time_registrations_query)->fetchAll();
} catch (Exception $e) {
    $first_time_registrations = [];
    error_log("Error fetching first-time registrations: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Clearance - LSC SRMS</title>
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
                <span class="welcome-text">Welcome, <?php echo htmlspecialchars($admin['full_name'] ?? 'Finance Administrator'); ?></span>
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
            <h3><i class="fas fa-tachometer-alt"></i> Finance Panel</h3>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section">
                <h4>Student Management</h4>
                <a href="view_students.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>View Students</span>
                </a>
                <a href="manage_fees.php" class="nav-item">
                    <i class="fas fa-money-bill"></i>
                    <span>Manage Fees & Finances</span>
                </a>
                <a href="manage_programme_fees.php" class="nav-item">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Programme Fees</span>
                </a>
                <a href="results_access.php" class="nav-item">
                    <i class="fas fa-lock"></i>
                    <span>Manage Results Access</span>
                </a>
            </div>
            
            <div class="nav-section">
                <h4>Financial Operations</h4>
                <a href="manage_programme_fees.php" class="nav-item">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Programme Fees</span>
                </a>
                <a href="income_expenses.php" class="nav-item">
                    <i class="fas fa-chart-pie"></i>
                    <span>Income & Expenses</span>
                </a>
                <a href="finance_reports.php" class="nav-item">
                    <i class="fas fa-file-invoice"></i>
                    <span>Finance Reports</span>
                </a>
                <a href="registration_clearance.php" class="nav-item active">
                    <i class="fas fa-user-check"></i>
                    <span>Registration Clearance</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="content-header">
            <h1><i class="fas fa-user-check"></i> Registration Clearance</h1>
            <p>Clear student accounts for course registrations and first-time registrations</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <button class="tab-item active" onclick="showTab('course-registrations')">Course Registrations</button>
            <button class="tab-item" onclick="showTab('first-time-registrations')">First-Time Registrations</button>
        </div>
        
        <!-- Course Registrations Tab -->
        <div id="course-registrations" class="tab-content">
            <div class="table-section">
                <div class="table-card">
                    <div class="table-header">
                        <h2><i class="fas fa-clipboard-check"></i> Approved Course Registrations</h2>
                        <p>Pending finance clearance</p>
                    </div>
                    
                    <?php if (empty($course_registrations)): ?>
                        <div class="empty-state">
                            <i class="fas fa-clipboard-check"></i>
                            <h3>No Pending Course Registrations</h3>
                            <p>All course registrations have been cleared by finance.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="users-table">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>Programme</th>
                                        <th>Course</th>
                                        <th>Intake</th>
                                        <th>Term</th>
                                        <th>Submitted At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($course_registrations as $registration): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($registration['student_number']); ?></td>
                                            <td><?php echo htmlspecialchars($registration['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($registration['programme_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($registration['course_name']); ?></td>
                                            <td><?php echo htmlspecialchars($registration['intake_name']); ?></td>
                                            <td><?php echo htmlspecialchars($registration['term']); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($registration['submitted_at'])); ?></td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="clear_registration">
                                                    <input type="hidden" name="student_id" value="<?php echo $registration['student_id']; ?>">
                                                    <input type="hidden" name="registration_type" value="course">
                                                    <button type="submit" class="btn btn-green btn-sm" title="Clear Registration">
                                                        <i class="fas fa-check"></i> Clear
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
        </div>
        
        <!-- First-Time Registrations Tab -->
        <div id="first-time-registrations" class="tab-content" style="display: none;">
            <div class="table-section">
                <div class="table-card">
                    <div class="table-header">
                        <h2><i class="fas fa-user-graduate"></i> Approved First-Time Registrations</h2>
                        <p>Pending finance clearance</p>
                    </div>
                    
                    <?php if (empty($first_time_registrations)): ?>
                        <div class="empty-state">
                            <i class="fas fa-user-graduate"></i>
                            <h3>No Pending First-Time Registrations</h3>
                            <p>All first-time registrations have been cleared by finance.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="users-table">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Email</th>
                                        <th>Programme</th>
                                        <th>Intake</th>
                                        <th>Student Number</th>
                                        <th>Approved At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($first_time_registrations as $registration): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($registration['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($registration['email']); ?></td>
                                            <td><?php echo htmlspecialchars($registration['programme_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($registration['intake_name']); ?></td>
                                            <td><?php echo htmlspecialchars($registration['student_number'] ?? 'Not Assigned'); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($registration['updated_at'])); ?></td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="clear_registration">
                                                    <input type="hidden" name="student_id" value="<?php echo $registration['id']; ?>">
                                                    <input type="hidden" name="registration_type" value="first_time">
                                                    <button type="submit" class="btn btn-green btn-sm" title="Clear Registration">
                                                        <i class="fas fa-check"></i> Clear
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
        </div>
    </main>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script>
        function showTab(tabId) {
            // Hide all tab contents
            document.getElementById('course-registrations').style.display = 'none';
            document.getElementById('first-time-registrations').style.display = 'none';
            
            // Remove active class from all tab items
            const tabItems = document.querySelectorAll('.tab-item');
            tabItems.forEach(item => item.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabId).style.display = 'block';
            
            // Add active class to clicked tab item
            event.target.classList.add('active');
        }
        
        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>